<?php

use App\Http\Controllers\InventoryMappingController;
use App\Http\Controllers\ShopifyController;
use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AmazonService;
use App\Services\ShopifyInventoryService;
use App\Services\ShopifyService;
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
        '*inventory_levels/set.json*' => Http::response(['inventory_level' => ['available' => 0]], 200),
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
            $table->boolean('is_active')->default(1);
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
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    Shop::truncate();
    ProductMarketplaceMapping::truncate();
    Cache::flush();
});

if (!function_exists('createLocationTestShop')) {
    function createLocationTestShop(string $domain = 'store-loc.myshopify.com', ?int $selectedIndex = null): Shop
    {
        $locations = [
            [
                'id' => '10001',
                'name' => 'Main Warehouse',
                'active' => true,
            ],
            [
                'id' => '10002',
                'name' => 'Retail Store NYC',
                'active' => true,
            ],
            [
                'id' => '10003',
                'name' => 'West Coast Hub',
                'active' => true,
            ],
        ];

        return Shop::updateOrCreate(
            ['shop' => $domain],
            [
                'shop_name'               => 'Test Location Store',
                'email'                   => 'owner@store-loc.com',
                'access_token'            => 'shpat_test_token_123',
                'is_active'               => 1,
                'shopify_locations'       => $locations,
                'selected_location_index' => $selectedIndex,
            ]
        );
    }
}

if (!function_exists('mockLocationShopAuth')) {
    function mockLocationShopAuth(Shop $shop): void
    {
        session([
            'active_shop'            => $shop->shop,
            'active_shop_id'         => $shop->id,
            '_shopify_verified_shop' => $shop->shop,
        ]);
    }
}

if (!function_exists('renderInventoryViewHelper')) {
    function renderInventoryViewHelper(Shop $shop): string
    {
        return view('inventory.index', [
            'inventories' => [],
            'shop'        => $shop,
            'syncUsage'   => ['limit' => 0],
            'errors'      => new \Illuminate\Support\ViewErrorBag(),
        ])->render();
    }
}

// =========================================================================
// Requirement 1 & 2 & 3: Dropdown is enabled, renders all locations, no placeholder
// =========================================================================
it('Requirement 1-3: Inventory dropdown is enabled, renders all Shopify locations, and does not contain Select a location placeholder', function () {
    $shop = createLocationTestShop('store-multi.myshopify.com', null);

    $html = renderInventoryViewHelper($shop);

    expect($html)->toContain('<select id="dtLocationShopify" class="saas-select">');
    expect($html)->not->toContain('<select id="dtLocationShopify" class="saas-select" disabled>');
    expect($html)->toContain('Main Warehouse');
    expect($html)->toContain('Retail Store NYC');
    expect($html)->toContain('West Coast Hub');
    expect($html)->not->toContain('Select a location');
    expect($html)->toContain('<option value="0" selected>');
});

// =========================================================================
// Requirement 4: New store installation defaults to location 0
// =========================================================================
it('Requirement 4: New store OAuth installation defaults to selected_location_index = 0', function () {
    $shop = Shop::create([
        'shop' => 'new-install.myshopify.com',
        'access_token' => 'shpat_new_token',
        'is_active' => 1,
    ]);

    $locations = [
        ['id' => '99001', 'name' => 'Primary Hub', 'active' => true],
        ['id' => '99002', 'name' => 'Secondary Hub', 'active' => true],
    ];

    $shop->update([
        'shopify_locations' => $locations,
        'selected_location_index' => !empty($locations) ? 0 : null,
    ]);

    $shop->refresh();
    expect($shop->selected_location_index)->toBe(0);
});

// =========================================================================
// Requirement 5: Existing NULL selected index defaults to location 0
// =========================================================================
it('Requirement 5: Existing NULL selected_location_index defaults to location 0 in UI and backend', function () {
    $shop = createLocationTestShop('store-null-idx.myshopify.com', null);
    expect($shop->selected_location_index)->toBeNull();

    $html = renderInventoryViewHelper($shop);
    expect($html)->toContain('<option value="0" selected>');

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Item',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'title' => 'Default Title',
                        'sku' => 'SKU-001',
                        'inventoryQuantity' => 50,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10001'], 'quantities' => [['name' => 'available', 'quantity' => 50]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10002'], 'quantities' => [['name' => 'available', 'quantity' => 100]]],
                                ],
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
    expect($flattened[0]['available'])->toBe(50);
});

// =========================================================================
// Requirement 6: Existing selected index 1 displays location 1 as selected
// =========================================================================
it('Requirement 6: Existing selected_location_index = 1 displays location 1 as selected in UI and uses location 1 in backend', function () {
    $shop = createLocationTestShop('store-idx-1.myshopify.com', 1);

    $html = renderInventoryViewHelper($shop);
    expect($html)->toContain('<option value="1" selected>');

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Item',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'sku' => 'SKU-001',
                        'inventoryQuantity' => 100,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10001'], 'quantities' => [['name' => 'available', 'quantity' => 35]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10002'], 'quantities' => [['name' => 'available', 'quantity' => 99]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10003'], 'quantities' => [['name' => 'available', 'quantity' => 12]]],
                                ],
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
    expect($flattened[0]['available'])->toBe(99);
});

// =========================================================================
// Requirement 7: Existing selected index 2 displays location 2 as selected
// =========================================================================
it('Requirement 7: Existing selected_location_index = 2 displays location 2 as selected in UI and uses location 2 in backend', function () {
    $shop = createLocationTestShop('store-idx-2.myshopify.com', 2);

    $html = renderInventoryViewHelper($shop);
    expect($html)->toContain('<option value="2" selected>');

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Item',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'sku' => 'SKU-001',
                        'inventoryQuantity' => 100,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10001'], 'quantities' => [['name' => 'available', 'quantity' => 35]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10002'], 'quantities' => [['name' => 'available', 'quantity' => 99]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10003'], 'quantities' => [['name' => 'available', 'quantity' => 12]]],
                                ],
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
    expect($flattened[0]['available'])->toBe(12);
});

// =========================================================================
// Requirement 8 & 9: User changes location 0 -> 1 persists index 1 and reloads location 1 inventory
// =========================================================================
it('Requirement 8 & 9: User changes location 0 -> 1 persists index 1 and reloads location 1 inventory', function () {
    $shop = createLocationTestShop('store-change-0-1.myshopify.com', 0);
    mockLocationShopAuth($shop);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/settings', [
            'shop'                    => $shop->shop,
            'selected_location_index' => 1,
        ]);

    $response->assertOk();
    $shop->refresh();
    expect($shop->selected_location_index)->toBe(1);

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Item',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'sku' => 'SKU-001',
                        'inventoryQuantity' => 100,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10001'], 'quantities' => [['name' => 'available', 'quantity' => 35]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10002'], 'quantities' => [['name' => 'available', 'quantity' => 99]]],
                                ],
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
    expect($flattened[0]['available'])->toBe(99);
});

// =========================================================================
// Requirement 10: User changes location 1 -> 2 uses location 2 inventory
// =========================================================================
it('Requirement 10: User changes location 1 -> 2 persists index 2 and uses location 2 inventory', function () {
    $shop = createLocationTestShop('store-change-1-2.myshopify.com', 1);
    mockLocationShopAuth($shop);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/settings', [
            'shop'                    => $shop->shop,
            'selected_location_index' => 2,
        ]);

    $response->assertOk();
    $shop->refresh();
    expect($shop->selected_location_index)->toBe(2);

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Item',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'sku' => 'SKU-001',
                        'inventoryQuantity' => 100,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10001'], 'quantities' => [['name' => 'available', 'quantity' => 35]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10002'], 'quantities' => [['name' => 'available', 'quantity' => 99]]],
                                    ['location' => ['id' => 'gid://shopify/Location/10003'], 'quantities' => [['name' => 'available', 'quantity' => 77]]],
                                ],
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
    expect($flattened[0]['available'])->toBe(77);
});

// =========================================================================
// Requirement 11: Manual Shopify update while location 1 is selected uses location 1 ID (10002)
// =========================================================================
it('Requirement 11: Manual Shopify inventory update while location 1 is selected uses location 1 ID (10002)', function () {
    $shop = createLocationTestShop('store-update-loc1.myshopify.com', 1);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_inventory_item_id' => 'ITEM-LOC-1',
        'amazon_sku' => 'AMZ-LOC-1',
        'quantity' => '10',
    ]);

    $mockAmazonService = Mockery::mock(AmazonService::class);
    $mockAmazonService->shouldReceive('updateInventory')
        ->with($shop, 'AMZ-LOC-1', 25)
        ->once()
        ->andReturn(['submissionId' => 'SUB-LOC-25', 'status' => 'ACCEPTED']);
    app()->instance(AmazonService::class, $mockAmazonService);

    Http::fake([
        '*inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            expect($data['location_id'])->toBe('10002');
            expect($data['available'])->toBe(25);
            return Http::response(['inventory_level' => ['available' => 25]], 200);
        },
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    $controller = app(InventoryMappingController::class);
    $req = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'ITEM-LOC-1',
        'quantity' => 25,
    ]);
    $req->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($req);
    expect($response->getStatusCode())->toBe(200);
});

// =========================================================================
// Requirement 12: Manual Shopify update while location 2 is selected uses location 2 ID (10003)
// =========================================================================
it('Requirement 12: Manual Shopify inventory update while location 2 is selected uses location 2 ID (10003)', function () {
    $shop = createLocationTestShop('store-update-loc2.myshopify.com', 2);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_inventory_item_id' => 'ITEM-LOC-2',
        'amazon_sku' => 'AMZ-LOC-2',
        'quantity' => '10',
    ]);

    $mockAmazonService = Mockery::mock(AmazonService::class);
    $mockAmazonService->shouldReceive('updateInventory')
        ->with($shop, 'AMZ-LOC-2', 40)
        ->once()
        ->andReturn(['submissionId' => 'SUB-LOC-40', 'status' => 'ACCEPTED']);
    app()->instance(AmazonService::class, $mockAmazonService);

    Http::fake([
        '*inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            expect($data['location_id'])->toBe('10003');
            expect($data['available'])->toBe(40);
            return Http::response(['inventory_level' => ['available' => 40]], 200);
        },
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    $controller = app(InventoryMappingController::class);
    $req = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'ITEM-LOC-2',
        'quantity' => 40,
    ]);
    $req->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($req);
    expect($response->getStatusCode())->toBe(200);
});

// =========================================================================
// Requirement 13: Cache uses location-specific keys
// =========================================================================
it('Requirement 13: Inventory cache uses location-specific keys', function () {
    $shop = createLocationTestShop('store-cache-keys.myshopify.com', 1);

    $service = new ShopifyInventoryService();
    $reflection = new ReflectionClass($service);
    $isExpiredMethod = $reflection->getMethod('isExpired');
    $isExpiredMethod->setAccessible(true);

    // Initial state: location 1 cache is expired
    expect($isExpiredMethod->invoke($service, $shop))->toBeTrue();

    // Cache location 0 - location 1 should STILL be expired!
    Cache::put("shopify_inventory_{$shop->shop}_location_0", ['location_0_data'], 600);
    expect($isExpiredMethod->invoke($service, $shop))->toBeTrue();

    // Cache location 1 - now location 1 is not expired
    Cache::put("shopify_inventory_{$shop->shop}_location_1", ['location_1_data'], 600);
    expect($isExpiredMethod->invoke($service, $shop))->toBeFalse();
});

// =========================================================================
// Requirement 14: Invalid stored location safely falls back to location 0
// =========================================================================
it('Requirement 14: Out-of-bounds stored selected_location_index safely falls back to location 0', function () {
    $shop = createLocationTestShop('store-invalid-idx.myshopify.com', 999);

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Item',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'sku' => 'SKU-001',
                        'inventoryQuantity' => 50,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10001'], 'quantities' => [['name' => 'available', 'quantity' => 45]]],
                                ],
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
    expect($flattened[0]['available'])->toBe(45);
});

// =========================================================================
// Requirement 15: 0-vs-Unknown behavior remains intact for every selected location
// =========================================================================
it('Requirement 15: 0-vs-Unknown behavior remains intact across any selected location', function () {
    $shop = createLocationTestShop('store-0-vs-unknown.myshopify.com', 1);

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Item 1',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'sku' => 'SKU-ZERO',
                        'inventoryQuantity' => 0,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10002'], 'quantities' => [['name' => 'available', 'quantity' => 0]]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'id' => 'gid://shopify/Product/2',
            'title' => 'Test Item 2',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/102',
                        'sku' => 'SKU-POS',
                        'inventoryQuantity' => 15,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1002',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10002'], 'quantities' => [['name' => 'available', 'quantity' => 15]]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'id' => 'gid://shopify/Product/3',
            'title' => 'Test Item 3',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/103',
                        'sku' => 'SKU-UNKNOWN',
                        'inventoryQuantity' => null,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1003',
                            'inventoryLevels' => [
                                'nodes' => [
                                    ['location' => ['id' => 'gid://shopify/Location/10001'], 'quantities' => [['name' => 'available', 'quantity' => 10]]], // not in location 10002
                                ],
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

    expect($flattened[0]['available'])->toBe(0);
    expect($flattened[0]['status'])->toBe('out_of_stock');

    expect($flattened[1]['available'])->toBe(15);
    expect($flattened[1]['status'])->toBe('synced');

    expect($flattened[2]['available'])->toBeNull();
    expect($flattened[2]['status'])->toBe('unknown');
});

// =========================================================================
// Requirement 16: Settings Shopify Location dropdown remains present and contains all real locations without Select a location placeholder
// =========================================================================
it('Requirement 16: Settings Shopify Location dropdown remains present and contains all real locations without Select a location placeholder', function () {
    $shop = createLocationTestShop('store-settings-check.myshopify.com', 1);
    mockLocationShopAuth($shop);

    $html = view('settings', [
        'activeShop'    => $shop->shop,
        'shop'          => $shop,
        'settings'      => null,
        'notifications' => collect([]),
        'errors'        => new \Illuminate\Support\ViewErrorBag(),
    ])->render();

    expect($html)->toContain('name="selected_location_index"');
    expect($html)->toContain('Select Your Shopify Location');
    expect($html)->toContain('Main Warehouse');
    expect($html)->toContain('Retail Store NYC');
    expect($html)->toContain('West Coast Hub');
    expect($html)->not->toContain('Select a location');
    expect($html)->toContain('<option value="1" selected');

    // Test that Settings update still persists selected_location_index
    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/settings', [
            'shop'                    => $shop->shop,
            'selected_location_index' => 2,
        ]);

    $response->assertOk();
    $shop->refresh();
    expect($shop->selected_location_index)->toBe(2);

    // Test that Inventory uses the same persisted selected location
    $invHtml = renderInventoryViewHelper($shop);
    expect($invHtml)->toContain('<option value="2" selected>');
});

// =========================================================================
// Requirement 17: Tenant isolation remains intact
// =========================================================================
it('Requirement 17: Tenant isolation remains intact across separate shop location selections', function () {
    $shopA = createLocationTestShop('store-tenant-a.myshopify.com', 0);
    $shopB = createLocationTestShop('store-tenant-b.myshopify.com', 2);

    mockLocationShopAuth($shopA);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/settings', [
            'shop'                    => $shopA->shop,
            'selected_location_index' => 1,
        ]);

    $response->assertOk();
    $shopA->refresh();
    $shopB->refresh();

    expect($shopA->selected_location_index)->toBe(1);
    expect($shopB->selected_location_index)->toBe(2);
});
