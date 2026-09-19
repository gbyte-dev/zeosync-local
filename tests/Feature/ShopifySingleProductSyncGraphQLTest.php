<?php

use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    View::share('cspNonce', 'test-csp-nonce-12345');

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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('product_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id');
            $table->foreignId('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->text('tags')->nullable();
            $table->string('category')->nullable();
            $table->text('collections')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('metafields')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->text('local_images')->nullable();
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    ShopSubscription::query()->forceDelete();
    Product::query()->forceDelete();
    Shop::query()->forceDelete();
    Plan::query()->forceDelete();
    Cache::flush();
});

function createSingleSyncShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'single-sync-' . uniqid() . '.myshopify.com',
        'shop_name' => 'Single Sync Store',
        'email' => 'sync@example.com',
        'access_token' => 'shpat_single_sync_' . uniqid(),
        'shopify_locations' => [
            ['id' => 10001, 'name' => 'Primary Location', 'active' => true],
            ['id' => 10002, 'name' => 'Secondary Location', 'active' => true],
        ],
        'selected_location_index' => 0,
        'is_active' => true,
    ], $attributes));

    $plan = Plan::firstOrCreate(
        ['name' => 'Unlimited Plan'],
        ['price' => 0, 'product_limit' => 0, 'is_active' => true]
    );

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'price' => 0,
        'current_period_end' => now()->addYear(),
    ]);

    return $shop;
}

function mockSingleProductGraphQLResponse(array $overrides = []): array
{
    $productData = array_merge([
        'id' => 'gid://shopify/Product/987654321',
        'legacyResourceId' => '987654321',
        'title' => 'Single Sync Wireless Headset',
        'handle' => 'single-sync-wireless-headset',
        'descriptionHtml' => '<p>Noise cancelling audio device</p>',
        'vendor' => 'AudioTech',
        'productType' => 'Headphones',
        'status' => 'ACTIVE',
        'tags' => ['wireless', 'bluetooth', 'audio'],
        'createdAt' => '2026-09-01T00:00:00Z',
        'updatedAt' => '2026-09-19T00:00:00Z',
        'featuredImage' => [
            'id' => 'gid://shopify/ProductImage/5501',
            'url' => 'https://cdn.shopify.com/headset-main.jpg',
            'altText' => 'Wireless Headset Front',
        ],
        'images' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductImage/5501',
                    'url' => 'https://cdn.shopify.com/headset-main.jpg',
                    'altText' => 'Wireless Headset Front',
                    'width' => 1000,
                    'height' => 1000,
                ],
                [
                    'id' => 'gid://shopify/ProductImage/5502',
                    'url' => 'https://cdn.shopify.com/headset-side.jpg',
                    'altText' => 'Wireless Headset Side',
                    'width' => 1000,
                    'height' => 1000,
                ],
            ]
        ],
        'options' => [
            [
                'id' => 'gid://shopify/ProductOption/4401',
                'name' => 'Color',
                'position' => 1,
                'values' => ['Midnight Black', 'Silver'],
            ]
        ],
        'variants' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductVariant/3301',
                    'legacyResourceId' => '3301',
                    'title' => 'Midnight Black',
                    'sku' => 'WH-BLK',
                    'barcode' => '777888999001',
                    'price' => '149.99',
                    'compareAtPrice' => '179.99',
                    'position' => 1,
                    'selectedOptions' => [
                        ['name' => 'Color', 'value' => 'Midnight Black']
                    ],
                    'image' => [
                        'id' => 'gid://shopify/ProductImage/5501',
                        'url' => 'https://cdn.shopify.com/headset-main.jpg',
                    ],
                    'inventoryQuantity' => 35,
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/2201',
                        'legacyResourceId' => '2201',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/10001',
                                        'legacyResourceId' => '10001',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 25],
                                    ]
                                ],
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/10002',
                                        'legacyResourceId' => '10002',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 10],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'gid://shopify/ProductVariant/3302',
                    'legacyResourceId' => '3302',
                    'title' => 'Silver',
                    'sku' => 'WH-SLV',
                    'barcode' => '777888999002',
                    'price' => '159.99',
                    'compareAtPrice' => '189.99',
                    'position' => 2,
                    'selectedOptions' => [
                        ['name' => 'Color', 'value' => 'Silver']
                    ],
                    'image' => [
                        'id' => 'gid://shopify/ProductImage/5502',
                        'url' => 'https://cdn.shopify.com/headset-side.jpg',
                    ],
                    'inventoryQuantity' => 15,
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/2202',
                        'legacyResourceId' => '2202',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/10001',
                                        'legacyResourceId' => '10001',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 12],
                                    ]
                                ],
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/10002',
                                        'legacyResourceId' => '10002',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 3],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ], $overrides);

    return [
        'data' => [
            'product' => $productData,
        ]
    ];
}

it('Test 1: successfully performs singleProductSync via GraphQL and creates local Product in DB', function () {
    $shop = createSingleSyncShop();

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) {
            $query = $request->data()['query'] ?? '';
            expect($query)->toContain('GetProductForView');
            expect($request->data()['variables']['id'])->toBe('gid://shopify/Product/987654321');

            return Http::response(mockSingleProductGraphQLResponse(), 200);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 987654321, 10001);

    expect($result['success'])->toBeTrue();
    expect($result['product'])->toBeArray();
    expect($result['product']['id'])->toBe(987654321);
    expect($result['product']['title'])->toBe('Single Sync Wireless Headset');

    // Verify local DB persistence
    $dbProduct = Product::where('shop_id', $shop->id)->where('shopify_id', 987654321)->first();
    expect($dbProduct)->not->toBeNull();
    expect($dbProduct->title)->toBe('Single Sync Wireless Headset');
    expect($dbProduct->vendor)->toBe('AudioTech');
    expect($dbProduct->product_type)->toBe('Headphones');
    expect($dbProduct->status)->toBe('active');
    expect((float) $dbProduct->price)->toBe(149.99);

    // Verify JSON columns
    $variants = json_decode($dbProduct->variants, true);
    expect($variants)->toHaveCount(2);
    expect($variants[0]['sku'])->toBe('WH-BLK');
    expect($variants[0]['inventory_quantity'])->toBe(25); // Selected location 10001
    expect($variants[1]['sku'])->toBe('WH-SLV');
    expect($variants[1]['inventory_quantity'])->toBe(12); // Selected location 10001

    $images = json_decode($dbProduct->images, true);
    expect($images)->toHaveCount(2);

    $options = json_decode($dbProduct->options, true);
    expect($options)->toHaveCount(1);
    expect($options[0]['name'])->toBe('Color');
});

it('Test 2: accepts full Shopify GID string and normalizes correctly', function () {
    $shop = createSingleSyncShop();

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) {
            expect($request->data()['variables']['id'])->toBe('gid://shopify/Product/987654321');
            return Http::response(mockSingleProductGraphQLResponse(), 200);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 'gid://shopify/Product/987654321');

    expect($result['success'])->toBeTrue();
    expect($result['product']['id'])->toBe(987654321);
});

it('Test 3: updates existing local DB record on subsequent singleProductSync without duplicating', function () {
    $shop = createSingleSyncShop();

    // Pre-create existing local record
    Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 987654321,
        'title' => 'Old Title Before Sync',
        'price' => 99.00,
        'status' => 'draft',
        'vendor' => 'OldVendor',
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function () {
            return Http::response(mockSingleProductGraphQLResponse([
                'title' => 'Updated Wireless Headset V2',
                'vendor' => 'AudioTech Pro',
            ]), 200);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 987654321);

    expect($result['success'])->toBeTrue();

    // Verify only 1 record exists and it is updated
    $products = Product::where('shop_id', $shop->id)->where('shopify_id', 987654321)->get();
    expect($products)->toHaveCount(1);
    expect($products->first()->title)->toBe('Updated Wireless Headset V2');
    expect($products->first()->vendor)->toBe('AudioTech Pro');
});

it('Test 4: location-specific inventory uses requested location quantity', function () {
    $shop = createSingleSyncShop();

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function () {
            return Http::response(mockSingleProductGraphQLResponse(), 200);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);

    // Sync targeting Secondary Location (10002)
    $result = $service->singleProductSync($shop, 987654321, 10002);

    expect($result['success'])->toBeTrue();
    $variants = $result['product']['variants'];
    expect($variants[0]['inventory_quantity'])->toBe(10); // Location 10002 quantity
    expect($variants[1]['inventory_quantity'])->toBe(3);  // Location 10002 quantity
});

it('Test 5: invalidates and refreshes shop products cache when cached', function () {
    $shop = createSingleSyncShop();
    $cacheKey = "products_shop_{$shop->id}";

    // Set existing cache
    Cache::put($cacheKey, collect([['id' => 1]]), now()->addMinutes(15));

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function () {
            return Http::response(mockSingleProductGraphQLResponse(), 200);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 987654321);

    expect($result['success'])->toBeTrue();
    expect(Cache::has($cacheKey))->toBeTrue();
    $cached = Cache::get($cacheKey);
    expect($cached->first()->shopify_id)->toBe(987654321);
});

it('Test 6: handles GraphQL top-level errors gracefully without corrupting local data', function () {
    $shop = createSingleSyncShop();

    // Pre-create existing local record
    $existing = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 987654321,
        'title' => 'Intact Product Title',
        'price' => 50.00,
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'errors' => [
                ['message' => 'GraphQL Rate Limit Exceeded']
            ]
        ], 200)
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 987654321);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Product not found on Shopify.');

    // Verify existing record was untouched
    $dbProduct = Product::find($existing->id);
    expect($dbProduct->title)->toBe('Intact Product Title');
});

it('Test 7: handles network/HTTP 500 failure gracefully', function () {
    $shop = createSingleSyncShop();

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response('Server Error', 500)
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 987654321);

    expect($result['success'])->toBeFalse();
});

it('Test 8: handles product not found (null product in response)', function () {
    $shop = createSingleSyncShop();

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'product' => null,
            ]
        ], 200)
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 999999999);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Product not found on Shopify.');
});

it('Test 9: strictly enforces tenant isolation (Shop A cannot alter Shop B product)', function () {
    $shopA = createSingleSyncShop(['shop' => 'shop-a.myshopify.com']);
    $shopB = createSingleSyncShop(['shop' => 'shop-b.myshopify.com']);

    // Shop B has a product with shopify_id 111111
    Product::create([
        'shop_id' => $shopB->id,
        'shopify_id' => 111111,
        'title' => 'Shop B Original Headset',
        'price' => 100.00,
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function () {
            return Http::response(mockSingleProductGraphQLResponse([
                'id' => 'gid://shopify/Product/222222',
                'legacyResourceId' => '222222',
                'title' => 'Shop A Synced Headset',
            ]), 200);
        }
    ]);

    $serviceA = new ShopifyService($shopA->shop, $shopA->access_token);
    $resultA = $serviceA->singleProductSync($shopA, 222222);

    expect($resultA['success'])->toBeTrue();

    // Shop B product should remain untouched
    $shopBProduct = Product::where('shop_id', $shopB->id)->where('shopify_id', 111111)->first();
    expect($shopBProduct->title)->toBe('Shop B Original Headset');

    // Shop A product should have been created with shop_id = $shopA->id
    $shopAProduct = Product::where('shop_id', $shopA->id)->where('shopify_id', 222222)->first();
    expect($shopAProduct->title)->toBe('Shop A Synced Headset');
    expect($shopAProduct->shop_id)->toBe($shopA->id);
});

it('Test 10: does NOT perform any Shopify inventory mutation (read-only sync)', function () {
    $shop = createSingleSyncShop();
    $mutationCalled = false;

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$mutationCalled) {
            $query = $request->data()['query'] ?? '';
            if (str_contains($query, 'inventorySetQuantities') || str_contains($query, 'inventoryAdjustQuantities')) {
                $mutationCalled = true;
            }
            return Http::response(mockSingleProductGraphQLResponse(), 200);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->singleProductSync($shop, 987654321, 10001);

    expect($result['success'])->toBeTrue();
    expect($mutationCalled)->toBeFalse();
});
