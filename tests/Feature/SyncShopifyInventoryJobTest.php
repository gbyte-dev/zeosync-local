<?php

use App\Console\Commands\RefreshShopifyInventoryCache;
use App\Jobs\SyncShopifyInventoryJob;
use App\Models\Shop;
use App\Services\ShopifyInventoryService;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

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
            $table->string('quantity')->nullable();
            $table->timestamps();
        });
    }

    // Default Http fake will be configured per test or via fakeDefaultShopifyGraphQL()
});

function fakeDefaultShopifyGraphQL(): void
{
    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'products' => [
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/1001',
                            'title' => 'Test Product',
                            'featuredImage' => ['url' => 'https://example.com/img.jpg'],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/2001',
                                        'title' => 'Default Title',
                                        'sku' => 'TEST-SKU-1',
                                        'image' => ['url' => 'https://example.com/var.jpg'],
                                        'inventoryQuantity' => 15,
                                        'inventoryItem' => [
                                            'id' => 'gid://shopify/InventoryItem/3001',
                                            'inventoryLevels' => [
                                                'nodes' => [
                                                    [
                                                        'location' => ['id' => 'gid://shopify/Location/10001'],
                                                        'quantities' => [
                                                            ['name' => 'available', 'quantity' => 15],
                                                            ['name' => 'committed', 'quantity' => 0],
                                                            ['name' => 'incoming', 'quantity' => 0],
                                                            ['name' => 'on_hand', 'quantity' => 15],
                                                        ],
                                                    ],
                                                    [
                                                        'location' => ['id' => 'gid://shopify/Location/10002'],
                                                        'quantities' => [
                                                            ['name' => 'available', 'quantity' => 30],
                                                            ['name' => 'committed', 'quantity' => 2],
                                                            ['name' => 'incoming', 'quantity' => 0],
                                                            ['name' => 'on_hand', 'quantity' => 32],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'endCursor'   => null,
                    ],
                ],
            ],
        ], 200),
        '*oauth/access_token*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);
}

function createSyncTestShop(array $attributes = []): Shop
{
    return Shop::create(array_merge([
        'shop'                    => 'sync-test-shop-' . uniqid() . '.myshopify.com',
        'access_token'            => 'shpua_test_token_123',
        'is_active'               => 1,
        'store_status'            => 'active',
        'selected_location_index' => 0,
        'shopify_locations'       => [
            ['id' => '10001', 'name' => 'Main Warehouse'],
            ['id' => '10002', 'name' => 'Secondary Warehouse'],
        ],
    ], $attributes));
}

// -------------------------------------------------------------------------
// 1. Method availability and successful job execution
// -------------------------------------------------------------------------

test('1. SyncShopifyInventoryJob successfully executes refreshShopifyInventory without undefined method error', function () {
    fakeDefaultShopifyGraphQL();
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    // Call service directly to verify method signature and return
    $result = $service->refreshShopifyInventory($shop);
    expect($result)->toBeArray()
        ->and(count($result))->toBe(1)
        ->and($result[0]['sku'])->toBe('TEST-SKU-1')
        ->and($result[0]['available'])->toBe(15);

    // Call through queued job handle
    $job = new SyncShopifyInventoryJob($shop->id);
    $job->handle($service);

    $effectiveIndex = 0;
    $cacheKey = "shopify_inventory_{$shop->shop}_location_{$effectiveIndex}";
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('2. SyncShopifyInventoryJob exits gracefully when shop does not exist', function () {
    $service = new ShopifyInventoryService();
    $job = new SyncShopifyInventoryJob(999999);

    // Should not throw exception
    $job->handle($service);
    expect(true)->toBeTrue();
});

// -------------------------------------------------------------------------
// 2. Cache invalidation & fresh data repopulation
// -------------------------------------------------------------------------

test('3. refreshShopifyInventory clears existing location cache and repopulates with fresh data', function () {
    fakeDefaultShopifyGraphQL();
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";

    // Pre-populate cache with old/stale mock data
    Cache::put($cacheKey, [['sku' => 'STALE-SKU', 'available' => 999]], now()->addMinutes(10));
    expect(Cache::get($cacheKey)[0]['sku'])->toBe('STALE-SKU');

    // Call refreshShopifyInventory
    $freshResult = $service->refreshShopifyInventory($shop);

    // Assert cache was replaced with fresh GraphQL data
    expect($freshResult[0]['sku'])->toBe('TEST-SKU-1')
        ->and($freshResult[0]['available'])->toBe(15);

    $cachedData = Cache::get($cacheKey);
    expect($cachedData[0]['sku'])->toBe('TEST-SKU-1')
        ->and($cachedData[0]['available'])->toBe(15);
});

// -------------------------------------------------------------------------
// 3. Location-aware cache keys and fallback behavior
// -------------------------------------------------------------------------

test('4. refreshShopifyInventory uses correct cache key for secondary location index', function () {
    fakeDefaultShopifyGraphQL();
    $shop = createSyncTestShop([
        'selected_location_index' => 1,
    ]);
    $service = new ShopifyInventoryService();

    $expectedCacheKey = "shopify_inventory_{$shop->shop}_location_1";

    $result = $service->refreshShopifyInventory($shop);

    expect(Cache::has($expectedCacheKey))->toBeTrue()
        ->and(Cache::has("shopify_inventory_{$shop->shop}_location_0"))->toBeFalse();

    // Location 2 has 30 available in our GraphQL fixture
    expect($result[0]['available'])->toBe(30);
});

test('5. refreshShopifyInventory falls back to location index 0 when selected index is invalid or null', function () {
    fakeDefaultShopifyGraphQL();
    $service = new ShopifyInventoryService();

    // Case A: Missing / null selected_location_index
    $shopNull = createSyncTestShop([
        'selected_location_index' => null,
    ]);
    $service->refreshShopifyInventory($shopNull);
    expect(Cache::has("shopify_inventory_{$shopNull->shop}_location_0"))->toBeTrue();

    // Case B: Out-of-bounds selected_location_index (e.g. index 99 when only 2 locations exist)
    $shopInvalid = createSyncTestShop([
        'selected_location_index' => 99,
    ]);
    $service->refreshShopifyInventory($shopInvalid);
    expect(Cache::has("shopify_inventory_{$shopInvalid->shop}_location_0"))->toBeTrue();

    // Case C: Empty shopify_locations array
    $shopEmptyLocs = createSyncTestShop([
        'shopify_locations'       => [],
        'selected_location_index' => 0,
    ]);
    $service->refreshShopifyInventory($shopEmptyLocs);
    expect(Cache::has("shopify_inventory_{$shopEmptyLocs->shop}_location_0"))->toBeTrue();
});

// -------------------------------------------------------------------------
// 4. Scheduler command behavior (RefreshShopifyInventoryCache)
// -------------------------------------------------------------------------

test('6. RefreshShopifyInventoryCache dispatches SyncShopifyInventoryJob only for shops with expired cache', function () {
    Queue::fake();

    $shopExpired = createSyncTestShop(['shop' => 'expired-shop.myshopify.com']);
    $shopWarm = createSyncTestShop(['shop' => 'warm-shop.myshopify.com']);
    $shopInactive = createSyncTestShop(['shop' => 'inactive-shop.myshopify.com', 'is_active' => 0]);
    $shopNoToken = createSyncTestShop(['shop' => 'no-token-shop.myshopify.com', 'access_token' => null]);

    // Warm shop has valid cache entry
    Cache::put("shopify_inventory_{$shopWarm->shop}_location_0", [['sku' => 'WARM-SKU']], now()->addMinutes(10));

    // Expired shop has no cache entry
    Cache::forget("shopify_inventory_{$shopExpired->shop}_location_0");

    $command = new RefreshShopifyInventoryCache();
    $status = $command->handle();

    expect($status)->toBe(\Illuminate\Console\Command::SUCCESS);

    // Job should be pushed ONLY for the expired shop
    Queue::assertPushed(SyncShopifyInventoryJob::class, function ($job) use ($shopExpired) {
        $reflection = new \ReflectionClass($job);
        $property = $reflection->getProperty('shopId');
        $property->setAccessible(true);
        return $property->getValue($job) === $shopExpired->id;
    });

    // Job should NOT be pushed for warm shop
    Queue::assertNotPushed(SyncShopifyInventoryJob::class, function ($job) use ($shopWarm) {
        $reflection = new \ReflectionClass($job);
        $property = $reflection->getProperty('shopId');
        $property->setAccessible(true);
        return $property->getValue($job) === $shopWarm->id;
    });

    // Job should NOT be pushed for inactive or no-token shops
    Queue::assertNotPushed(SyncShopifyInventoryJob::class, function ($job) use ($shopInactive) {
        $reflection = new \ReflectionClass($job);
        $property = $reflection->getProperty('shopId');
        $property->setAccessible(true);
        return $property->getValue($job) === $shopInactive->id;
    });
});

// -------------------------------------------------------------------------
// 5. Pagination Safety Guards
// -------------------------------------------------------------------------

function makeTestProductNode(string $id, string $title, string $sku, int $qty): array
{
    return [
        'id' => "gid://shopify/Product/{$id}",
        'title' => $title,
        'featuredImage' => ['url' => "https://example.com/{$id}.jpg"],
        'variants' => [
            'nodes' => [
                [
                    'id' => "gid://shopify/ProductVariant/{$id}1",
                    'title' => 'Default Title',
                    'sku' => $sku,
                    'image' => ['url' => "https://example.com/{$id}1.jpg"],
                    'inventoryQuantity' => $qty,
                    'inventoryItem' => [
                        'id' => "gid://shopify/InventoryItem/{$id}2",
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => ['id' => 'gid://shopify/Location/10001'],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => $qty],
                                        ['name' => 'committed', 'quantity' => 0],
                                        ['name' => 'incoming', 'quantity' => 0],
                                        ['name' => 'on_hand', 'quantity' => $qty],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('7. Normal multi-page pagination collects all products across pages', function () {
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    Http::fake([
        '*graphql.json*' => Http::sequence()
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('101', 'Product 1', 'SKU-PAGE-1', 10),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'cursor_page_1',
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('102', 'Product 2', 'SKU-PAGE-2', 20),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor'   => null,
                        ],
                    ],
                ],
            ], 200),
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    $result = $service->refreshShopifyInventory($shop);

    expect(count($result))->toBe(2)
        ->and($result[0]['sku'])->toBe('SKU-PAGE-1')
        ->and($result[1]['sku'])->toBe('SKU-PAGE-2');
});

test('8. Repeated cursor detection terminates pagination safely without infinite loop', function () {
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    Http::fake([
        '*graphql.json*' => Http::sequence()
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('201', 'Product 1', 'SKU-CYCLE-1', 10),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'repeated_cursor_A',
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('202', 'Product 2', 'SKU-CYCLE-2', 20),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'repeated_cursor_A', // Repeated!
                        ],
                    ],
                ],
            ], 200),
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    // Should break safely after detecting repeated cursor without hanging
    $result = $service->refreshShopifyInventory($shop);

    expect(count($result))->toBe(2)
        ->and($result[0]['sku'])->toBe('SKU-CYCLE-1')
        ->and($result[1]['sku'])->toBe('SKU-CYCLE-2');
});

test('9. Missing cursor when has_next is true terminates safely without refetching page 1', function () {
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    Http::fake([
        '*graphql.json*' => Http::sequence()
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('301', 'Product 1', 'SKU-MISSING-CURSOR', 10),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => null, // Missing cursor when hasNextPage is true!
                        ],
                    ],
                ],
            ], 200),
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    $result = $service->refreshShopifyInventory($shop);

    expect(count($result))->toBe(1)
        ->and($result[0]['sku'])->toBe('SKU-MISSING-CURSOR');
});

test('10. Empty page when has_next is true terminates safely without infinite loop', function () {
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    Http::fake([
        '*graphql.json*' => Http::sequence()
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('401', 'Product 1', 'SKU-EMPTY-PAGE-1', 10),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'cursor_page_1',
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [], // Empty page while hasNextPage is true!
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'cursor_page_2',
                        ],
                    ],
                ],
            ], 200),
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    $result = $service->refreshShopifyInventory($shop);

    expect(count($result))->toBe(1)
        ->and($result[0]['sku'])->toBe('SKU-EMPTY-PAGE-1');
});

test('11. Maximum page limit terminates pagination safely at configured limit without hanging', function () {
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();
    $service->maxPages = 3; // Configured small limit for test verification

    $counter = 0;
    Http::fake([
        '*graphql.json*' => function () use (&$counter) {
            $counter++;
            return Http::response([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode((string) (500 + $counter), "Product {$counter}", "SKU-PAGE-{$counter}", 10),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => "cursor_{$counter}",
                        ],
                    ],
                ],
            ], 200);
        },
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    $result = $service->refreshShopifyInventory($shop);

    // Should stop exactly after 3 pages (configured maxPages = 3)
    expect($counter)->toBe(3)
        ->and(count($result))->toBe(3)
        ->and($result[0]['sku'])->toBe('SKU-PAGE-1')
        ->and($result[1]['sku'])->toBe('SKU-PAGE-2')
        ->and($result[2]['sku'])->toBe('SKU-PAGE-3');
});

test('12. Malformed or invalid response stops safely and preserves collected items', function () {
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    Http::fake([
        '*graphql.json*' => Http::sequence()
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('601', 'Product 1', 'SKU-VALID-1', 10),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'cursor_page_1',
                        ],
                    ],
                ],
            ], 200)
            ->push(['error' => 'Internal server error', 'status' => 500], 500),
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    $result = $service->refreshShopifyInventory($shop);

    expect(count($result))->toBe(1)
        ->and($result[0]['sku'])->toBe('SKU-VALID-1');
});

test('13. Multi-hop cyclical cursor (A -> B -> A) stops safely on revisit', function () {
    $shop = createSyncTestShop();
    $service = new ShopifyInventoryService();

    Http::fake([
        '*graphql.json*' => Http::sequence()
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('701', 'Product 1', 'SKU-CYCLE-A', 10),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'cursor_A',
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('702', 'Product 2', 'SKU-CYCLE-B', 20),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'cursor_B',
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    'products' => [
                        'nodes' => [
                            makeTestProductNode('703', 'Product 3', 'SKU-CYCLE-C', 30),
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor'   => 'cursor_A', // Loop back to A
                        ],
                    ],
                ],
            ], 200),
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    $result = $service->refreshShopifyInventory($shop);

    expect(count($result))->toBe(3)
        ->and($result[0]['sku'])->toBe('SKU-CYCLE-A')
        ->and($result[1]['sku'])->toBe('SKU-CYCLE-B')
        ->and($result[2]['sku'])->toBe('SKU-CYCLE-C');
});


