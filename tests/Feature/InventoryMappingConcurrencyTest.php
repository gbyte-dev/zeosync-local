<?php

use App\Http\Controllers\InventoryMappingController;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AutoSkuMappingService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('email')->nullable();
            $table->string('access_token')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sync_limit')->default(100);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_id')->nullable();
            $table->string('title')->nullable();
            $table->json('variants')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('settings')) {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->boolean('auto_sku_mapping')->default(1);
            $table->boolean('auto_sync')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_parent_asin')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('quantity')->nullable();
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'shopify_variant_id'], 'unique_shop_shopify_variant');
            $table->unique(['shop_id', 'amazon_sku'], 'unique_shop_amazon_sku');
        });
    } else {
        // In memory test or shared DB, ensure table is clean
        ProductMarketplaceMapping::truncate();
    }
});

/* =========================================================================
 * 1. DATABASE UNIQUE CONSTRAINT TESTS
 * ========================================================================= */

it('enforces database-level uniqueness on (shop_id, shopify_variant_id)', function () {
    ProductMarketplaceMapping::create([
        'shop_id'            => 1,
        'shopify_variant_id' => 'VAR-100',
        'amazon_sku'         => 'SKU-AAA',
    ]);

    expect(function () {
        ProductMarketplaceMapping::create([
            'shop_id'            => 1,
            'shopify_variant_id' => 'VAR-100',
            'amazon_sku'         => 'SKU-BBB',
        ]);
    })->toThrow(QueryException::class);

    expect(ProductMarketplaceMapping::where('shop_id', 1)->count())->toBe(1);
});

it('enforces database-level uniqueness on (shop_id, amazon_sku)', function () {
    ProductMarketplaceMapping::create([
        'shop_id'            => 1,
        'shopify_variant_id' => 'VAR-100',
        'amazon_sku'         => 'SKU-SAME',
    ]);

    expect(function () {
        ProductMarketplaceMapping::create([
            'shop_id'            => 1,
            'shopify_variant_id' => 'VAR-200',
            'amazon_sku'         => 'SKU-SAME',
        ]);
    })->toThrow(QueryException::class);

    expect(ProductMarketplaceMapping::where('shop_id', 1)->count())->toBe(1);
});

it('allows different shops to map the same shopify_variant_id and amazon_sku (tenant isolation)', function () {
    $mappingA = ProductMarketplaceMapping::create([
        'shop_id'            => 10,
        'shopify_variant_id' => 'SHARED-VAR-1',
        'amazon_sku'         => 'SHARED-SKU-1',
    ]);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'            => 20,
        'shopify_variant_id' => 'SHARED-VAR-1',
        'amazon_sku'         => 'SHARED-SKU-1',
    ]);

    expect($mappingA)->not->toBeNull();
    expect($mappingB)->not->toBeNull();
    expect(ProductMarketplaceMapping::count())->toBe(2);
});

it('allows multiple mapping rows with NULL amazon_sku for the same shop (drafts)', function () {
    $mapping1 = ProductMarketplaceMapping::create([
        'shop_id'            => 1,
        'shopify_variant_id' => 'DRAFT-VAR-1',
        'amazon_sku'         => null,
    ]);

    $mapping2 = ProductMarketplaceMapping::create([
        'shop_id'            => 1,
        'shopify_variant_id' => 'DRAFT-VAR-2',
        'amazon_sku'         => null,
    ]);

    expect($mapping1)->not->toBeNull();
    expect($mapping2)->not->toBeNull();
    expect(ProductMarketplaceMapping::where('shop_id', 1)->whereNull('amazon_sku')->count())->toBe(2);
});

/* =========================================================================
 * 2. CONTROLLER HARDENING & SEQUENTIAL / CONCURRENT TESTS
 * ========================================================================= */

it('rejects sequential duplicate mapping requests with HTTP 422', function () {
    $shop = Shop::create([
        'id'           => 99,
        'shop'         => 'shop-concurrency.myshopify.com',
        'access_token' => 'token-99',
        'is_active'    => 1,
    ]);

    $plan = Plan::create(['name' => 'Pro', 'sync_limit' => 100]);
    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => $plan->id,
        'status'             => 'active',
        'started_at'         => now()->subDays(1),
        'current_period_end' => now()->addDays(30),
    ]);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'SHP-900',
        'title'      => 'Test Product',
        'variants'   => [
            [
                'id'                 => 'VAR-901',
                'title'              => 'Default',
                'inventory_quantity' => 10,
            ]
        ],
    ]);

    $controller = new InventoryMappingController();

    // Request 1: should succeed
    $request1 = Request::create('/inventory/save-product-mapping', 'POST', [
        'product_id'                => $product->id,
        'variant_id'                => 'VAR-901',
        'shopify_product_id'        => 'SHP-900',
        'shopify_variant_id'        => 'VAR-901',
        'shopify_inventory_item_id' => 'INV-901',
        'amazon_sku'                => 'AMZ-SKU-901',
    ]);
    $request1->attributes->set('active_shop_model', $shop);

    $response1 = $controller->saveProductMapping($request1);
    expect($response1->getStatusCode())->toBe(200);
    $data1 = $response1->getData(true);
    expect($data1['success'])->toBeTrue();

    // Request 2 (Sequential Duplicate): should return 422
    $request2 = Request::create('/inventory/save-product-mapping', 'POST', [
        'product_id'                => $product->id,
        'variant_id'                => 'VAR-901',
        'shopify_product_id'        => 'SHP-900',
        'shopify_variant_id'        => 'VAR-901',
        'shopify_inventory_item_id' => 'INV-901',
        'amazon_sku'                => 'AMZ-SKU-901',
    ]);
    $request2->attributes->set('active_shop_model', $shop);

    $response2 = $controller->saveProductMapping($request2);
    expect($response2->getStatusCode())->toBe(422);
    $data2 = $response2->getData(true);
    expect($data2['success'])->toBeFalse();
    expect($data2['message'])->toBe('Variant already mapped.');

    // Verify DB has only 1 record
    expect(ProductMarketplaceMapping::where('shop_id', $shop->id)->count())->toBe(1);
});

it('gracefully handles concurrent database duplicate-key exception in saveProductMapping with 422 JSON', function () {
    $shop = Shop::create([
        'id'           => 100,
        'shop'         => 'race-shop.myshopify.com',
        'access_token' => 'token-100',
        'is_active'    => 1,
    ]);

    $plan = Plan::create(['name' => 'Pro', 'sync_limit' => 100]);
    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => $plan->id,
        'status'             => 'active',
        'started_at'         => now()->subDays(1),
        'current_period_end' => now()->addDays(30),
    ]);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'SHP-1000',
        'title'      => 'Race Product',
        'variants'   => [
            [
                'id'                 => 'VAR-1001',
                'title'              => 'Default',
                'inventory_quantity' => 15,
            ]
        ],
    ]);

    // Simulate winning request having already inserted the record right after Request B's exists() check
    ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'product_id'                => $product->id,
        'variant_id'                => 'VAR-1001',
        'shopify_product_id'        => 'SHP-1000',
        'shopify_variant_id'        => 'VAR-1001',
        'shopify_inventory_item_id' => 'INV-1001',
        'amazon_sku'                => 'AMZ-SKU-1001',
    ]);

    // Now call saveProductMapping (simulating losing concurrent request that hits insert)
    // Even if it attempts insert, the DB constraint triggers QueryException, which controller catches
    $controller = new InventoryMappingController();
    $request = Request::create('/inventory/save-product-mapping', 'POST', [
        'product_id'                => $product->id,
        'variant_id'                => 'VAR-1001',
        'shopify_product_id'        => 'SHP-1000',
        'shopify_variant_id'        => 'VAR-1001',
        'shopify_inventory_item_id' => 'INV-1001',
        'amazon_sku'                => 'AMZ-SKU-1001',
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->saveProductMapping($request);

    expect($response->getStatusCode())->toBe(422);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['message'])->toBe('Variant already mapped.');

    // Exactly 1 row in DB
    expect(ProductMarketplaceMapping::where('shop_id', $shop->id)->count())->toBe(1);
});

it('gracefully handles duplicate amazon_sku collision in saveAmazonMapping with 422 JSON', function () {
    $shop = Shop::create([
        'id'           => 101,
        'shop'         => 'amazon-race.myshopify.com',
        'access_token' => 'token-101',
        'is_active'    => 1,
    ]);

    $plan = Plan::create(['name' => 'Pro', 'sync_limit' => 100]);
    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => $plan->id,
        'status'             => 'active',
        'started_at'         => now()->subDays(1),
        'current_period_end' => now()->addDays(30),
    ]);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'SHP-2000',
        'title'      => 'Amazon Race Product',
        'variants'   => [
            [
                'id'                    => 2001,
                'title'                 => 'Variant 1',
                'inventory_item_id'     => 'INV-2001',
                'inventory_quantity'    => 10,
            ],
            [
                'id'                    => 2002,
                'title'                 => 'Variant 2',
                'inventory_item_id'     => 'INV-2002',
                'inventory_quantity'    => 10,
            ]
        ],
    ]);

    // Existing mapping for Variant 2001 with SKU-XYZ
    ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'product_id'                => $product->id,
        'variant_id'                => '2001',
        'shopify_product_id'        => 'SHP-2000',
        'shopify_variant_id'        => '2001',
        'shopify_inventory_item_id' => 'INV-2001',
        'amazon_sku'                => 'SKU-XYZ',
    ]);

    $controller = new InventoryMappingController();

    // Attempt to map Variant 2002 to the SAME SKU-XYZ
    $request = Request::create('/inventory/save-amazon-mapping', 'POST', [
        'product_id'         => 'SHP-2000',
        'shopify_variant_id' => '2002',
        'amazon_sku'         => 'SKU-XYZ',
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->saveAmazonMapping($request);

    expect($response->getStatusCode())->toBe(422);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['message'])->toContain('already mapped with another Shopify variant');
});

/* =========================================================================
 * 3. AUTO SKU MAPPING SERVICE CONCURRENCY TEST
 * ========================================================================= */

it('AutoSkuMappingService catches concurrent duplicate exception without crashing', function () {
    $shop = Shop::create([
        'id'           => 102,
        'shop'         => 'auto-sku.myshopify.com',
        'access_token' => 'token-102',
        'is_active'    => 1,
    ]);

    \App\Models\Setting::create([
        'shop_id'          => $shop->id,
        'auto_sku_mapping' => 1,
    ]);

    // Pre-insert mapping simulating another thread already inserting
    ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_product_id'        => 'PID-1',
        'shopify_variant_id'        => 'VID-1',
        'shopify_inventory_item_id' => 'INV-1',
        'amazon_sku'                => 'MATCH-SKU-1',
    ]);

    $service = new AutoSkuMappingService();

    // Calling handle with inventory containing same item
    // It should safely handle / log without throwing QueryException
    expect(function () use ($service, $shop) {
        $service->handle($shop, [
            [
                'pid'               => 'PID-1',
                'vid'               => 'VID-1',
                'sku'               => 'MATCH-SKU-1',
                'inventory_item_id' => 'INV-1',
                'qty'               => 5,
            ]
        ], [
            [
                'sku' => 'MATCH-SKU-1',
            ]
        ]);
    })->not->toThrow(Throwable::class);

    // Also test createMapping directly throwing duplicate key QueryException
    // Reflection to invoke private createMapping
    $ref = new ReflectionClass($service);
    $createMappingMethod = $ref->getMethod('createMapping');
    $createMappingMethod->setAccessible(true);

    // Call createMapping with duplicate data that will hit ProductMarketplaceMapping::create
    expect(function () use ($createMappingMethod, $service, $shop) {
        $createMappingMethod->invoke($service, $shop, [
            'pid'               => 'PID-1',
            'vid'               => 'VID-1',
            'sku'               => 'MATCH-SKU-1',
            'inventory_item_id' => 'INV-1',
            'qty'               => 5,
        ], [
            'sku' => 'MATCH-SKU-1',
        ]);
    })->not->toThrow(Throwable::class);

    // Exactly 1 record in DB
    expect(ProductMarketplaceMapping::where('shop_id', $shop->id)->count())->toBe(1);
});

/* =========================================================================
 * 4. MAPPING RESPONSE SYNC USAGE / QUOTA UPDATE TESTS
 * ========================================================================= */

it('returns updated sync_usage and quota counts on saveProductMapping, saveAmazonMapping, mappings, and unmap', function () {
    $plan = Plan::create([
        'name' => 'Growth',
        'sync_limit' => 50,
    ]);

    $shop = Shop::create([
        'id'           => 301,
        'shop'         => 'quota-test.myshopify.com',
        'access_token' => 'token-301',
        'is_active'    => 1,
    ]);

    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => $plan->id,
        'status'             => 'active',
        'started_at'         => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'SHP-301',
        'title'      => 'Sync Usage Test Product',
        'variants'   => [
            [
                'id'                 => '30101',
                'title'              => 'Variant 1',
                'inventory_item_id' => 'INV-30101',
                'inventory_quantity' => 10,
            ],
            [
                'id'                 => '30102',
                'title'              => 'Variant 2',
                'inventory_item_id' => 'INV-30102',
                'inventory_quantity' => 20,
            ],
        ],
    ]);

    $controller = new InventoryMappingController();

    // 1. Test saveProductMapping response
    $req1 = Request::create('/inventory/save-product-mapping', 'POST', [
        'product_id'                => $product->id,
        'variant_id'                => '30101',
        'shopify_product_id'        => 'SHP-301',
        'shopify_variant_id'        => '30101',
        'shopify_inventory_item_id' => 'INV-30101',
        'amazon_sku'                => 'AMZ-SKU-30101',
    ]);
    $req1->attributes->set('active_shop_model', $shop);

    $res1 = $controller->saveProductMapping($req1);
    expect($res1->getStatusCode())->toBe(200);
    $data1 = $res1->getData(true);
    expect($data1['success'])->toBeTrue()
        ->and($data1['used'])->toBe(1)
        ->and($data1['limit'])->toBe(50)
        ->and($data1['remaining'])->toBe(49)
        ->and($data1['sync_usage']['used'])->toBe(1);

    // 2. Test saveAmazonMapping response
    $req2 = Request::create('/inventory/save-amazon-mapping', 'POST', [
        'product_id'         => 'SHP-301',
        'shopify_variant_id' => '30102',
        'amazon_sku'         => 'AMZ-SKU-30102',
    ]);
    $req2->attributes->set('active_shop_model', $shop);

    $res2 = $controller->saveAmazonMapping($req2);
    expect($res2->getStatusCode())->toBe(200);
    $data2 = $res2->getData(true);
    expect($data2['success'])->toBeTrue()
        ->and($data2['used'])->toBe(2)
        ->and($data2['limit'])->toBe(50)
        ->and($data2['remaining'])->toBe(48)
        ->and($data2['sync_usage']['used'])->toBe(2);

    // 3. Test mappings endpoint response
    $req3 = Request::create('/inventory/mappings', 'GET');
    $req3->attributes->set('active_shop_model', $shop);

    $res3 = $controller->mappings($req3);
    expect($res3->getStatusCode())->toBe(200);
    $data3 = $res3->getData(true);
    expect($data3['success'])->toBeTrue()
        ->and(count($data3['mappings']))->toBe(2)
        ->and($data3['sync_usage']['used'])->toBe(2)
        ->and($data3['sync_usage']['remaining'])->toBe(48);

    // 4. Test unmap response
    $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)->where('shopify_variant_id', '30101')->first();
    $req4 = Request::create('/inventory/unmap/' . $mapping->id, 'DELETE');
    $req4->attributes->set('active_shop_model', $shop);

    $res4 = $controller->unmap($req4, $mapping);
    expect($res4->getStatusCode())->toBe(200);
    $data4 = $res4->getData(true);
    expect($data4['success'])->toBeTrue()
        ->and($data4['sync_usage']['used'])->toBe(1)
        ->and($data4['sync_usage']['remaining'])->toBe(49);
});

