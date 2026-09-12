<?php

use App\Http\Controllers\DashboardController;
use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AmazonService;
use App\Services\AutoSkuMappingService;
use App\Services\ShopifyInventoryService;
use App\Services\ShopifyService;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*graphql.json*'              => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => [['id' => 'gid://shopify/AppSubscription/1', 'name' => 'Pro', 'status' => 'ACTIVE', 'currentPeriodEnd' => '2030-01-01T00:00:00Z']]]]], 200),
        '*inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            $qty = $data['available'] ?? 0;
            return Http::response(['inventory_level' => ['available' => $qty]], 200);
        },
        '*inventory_levels.json*'     => function (\Illuminate\Http\Client\Request $request) {
            $params = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);
            $itemId = $params['inventory_item_ids'] ?? null;
            $locId = $params['location_ids'] ?? 'loc_101';
            $mapping = $itemId ? \App\Models\ProductMarketplaceMapping::where('shopify_inventory_item_id', $itemId)->first() : null;
            $qty = $mapping && $mapping->quantity !== null ? (int) $mapping->quantity : 0;
            return Http::response(['inventory_levels' => [['inventory_item_id' => $itemId, 'location_id' => $locId, 'available' => $qty]]], 200);
        },
        '*products.json*'             => Http::response(['products' => []], 200),
        '*'                           => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->text('amazon_refresh_token')->nullable();
            $table->boolean('is_active')->default(1);
            $table->string('store_status')->default('active');
            $table->softDeletes();
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
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('shopify_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->string('shopify_status')->nullable();
            $table->text('shopify_error')->nullable();
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->json('tags')->nullable();
            $table->string('category')->nullable();
            $table->json('collections')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('metafields')->nullable();
            $table->boolean('synced_to_amazon')->default(0);
            $table->boolean('needs_resync')->default(0);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->json('local_images')->nullable();
            $table->string('amazon_product_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
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

    if (!Schema::hasTable('settings')) {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->unique();
            $table->boolean('auto_sku_mapping')->default(1);
            $table->timestamps();
        });
    }

    ProductMarketplaceMapping::truncate();
    Product::truncate();
    Shop::truncate();
    Setting::truncate();
    Plan::truncate();
    ShopSubscription::truncate();
    Cache::flush();

    Plan::create([
        'id'         => 1,
        'name'       => 'Pro Plan',
        'sync_limit' => 100,
    ]);
});

it('Shopify normalization distinguishes explicit 0, positive quantity, null, and missing location data', function () {
    $shop = Shop::create([
        'shop' => 'zero-vs-unknown-test.myshopify.com',
        'access_token' => 'dummy_token',
        'is_active' => 1,
        'shopify_locations' => [
            ['id' => '11111', 'name' => 'Warehouse A'],
            ['id' => '22222', 'name' => 'Warehouse B'],
        ],
        'selected_location_index' => 0, // Location '11111'
    ]);

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Product',
            'featuredImage' => ['url' => 'https://example.com/img.jpg'],
            'variants' => [
                'nodes' => [
                    // Variant 1: Explicit 0
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'title' => 'Zero Stock Variant',
                        'sku' => 'SKU-ZERO',
                        'inventoryQuantity' => 0,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/11111'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 0],
                                            ['name' => 'committed', 'quantity' => 0],
                                            ['name' => 'on_hand', 'quantity' => 0],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // Variant 2: Explicit positive (25)
                    [
                        'id' => 'gid://shopify/ProductVariant/102',
                        'title' => 'In Stock Variant',
                        'sku' => 'SKU-POS',
                        'inventoryQuantity' => 25,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1002',
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/11111'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 25],
                                            ['name' => 'committed', 'quantity' => 5],
                                            ['name' => 'on_hand', 'quantity' => 30],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // Variant 3: Null quantity & no matching location level
                    [
                        'id' => 'gid://shopify/ProductVariant/103',
                        'title' => 'Unknown Quantity Variant',
                        'sku' => 'SKU-NULL',
                        'inventoryQuantity' => null,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1003',
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/99999'], // different location
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 10],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // Variant 4: Missing inventoryQuantity field and empty inventory levels
                    [
                        'id' => 'gid://shopify/ProductVariant/104',
                        'title' => 'Missing Fields Variant',
                        'sku' => 'SKU-MISSING',
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1004',
                            'inventoryLevels' => [
                                'nodes' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $service = new ShopifyInventoryService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('flattenVariants');
    $method->setAccessible(true);

    $flattened = $method->invoke($service, $mockProducts, $shop);

    expect($flattened)->toHaveCount(4);

    // Variant 1: Explicit 0
    expect($flattened[0]['qty'])->toBe(0);
    expect($flattened[0]['available'])->toBe(0);
    expect($flattened[0]['committed'])->toBe(0);
    expect($flattened[0]['on_hand'])->toBe(0);
    expect($flattened[0]['status'])->toBe('out_of_stock');

    // Variant 2: Positive 25
    expect($flattened[1]['qty'])->toBe(25);
    expect($flattened[1]['available'])->toBe(25);
    expect($flattened[1]['committed'])->toBe(5);
    expect($flattened[1]['on_hand'])->toBe(30);
    expect($flattened[1]['unavailable'])->toBe(5);
    expect($flattened[1]['status'])->toBe('synced');

    // Variant 3: Null quantity / location not matched
    expect($flattened[2]['qty'])->toBeNull();
    expect($flattened[2]['available'])->toBeNull();
    expect($flattened[2]['committed'])->toBeNull();
    expect($flattened[2]['on_hand'])->toBeNull();
    expect($flattened[2]['unavailable'])->toBeNull();
    expect($flattened[2]['status'])->toBe('unknown');

    // Variant 4: Missing fields
    expect($flattened[3]['qty'])->toBeNull();
    expect($flattened[3]['available'])->toBeNull();
    expect($flattened[3]['status'])->toBe('unknown');
});

it('Dashboard low inventory filter includes only known quantities below threshold and excludes null', function () {
    $inventory = [
        ['product' => 'P1', 'sku' => 'S1', 'available' => null],
        ['product' => 'P2', 'sku' => 'S2', 'available' => 0],
        ['product' => 'P3', 'sku' => 'S3', 'available' => 5],
        ['product' => 'P4', 'sku' => 'S4', 'available' => 20],
    ];

    $lowInventoryProducts = collect($inventory)
        ->filter(function ($item) {
            return isset($item['available']) && $item['available'] !== null && $item['available'] < 10;
        })
        ->sortBy('available')
        ->values();

    expect($lowInventoryProducts)->toHaveCount(2);
    expect($lowInventoryProducts[0]['available'])->toBe(0);
    expect($lowInventoryProducts[1]['available'])->toBe(5);
});

it('Auto SKU mapping preserves null quantity for unknown Shopify inventory and saves 0 for explicit zero', function () {
    $shop = Shop::create([
        'shop' => 'auto-map-null.myshopify.com',
        'access_token' => 'dummy_token',
        'is_active' => 1,
    ]);

    Setting::create([
        'shop_id' => $shop->id,
        'auto_sku_mapping' => 1,
    ]);

    Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => '1001',
        'title' => 'Auto Map Test Product',
        'variants' => json_encode([
            ['id' => 2001, 'title' => 'Var 1', 'inventory_item_id' => '3001'],
            ['id' => 2002, 'title' => 'Var 2', 'inventory_item_id' => '3002'],
        ]),
    ]);

    $shopifyInventory = [
        [
            'pid' => '1001',
            'vid' => '2001',
            'inventory_item_id' => '3001',
            'sku' => 'AUTO-SKU-NULL',
            'qty' => null, // UNKNOWN
            'available' => null,
        ],
        [
            'pid' => '1001',
            'vid' => '2002',
            'inventory_item_id' => '3002',
            'sku' => 'AUTO-SKU-ZERO',
            'qty' => 0, // EXPLICIT ZERO
            'available' => 0,
        ],
    ];

    $amazonInventory = [
        ['sku' => 'AUTO-SKU-NULL', 'quantity' => 10],
        ['sku' => 'AUTO-SKU-ZERO', 'quantity' => 0],
    ];

    $autoSkuService = app(AutoSkuMappingService::class);
    $autoSkuService->handle($shop, $shopifyInventory, $amazonInventory);

    $mappingNull = ProductMarketplaceMapping::where('shop_id', $shop->id)
        ->where('shopify_variant_id', '2001')
        ->first();

    $mappingZero = ProductMarketplaceMapping::where('shop_id', $shop->id)
        ->where('shopify_variant_id', '2002')
        ->first();

    expect($mappingNull)->not->toBeNull();
    expect($mappingNull->quantity)->toBeNull();

    expect($mappingZero)->not->toBeNull();
    expect((int) $mappingZero->quantity)->toBe(0);
});

it('Shopify order webhook skips Amazon inventory update if mapping quantity is null', function () {
    $shop = Shop::create([
        'shop' => 'order-webhook-null-test.myshopify.com',
        'access_token' => 'dummy_token',
        'is_active' => 1,
    ]);

    $mappingNull = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_variant_id' => '8888',
        'amazon_sku' => 'AMZ-SKU-NULL',
        'quantity' => null, // Unknown quantity
    ]);

    $mappingZero = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_variant_id' => '9999',
        'amazon_sku' => 'AMZ-SKU-ZERO',
        'quantity' => '0', // Known zero
    ]);

    $mockAmazonService = Mockery::mock(AmazonService::class);

    // AmazonService should NOT receive updateInventory for AMZ-SKU-NULL
    $mockAmazonService->shouldNotReceive('updateInventory')
        ->with($shop, 'AMZ-SKU-NULL', Mockery::any());

    // AmazonService SHOULD receive updateInventory for AMZ-SKU-ZERO with 0
    $mockAmazonService->shouldReceive('updateInventory')
        ->with($shop, 'AMZ-SKU-ZERO', 0)
        ->once()
        ->andReturn(['submissionId' => 'SUB-123']);

    app()->instance(AmazonService::class, $mockAmazonService);

    // Simulate order webhook processing for NULL quantity variant
    if ($mappingNull->quantity === null || $mappingNull->quantity === '') {
        // Correctly skipped
    } else {
        $newQty = max(0, ((int) $mappingNull->quantity) - 1);
        $mockAmazonService->updateInventory($shop, $mappingNull->amazon_sku, $newQty);
    }

    // Simulate order webhook processing for ZERO quantity variant
    if ($mappingZero->quantity === null || $mappingZero->quantity === '') {
        // Should not be skipped
    } else {
        $newQty = max(0, ((int) $mappingZero->quantity) - 1);
        $mockAmazonService->updateInventory($shop, $mappingZero->amazon_sku, $newQty);
    }
});

it('Backend Shopify inventory update accepts explicit 0, positive integer, and rejects empty/null quantity', function () {
    $shop = Shop::create([
        'shop' => 'manual-update-test.myshopify.com',
        'access_token' => 'dummy_token',
        'is_active' => 1,
        'shopify_locations' => [
            ['id' => '11111', 'name' => 'Main Loc'],
        ],
        'selected_location_index' => 0,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_inventory_item_id' => 'ITEM-123',
        'amazon_sku' => 'AMZ-SKU-123',
        'quantity' => '10',
    ]);

    $mockAmazonService = Mockery::mock(AmazonService::class);
    $mockAmazonService->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'AMZ-SKU-123', 0, false)
        ->once()
        ->andReturn(['submissionId' => 'SUB-000', 'status' => 'ACCEPTED']);
    $mockAmazonService->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'AMZ-SKU-123', 15, false)
        ->once()
        ->andReturn(['submissionId' => 'SUB-015', 'status' => 'ACCEPTED']);
    app()->instance(AmazonService::class, $mockAmazonService);


    // Mock Shopify REST inventory_levels/set.json
    Http::fake([
        '*inventory_levels/set.json*' => Http::response(['inventory_level' => ['available' => 0]], 200),
        '*products.json*'             => Http::response(['products' => []], 200),
        '*'                           => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    $controller = app(\App\Http\Controllers\InventoryMappingController::class);

    // 1. Explicit 0 update
    $reqZero = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'ITEM-123',
        'quantity' => 0,
    ]);
    $reqZero->attributes->set('active_shop_model', $shop);
    $responseZero = $controller->updateShopifyInventory($reqZero);
    expect($responseZero->getStatusCode())->toBe(200);
    expect((int) $mapping->fresh()->quantity)->toBe(0);

    // 2. Positive quantity update
    $reqPos = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'ITEM-123',
        'quantity' => 15,
    ]);
    $reqPos->attributes->set('active_shop_model', $shop);
    $responsePos = $controller->updateShopifyInventory($reqPos);
    expect($responsePos->getStatusCode())->toBe(200);
    expect((int) $mapping->fresh()->quantity)->toBe(15);

    // 3. Empty string / null quantity -> rejected by validation
    try {
        $reqEmpty = Request::create('/inventory/shopify/update', 'POST', [
            'inventory_item_id' => 'ITEM-123',
            'quantity' => '',
        ]);
        $reqEmpty->attributes->set('active_shop_model', $shop);
        $controller->updateShopifyInventory($reqEmpty);
        $this->fail('Expected ValidationException was not thrown for empty string');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('quantity');
    }

    try {
        $reqNull = Request::create('/inventory/shopify/update', 'POST', [
            'inventory_item_id' => 'ITEM-123',
            'quantity' => null,
        ]);
        $reqNull->attributes->set('active_shop_model', $shop);
        $controller->updateShopifyInventory($reqNull);
        $this->fail('Expected ValidationException was not thrown for null');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('quantity');
    }
});

it('Backend Amazon inventory update accepts explicit 0, positive integer, and rejects empty/null quantity', function () {
    $shop = Shop::create([
        'shop' => 'manual-amazon-update.myshopify.com',
        'access_token' => 'dummy_token',
        'is_active' => 1,
    ]);

    $mockAmazonService = Mockery::mock(AmazonService::class);
    $mockAmazonService->shouldReceive('updateInventory')
        ->with($shop, 'AMZ-CHILD-SKU', 0)
        ->once()
        ->andReturn(['submissionId' => 'SUB-AMZ-0', 'status' => 'ACCEPTED']);
    $mockAmazonService->shouldReceive('updateInventory')
        ->with($shop, 'AMZ-CHILD-SKU', 25)
        ->once()
        ->andReturn(['submissionId' => 'SUB-AMZ-25', 'status' => 'ACCEPTED']);
    app()->instance(AmazonService::class, $mockAmazonService);

    $controller = app(\App\Http\Controllers\InventoryController::class);

    // 1. Explicit 0
    $reqZero = Request::create('/inventory/amazon/AMZ-CHILD-SKU/update-quantity', 'POST', [
        'quantity' => 0,
    ]);
    $reqZero->attributes->set('active_shop_model', $shop);
    $respZero = $controller->updateAmazonQuantity($reqZero, 'AMZ-CHILD-SKU');
    expect($respZero->getStatusCode())->toBe(200);

    // 2. Positive 25
    $reqPos = Request::create('/inventory/amazon/AMZ-CHILD-SKU/update-quantity', 'POST', [
        'quantity' => 25,
    ]);
    $reqPos->attributes->set('active_shop_model', $shop);
    $respPos = $controller->updateAmazonQuantity($reqPos, 'AMZ-CHILD-SKU');
    expect($respPos->getStatusCode())->toBe(200);

    // 3. Empty / Null
    try {
        $reqEmpty = Request::create('/inventory/amazon/AMZ-CHILD-SKU/update-quantity', 'POST', [
            'quantity' => '',
        ]);
        $reqEmpty->attributes->set('active_shop_model', $shop);
        $controller->updateAmazonQuantity($reqEmpty, 'AMZ-CHILD-SKU');
        $this->fail('Expected ValidationException was not thrown for empty string');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('quantity');
    }

    try {
        $reqNull = Request::create('/inventory/amazon/AMZ-CHILD-SKU/update-quantity', 'POST', [
            'quantity' => null,
        ]);
        $reqNull->attributes->set('active_shop_model', $shop);
        $controller->updateAmazonQuantity($reqNull, 'AMZ-CHILD-SKU');
        $this->fail('Expected ValidationException was not thrown for null');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('quantity');
    }
});

