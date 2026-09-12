<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Shop;
use App\Models\ProductMarketplaceMapping;
use App\Models\InventorySyncOperation;
use App\Services\ShopifyService;
use App\Services\ShopifySessionTokenValidator;
use App\Services\AmazonService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use App\Jobs\ProcessInventoryUpdateJob;
use Mockery;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();

        if (str_contains($url, 'locations.json')) {
            if (str_contains($url, 'fail-loc.myshopify.com')) {
                return Http::response(['errors' => 'Location service unavailable'], 500);
            }
            if (str_contains($url, 'self-heal.myshopify.com')) {
                return Http::response([
                    'locations' => [
                        ['id' => '8888', 'name' => 'Auto Refreshed Location', 'active' => true],
                    ],
                ], 200);
            }
            return Http::response([
                'locations' => [
                    ['id' => '10001', 'name' => 'Default Location', 'active' => true],
                ],
            ], 200);
        }

        if (str_contains($url, 'inventory_levels/set.json')) {
            $data = $request->data();
            $qty = $data['available'] ?? 0;
            return Http::response(['inventory_level' => ['available' => $qty]], 200);
        }

        if (str_contains($url, 'inventory_levels.json')) {
            $params = [];
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);
            $itemId = $params['inventory_item_ids'] ?? null;
            $locId = $params['location_ids'] ?? '101';
            $mapping = $itemId ? ProductMarketplaceMapping::where('shopify_inventory_item_id', (string) $itemId)->first() : null;
            $qty = $mapping && $mapping->quantity !== null ? (int) $mapping->quantity : 10;
            return Http::response([
                'inventory_levels' => [
                    ['inventory_item_id' => $itemId, 'location_id' => $locId, 'available' => $qty],
                ],
            ], 200);
        }

        return Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200);
    });

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('domain')->nullable();
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->integer('quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }
});

// A. Valid JWT, no ?shop
it('Test A: Valid Shopify App Bridge Bearer token without query param creates durable operation', function () {
    $shop = Shop::create([
        'shop'                    => 'store-a.myshopify.com',
        'domain'                  => 'store-a.myshopify.com',
        'shop_name'               => 'Store A',
        'email'                   => 'store-a@example.com',
        'access_token'            => 'shpat_token_a',
        'is_active'               => 1,
        'selected_location_index' => 0,
        'shopify_locations'       => [['id' => '101', 'name' => 'Location 1', 'active' => true]],
    ]);

    $validator = Mockery::mock(ShopifySessionTokenValidator::class);
    $validator->shouldReceive('validate')->with('valid-bearer-a')->andReturn([
        'shop'       => 'store-a.myshopify.com',
        'shop_model' => $shop,
        'payload'    => ['dest' => 'https://store-a.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-bearer-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_100',
        'quantity'          => 25,
    ]);

    $response->assertOk();
    $opId = $response->json('operation_id');
    expect($opId)->not->toBeNull();

    $op = InventorySyncOperation::find($opId);
    expect($op->shop_id)->toBe($shop->id)
        ->and($op->shopify_inventory_item_id)->toBe('item_100')
        ->and($op->desired_quantity)->toBe(25);
});

// B. Valid session, no ?shop
it('Test B: Valid authenticated Laravel session without query param creates durable operation', function () {
    $shop = Shop::create([
        'shop'                    => 'store-b.myshopify.com',
        'domain'                  => 'store-b.myshopify.com',
        'shop_name'               => 'Store B',
        'email'                   => 'store-b@example.com',
        'access_token'            => 'shpat_token_b',
        'is_active'               => 1,
        'selected_location_index' => 0,
        'shopify_locations'       => [['id' => '201', 'name' => 'Loc B', 'active' => true]],
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ])->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_200',
        'quantity'          => 30,
    ]);

    $response->assertOk();
    $opId = $response->json('operation_id');
    $op = InventorySyncOperation::find($opId);
    expect($op->shop_id)->toBe($shop->id);
});

// C. Spoofed shop
it('Test C: Spoofed shop query parameter is ignored and authenticated shop remains authoritative', function () {
    $shopA = Shop::create([
        'shop'                    => 'store-auth-a.myshopify.com',
        'domain'                  => 'store-auth-a.myshopify.com',
        'shop_name'               => 'Store Auth A',
        'email'                   => 'autha@example.com',
        'access_token'            => 'shpat_token_a',
        'is_active'               => 1,
        'selected_location_index' => 0,
        'shopify_locations'       => [['id' => '101', 'name' => 'Loc A', 'active' => true]],
    ]);

    $shopB = Shop::create([
        'shop'                    => 'store-victim-b.myshopify.com',
        'domain'                  => 'store-victim-b.myshopify.com',
        'shop_name'               => 'Store Victim B',
        'email'                   => 'victimb@example.com',
        'access_token'            => 'shpat_token_b',
        'is_active'               => 1,
        'selected_location_index' => 0,
        'shopify_locations'       => [['id' => '201', 'name' => 'Loc B', 'active' => true]],
    ]);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'                   => $shopB->id,
        'shopify_inventory_item_id' => 'item_victim',
        'amazon_sku'                => 'SKU-VICTIM',
        'quantity'                  => 40,
    ]);

    $validator = Mockery::mock(ShopifySessionTokenValidator::class);
    $validator->shouldReceive('validate')->with('token-a')->andReturn([
        'shop'       => 'store-auth-a.myshopify.com',
        'shop_model' => $shopA,
        'payload'    => ['dest' => 'https://store-auth-a.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'shop'              => 'store-victim-b.myshopify.com',
        'inventory_item_id' => 'item_victim',
        'quantity'          => 0,
    ]);

    $response->assertOk();
    $op = InventorySyncOperation::find($response->json('operation_id'));
    expect($op->shop_id)->toBe($shopA->id)
        ->and($mappingB->fresh()->quantity)->toBe(40);
});

// D. Session expired + valid App Bridge token
it('Test D: Expired session with valid App Bridge Bearer token re-establishes session and succeeds', function () {
    $shop = Shop::create([
        'shop'                    => 'store-d.myshopify.com',
        'domain'                  => 'store-d.myshopify.com',
        'shop_name'               => 'Store D',
        'email'                   => 'stored@example.com',
        'access_token'            => 'shpat_token_d',
        'is_active'               => 1,
        'selected_location_index' => 0,
        'shopify_locations'       => [['id' => '401', 'name' => 'Loc D', 'active' => true]],
    ]);

    $validator = Mockery::mock(ShopifySessionTokenValidator::class);
    $validator->shouldReceive('validate')->with('valid-token-d')->andReturn([
        'shop'       => 'store-d.myshopify.com',
        'shop_model' => $shop,
        'payload'    => ['dest' => 'https://store-d.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $response = $this->flushSession()->withHeaders([
        'Authorization' => 'Bearer valid-token-d',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_d',
        'quantity'          => 12,
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue();
});

// E. Session expired + missing token
it('Test E: Missing session and missing token returns 401 with retry header', function () {
    $this->defaultHeaders = [];
    $response = $this->flushSession()->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_unauth',
        'quantity'          => 10,
    ]);

    $response->assertStatus(401);
    expect($response->headers->get('X-Shopify-Retry-Invalid-Session-Request'))->toBe('1');
});

// G. Unactivated shop AJAX
it('Test G: Unactivated shop making AJAX request receives structured JSON 403 instead of HTML redirect', function () {
    $unactivatedShop = Shop::create([
        'shop'         => 'unactivated.myshopify.com',
        'domain'       => 'unactivated.myshopify.com',
        'shop_name'    => null,
        'email'        => null,
        'access_token' => 'shpat_unactivated',
        'is_active'    => 1,
    ]);

    $validator = Mockery::mock(ShopifySessionTokenValidator::class);
    $validator->shouldReceive('validate')->with('token-unactivated')->andReturn([
        'shop'       => 'unactivated.myshopify.com',
        'shop_model' => $unactivatedShop,
        'payload'    => ['dest' => 'https://unactivated.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $this->defaultHeaders = [];
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-unactivated',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_unact',
        'quantity'          => 5,
    ]);

    $response->assertStatus(403);
    expect($response->json('code'))->toBe('SHOP_ACTIVATION_REQUIRED')
        ->and($response->json('success'))->toBeFalse();
});

// H. Missing location self-healing
it('Test H: Shop with missing locations self-heals by refreshing locations via ShopifyService', function () {
    $shop = Shop::create([
        'shop'              => 'self-heal.myshopify.com',
        'domain'            => 'self-heal.myshopify.com',
        'shop_name'         => 'Self Heal Store',
        'email'             => 'selfheal@example.com',
        'access_token'      => 'shpat_self_heal',
        'is_active'         => 1,
        'shopify_locations' => null,
    ]);

    $validator = Mockery::mock(ShopifySessionTokenValidator::class);
    $validator->shouldReceive('validate')->with('token-self-heal')->andReturn([
        'shop'       => 'self-heal.myshopify.com',
        'shop_model' => $shop,
        'payload'    => ['dest' => 'https://self-heal.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $this->defaultHeaders = [];
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-self-heal',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_heal',
        'quantity'          => 18,
    ]);

    $response->assertOk();
    $op = InventorySyncOperation::find($response->json('operation_id'));
    expect($op->shopify_location_id)->toBe('8888')
        ->and($shop->fresh()->shopify_locations)->toHaveCount(1);
});

// I. Location refresh failure
it('Test I: Location refresh failure returns structured 422 JSON error and does not mutate inventory', function () {
    $shop = Shop::create([
        'shop'              => 'fail-loc.myshopify.com',
        'domain'            => 'fail-loc.myshopify.com',
        'shop_name'         => 'Fail Loc Store',
        'email'             => 'failloc@example.com',
        'access_token'      => 'shpat_fail_loc',
        'is_active'         => 1,
        'shopify_locations' => null,
    ]);

    $validator = Mockery::mock(ShopifySessionTokenValidator::class);
    $validator->shouldReceive('validate')->with('token-fail-loc')->andReturn([
        'shop'       => 'fail-loc.myshopify.com',
        'shop_model' => $shop,
        'payload'    => ['dest' => 'https://fail-loc.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $this->defaultHeaders = [];
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-fail-loc',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_fail',
        'quantity'          => 18,
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toBe('No Shopify location found for this store.')
        ->and(InventorySyncOperation::count())->toBe(0);
});

// J. Legacy endpoint without ?shop
it('Test J: Legacy endpoint /amazon/test-update uses verified shop without requiring query param', function () {
    $shop = Shop::create([
        'shop'         => 'legacy-store.myshopify.com',
        'domain'       => 'legacy-store.myshopify.com',
        'shop_name'    => 'Legacy Store',
        'email'        => 'legacy@example.com',
        'access_token' => 'shpat_legacy',
        'is_active'    => 1,
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-LEGACY', 15)
        ->once()
        ->andReturn(['success' => true]);
    app()->instance(AmazonService::class, $amazonMock);

    $this->defaultHeaders = [];
    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ])->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/amazon/test-update/SKU-LEGACY', [
        'quantity' => 15,
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue();
});

// K. Cross-tenant legacy endpoint
it('Test K: Shop A cannot access Shop B via legacy endpoints by passing shop query parameter', function () {
    $shopA = Shop::create([
        'shop'         => 'store-legacy-a.myshopify.com',
        'domain'       => 'store-legacy-a.myshopify.com',
        'shop_name'    => 'Store Legacy A',
        'email'        => 'legacya@example.com',
        'access_token' => 'shpat_leg_a',
        'is_active'    => 1,
    ]);

    $shopB = Shop::create([
        'shop'         => 'store-legacy-b.myshopify.com',
        'domain'       => 'store-legacy-b.myshopify.com',
        'shop_name'    => 'Store Legacy B',
        'email'        => 'legacyb@example.com',
        'access_token' => 'shpat_leg_b',
        'is_active'    => 1,
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    // Amazon update MUST be invoked for Shop A (the authenticated tenant), NEVER Shop B
    $amazonMock->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shopA->id), 'SKU-CROSS', 10)
        ->once()
        ->andReturn(['success' => true]);
    app()->instance(AmazonService::class, $amazonMock);

    $this->defaultHeaders = [];
    $response = $this->withSession([
        '_shopify_verified_shop' => $shopA->shop,
        'active_shop'            => $shopA->shop,
        'active_shop_id'         => $shopA->id,
    ])->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/amazon/test-update/SKU-CROSS?shop=store-legacy-b.myshopify.com', [
        'quantity' => 10,
    ]);

    $response->assertOk();
});

// L. Queue worker durable execution
it('Test L: ProcessInventoryUpdateJob executes without HTTP request session using operation shop_id', function () {
    $shop = Shop::create([
        'shop'                    => 'worker-store.myshopify.com',
        'domain'                  => 'worker-store.myshopify.com',
        'shop_name'               => 'Worker Store',
        'email'                   => 'worker@example.com',
        'access_token'            => 'shpat_worker',
        'is_active'               => 1,
        'selected_location_index' => 0,
        'shopify_locations'       => [['id' => '101', 'name' => 'Loc 101', 'active' => true]],
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_worker',
        'amazon_sku'                => 'SKU-WORKER',
        'quantity'                  => 10,
        'inventory_version'         => 1,
    ]);

    $op = InventorySyncOperation::create([
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_worker',
        'shopify_location_id'        => '101',
        'amazon_sku'                 => 'SKU-WORKER',
        'desired_quantity'           => 25,
        'baseline_quantity'          => 10,
        'expected_inventory_version' => 1,
        'source'                     => 'manual_ui',
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-WORKER', 25, Mockery::any())
        ->once()
        ->andReturn(['submission_id' => 'sub_123']);
    app()->instance(AmazonService::class, $amazonMock);

    // Run job in isolation without session
    $job = new ProcessInventoryUpdateJob($op->id);
    $job->handle($amazonMock);

    $op->refresh();
    expect($op->status)->toBe('awaiting_verification')
        ->and($mapping->fresh()->quantity)->toBe(25);
});

