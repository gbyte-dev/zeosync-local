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
// Test 1: Inventory page with multiple Shopify locations
// =========================================================================
it('Test 1: Inventory page with multiple Shopify locations displays only main location without Select a location', function () {
    $shop = createLocationTestShop('store-multi.myshopify.com', null);

    $html = renderInventoryViewHelper($shop);

    expect($html)->toContain('Main Warehouse');
    expect($html)->not->toContain('Select a location');
    expect($html)->not->toContain('Retail Store NYC');
    expect($html)->not->toContain('West Coast Hub');
    expect($html)->toContain('<select id="dtLocationShopify" class="saas-select" disabled>');
    expect($html)->toContain('<option value="0" selected>');
});

// =========================================================================
// Test 2: Inventory page with one Shopify location
// =========================================================================
it('Test 2: Inventory page with one Shopify location displays that location and selects it', function () {
    $shop = Shop::create([
        'shop' => 'store-single.myshopify.com',
        'shopify_locations' => [
            ['id' => '55555', 'name' => 'Solo Flagship Store', 'active' => true],
        ],
        'selected_location_index' => null,
        'is_active' => 1,
    ]);

    $html = renderInventoryViewHelper($shop);

    expect($html)->toContain('Solo Flagship Store');
    expect($html)->not->toContain('Select a location');
    expect($html)->toContain('<option value="0" selected>');
});

// =========================================================================
// Test 3: selected_location_index = NULL, 0, 1, 2 ALL resolve to location 0 in Inventory
// =========================================================================
it('Test 3: selected_location_index = NULL, 0, 1, and 2 ALL resolve to location 0 in Inventory backend', function () {
    $indicesToTest = [null, 0, 1, 2];

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Multi-Location Item',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/101',
                        'title' => 'Default Title',
                        'sku' => 'SKU-001',
                        'inventoryQuantity' => 100,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    // Location 0 (Main Warehouse: 10001) -> 42
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10001'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 42],
                                        ],
                                    ],
                                    // Location 1 (Retail Store NYC: 10002) -> 99
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10002'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 99],
                                        ],
                                    ],
                                    // Location 2 (West Coast Hub: 10003) -> 13
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10003'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 13],
                                        ],
                                    ],
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

    foreach ($indicesToTest as $idx) {
        $shop = createLocationTestShop("store-test-idx-{$idx}.myshopify.com", $idx);
        $flattened = $method->invoke($service, $mockProducts, $shop);

        expect($flattened)->toHaveCount(1);
        // In all cases, must match location 10001 (Main Warehouse) = 42, NEVER 99 or 13!
        expect($flattened[0]['available'])->toBe(42, "Failed for selected_location_index = " . var_export($idx, true));
        expect($flattened[0]['status'])->toBe('synced');
    }
});

// =========================================================================
// Test 4: New Shopify installation OAuth callback stores selected_location_index = 0
// =========================================================================
it('Test 4: New Shopify installation OAuth callback stores selected_location_index = 0', function () {
    $shop = Shop::create([
        'shop' => 'new-install.myshopify.com',
        'access_token' => 'shpat_new_token',
        'is_active' => 1,
    ]);

    // Simulate location save block from ShopifyController::callback
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
// Test 5: No Shopify locations handles safely with Unknown/null
// =========================================================================
it('Test 5: No Shopify locations handles safely with no fake location and inventory Unknown/null', function () {
    $shop = Shop::create([
        'shop' => 'no-loc.myshopify.com',
        'shopify_locations' => [],
        'selected_location_index' => null,
        'is_active' => 1,
    ]);

    $html = renderInventoryViewHelper($shop);
    expect($html)->toContain('No Location Available');

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
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1001',
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
    expect($flattened[0]['available'])->toBeNull();
    expect($flattened[0]['status'])->toBe('unknown');
});

// =========================================================================
// Test 6: Main location quantity = 0 produces available = 0 and out_of_stock
// =========================================================================
it('Test 6: Main location quantity = 0 produces available = 0 and out_of_stock status', function () {
    $shop = createLocationTestShop('store-zero.myshopify.com', 2); // DB has index 2, but must still use index 0!

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Zero',
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
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10001'], // Main location
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 0],
                                        ],
                                    ],
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
});

// =========================================================================
// Test 7: Main location quantity = 25 produces available = 25 and synced
// =========================================================================
it('Test 7: Main location quantity = 25 produces available = 25 and synced status', function () {
    $shop = createLocationTestShop('store-pos.myshopify.com', 1); // DB has index 1, but must still use index 0!

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test Pos',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/102',
                        'sku' => 'SKU-POS',
                        'inventoryQuantity' => 25,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/1002',
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10001'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 25],
                                        ],
                                    ],
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
    expect($flattened[0]['available'])->toBe(25);
    expect($flattened[0]['status'])->toBe('synced');
});

// =========================================================================
// Test 8: Main location has no inventory level produces available = null and unknown
// =========================================================================
it('Test 8: Main location has no inventory level produces available = null and unknown status', function () {
    $shop = createLocationTestShop('store-no-lvl.myshopify.com', null);

    $mockProducts = [
        [
            'id' => 'gid://shopify/Product/1',
            'title' => 'Test No Level',
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/103',
                        'sku' => 'SKU-NO-LVL',
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
                ],
            ],
        ],
    ];

    $service = new ShopifyInventoryService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('flattenVariants');
    $method->setAccessible(true);

    $flattened = $method->invoke($service, $mockProducts, $shop);
    expect($flattened[0]['available'])->toBeNull();
    expect($flattened[0]['status'])->toBe('unknown');
});

// =========================================================================
// Test 9: Manual Shopify inventory update ALWAYS writes to location 0 ID (10001)
// =========================================================================
it('Test 9: Manual Shopify inventory update ALWAYS writes to location 0 ID even if selected_location_index is 2 in DB', function () {
    $shop = createLocationTestShop('store-update-loc.myshopify.com', 2); // DB has index 2 (10003)

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_inventory_item_id' => 'ITEM-LOC-1',
        'amazon_sku' => 'AMZ-LOC-1',
        'quantity' => '10',
    ]);

    $mockAmazonService = Mockery::mock(AmazonService::class);
    $mockAmazonService->shouldReceive('updateInventory')
        ->with($shop, 'AMZ-LOC-1', 12)
        ->once()
        ->andReturn(['submissionId' => 'SUB-LOC-12', 'status' => 'ACCEPTED']);
    app()->instance(AmazonService::class, $mockAmazonService);

    // Verify request made to Shopify rest uses main location ID (10001), NOT 10003!
    Http::fake([
        '*inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            expect($data['location_id'])->toBe('10001');
            expect($data['available'])->toBe(12);
            return Http::response(['inventory_level' => ['available' => 12]], 200);
        },
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    $controller = app(InventoryMappingController::class);
    $req = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'ITEM-LOC-1',
        'quantity' => 12,
    ]);
    $req->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($req);
    expect($response->getStatusCode())->toBe(200);
    expect((int) $mapping->fresh()->quantity)->toBe(12);
});

// =========================================================================
// Test 10: Inventory cache ALWAYS uses location 0 key even when DB has selected_location_index = 1 or 2
// =========================================================================
it('Test 10: Inventory cache ALWAYS uses location 0 key even when DB contains selected_location_index = 2', function () {
    $shop = createLocationTestShop('store-cache-test.myshopify.com', 2);
    expect($shop->selected_location_index)->toBe(2);

    $service = new ShopifyInventoryService();
    $reflection = new ReflectionClass($service);
    $isExpiredMethod = $reflection->getMethod('isExpired');
    $isExpiredMethod->setAccessible(true);

    // Is expired for location 0
    expect($isExpiredMethod->invoke($service, $shop))->toBeTrue();

    // Cache stored for location 0
    Cache::put("shopify_inventory_{$shop->shop}_location_0", ['test_data'], 600);
    expect($isExpiredMethod->invoke($service, $shop))->toBeFalse();

    // Cache stored for location 2 must NOT satisfy location 0
    Cache::flush();
    Cache::put("shopify_inventory_{$shop->shop}_location_2", ['stale_data'], 600);
    expect($isExpiredMethod->invoke($service, $shop))->toBeTrue();
});

// =========================================================================
// Test 11: Settings view and update persist settings location without breaking Inventory
// =========================================================================
it('Test 11: Settings view and update persist settings location while tenant isolation is preserved', function () {
    $shopA = createLocationTestShop('store-sett-a.myshopify.com', 0);
    $shopB = createLocationTestShop('store-sett-b.myshopify.com', 0);

    mockLocationShopAuth($shopA);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/settings', [
        'shop'                    => $shopA->shop,
        'selected_location_index' => 2,
    ]);

    $response->assertOk();
    $shopA->refresh();
    $shopB->refresh();

    expect($shopA->selected_location_index)->toBe(2);
    expect($shopB->selected_location_index)->toBe(0);

    // After setting to 2 in Settings, Inventory view MUST STILL show location 0!
    $html = renderInventoryViewHelper($shopA);
    expect($html)->toContain('Main Warehouse');
    expect($html)->not->toContain('West Coast Hub');
    expect($html)->toContain('<option value="0" selected>');
});
