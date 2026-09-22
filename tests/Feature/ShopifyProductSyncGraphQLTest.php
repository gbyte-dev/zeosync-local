<?php

use App\Http\Controllers\ShopifyController;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_id')->nullable()->unique();
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
            $table->boolean('synced_to_amazon')->default(false);
            $table->boolean('needs_resync')->default(false);
            $table->unsignedBigInteger('amazon_product_id')->nullable();
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (Schema::hasTable('products')) {
        if (!Schema::hasColumn('products', 'synced_to_amazon')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('synced_to_amazon')->default(false);
            });
        }
        if (!Schema::hasColumn('products', 'needs_resync')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('needs_resync')->default(false);
            });
        }
        if (!Schema::hasColumn('products', 'amazon_product_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('amazon_product_id')->nullable();
            });
        }
    }

    Product::query()->forceDelete();
    Shop::query()->forceDelete();
});

it('Test 1: successfully synchronizes products, variants, options, and images via single-page GraphQL', function () {
    $shop = Shop::create([
        'shop' => 'single-page-shop.myshopify.com',
        'access_token' => 'shpat_test_token_123',
        'shopify_locations' => [
            ['id' => 10001, 'name' => 'Main Warehouse', 'active' => true]
        ],
        'selected_location_index' => 0,
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) {
            $query = $request->data()['query'] ?? '';
            expect($query)->toContain('GetProductsForSync');

            return Http::response([
                'data' => [
                    'products' => [
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                        'nodes' => [
                            [
                                'id' => 'gid://shopify/Product/987654321',
                                'legacyResourceId' => '987654321',
                                'title' => 'Test GraphQL T-Shirt',
                                'handle' => 'test-graphql-t-shirt',
                                'descriptionHtml' => '<p>Premium organic cotton t-shirt</p>',
                                'vendor' => 'ZeoApparel',
                                'productType' => 'Shirts',
                                'status' => 'ACTIVE',
                                'tags' => ['summer', 'organic', 'cotton'],
                                'createdAt' => '2026-09-01T10:00:00Z',
                                'updatedAt' => '2026-09-19T10:00:00Z',
                                'options' => [
                                    [
                                        'id' => 'gid://shopify/ProductOption/111',
                                        'name' => 'Size',
                                        'position' => 1,
                                        'values' => ['Small', 'Medium', 'Large'],
                                    ]
                                ],
                                'images' => [
                                    'nodes' => [
                                        [
                                            'id' => 'gid://shopify/ProductImage/222',
                                            'url' => 'https://cdn.shopify.com/tshirt.png',
                                            'altText' => 'Front view',
                                            'width' => 1000,
                                            'height' => 1000,
                                        ]
                                    ]
                                ],
                                'variants' => [
                                    'nodes' => [
                                        [
                                            'id' => 'gid://shopify/ProductVariant/333',
                                            'legacyResourceId' => '333',
                                            'title' => 'Small',
                                            'sku' => 'TSHIRT-SM',
                                            'barcode' => '123456789012',
                                            'price' => '24.99',
                                            'compareAtPrice' => '29.99',
                                            'position' => 1,
                                            'selectedOptions' => [
                                                ['name' => 'Size', 'value' => 'Small']
                                            ],
                                            'image' => [
                                                'id' => 'gid://shopify/ProductImage/222',
                                                'url' => 'https://cdn.shopify.com/tshirt.png',
                                            ],
                                            'inventoryQuantity' => 45,
                                            'inventoryItem' => [
                                                'id' => 'gid://shopify/InventoryItem/444',
                                                'legacyResourceId' => '444',
                                                'inventoryLevels' => [
                                                    'nodes' => [
                                                        [
                                                            'location' => [
                                                                'id' => 'gid://shopify/Location/10001',
                                                                'legacyResourceId' => '10001',
                                                            ],
                                                            'quantities' => [
                                                                ['name' => 'available', 'quantity' => 25],
                                                                ['name' => 'on_hand', 'quantity' => 30],
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200);
        }
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    $dbProduct = Product::where('shop_id', $shop->id)->where('shopify_id', 987654321)->first();

    expect($dbProduct)
        ->not
        ->toBeNull()
        ->and($dbProduct->title)
        ->toBe('Test GraphQL T-Shirt')
        ->and($dbProduct->description)
        ->toBe('Premium organic cotton t-shirt')
        ->and((float) $dbProduct->price)
        ->toEqual(24.99)
        ->and($dbProduct->status)
        ->toBe('active')
        ->and($dbProduct->product_type)
        ->toBe('Shirts')
        ->and($dbProduct->vendor)
        ->toBe('ZeoApparel')
        ->and($dbProduct->tags)
        ->toBe('summer, organic, cotton');

    // Variants verification
    $variants = $dbProduct->variants;
    expect($variants)->toBeArray()->toHaveCount(1);
    expect($variants[0]['id'])
        ->toBe(333)
        ->and($variants[0]['product_id'])
        ->toBe(987654321)
        ->and($variants[0]['title'])
        ->toBe('Small')
        ->and($variants[0]['sku'])
        ->toBe('TSHIRT-SM')
        ->and($variants[0]['barcode'])
        ->toBe('123456789012')
        ->and($variants[0]['price'])
        ->toBe('24.99')
        ->and($variants[0]['compare_at_price'])
        ->toBe('29.99')
        ->and($variants[0]['inventory_item_id'])
        ->toBe(444)
        ->and($variants[0]['inventory_quantity'])
        ->toBe(25)  // Selected location quantity!
        ->and($variants[0]['option1'])
        ->toBe('Small');

    // Images verification
    $images = $dbProduct->images;
    expect($images)->toBeArray()->toHaveCount(1);
    expect($images[0]['src'])
        ->toBe('https://cdn.shopify.com/tshirt.png')
        ->and($images[0]['url'])
        ->toBe('https://cdn.shopify.com/tshirt.png')
        ->and($images[0]['alt'])
        ->toBe('Front view');

    // Options verification
    $options = $dbProduct->options;
    expect($options)->toBeArray()->toHaveCount(1);
    expect($options[0]['name'])
        ->toBe('Size')
        ->and($options[0]['values'])
        ->toBe(['Small', 'Medium', 'Large']);
});

it('Test 2: advances cursor through multiple GraphQL pages and syncs all products', function () {
    $shop = Shop::create([
        'shop' => 'multi-page-shop.myshopify.com',
        'access_token' => 'shpat_multi_page_token',
        'shopify_locations' => [
            ['id' => 10001, 'name' => 'Main Warehouse', 'active' => true]
        ],
        'selected_location_index' => 0,
    ]);

    $callCount = 0;

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$callCount) {
            $callCount++;
            $variables = $request->data()['variables'] ?? [];
            $after = $variables['after'] ?? null;

            if ($after === null) {
                // Page 1
                return Http::response([
                    'data' => [
                        'products' => [
                            'pageInfo' => [
                                'hasNextPage' => true,
                                'endCursor' => 'cursor_page_1',
                            ],
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/Product/1001',
                                    'legacyResourceId' => '1001',
                                    'title' => 'Product Page 1',
                                    'descriptionHtml' => 'Page 1 Desc',
                                    'status' => 'ACTIVE',
                                    'variants' => [
                                        'nodes' => [
                                            [
                                                'id' => 'gid://shopify/ProductVariant/2001',
                                                'legacyResourceId' => '2001',
                                                'title' => 'V1',
                                                'price' => '10.00',
                                                'inventoryQuantity' => 5,
                                                'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/3001', 'legacyResourceId' => '3001'],
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 200);
            } elseif ($after === 'cursor_page_1') {
                // Page 2
                return Http::response([
                    'data' => [
                        'products' => [
                            'pageInfo' => [
                                'hasNextPage' => true,
                                'endCursor' => 'cursor_page_2',
                            ],
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/Product/1002',
                                    'legacyResourceId' => '1002',
                                    'title' => 'Product Page 2',
                                    'descriptionHtml' => 'Page 2 Desc',
                                    'status' => 'DRAFT',
                                    'variants' => [
                                        'nodes' => [
                                            [
                                                'id' => 'gid://shopify/ProductVariant/2002',
                                                'legacyResourceId' => '2002',
                                                'title' => 'V2',
                                                'price' => '20.00',
                                                'inventoryQuantity' => 8,
                                                'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/3002', 'legacyResourceId' => '3002'],
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 200);
            } else {
                // Page 3 (Final)
                return Http::response([
                    'data' => [
                        'products' => [
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/Product/1003',
                                    'legacyResourceId' => '1003',
                                    'title' => 'Product Page 3',
                                    'descriptionHtml' => 'Page 3 Desc',
                                    'status' => 'ARCHIVED',
                                    'variants' => [
                                        'nodes' => [
                                            [
                                                'id' => 'gid://shopify/ProductVariant/2003',
                                                'legacyResourceId' => '2003',
                                                'title' => 'V3',
                                                'price' => '30.00',
                                                'inventoryQuantity' => 12,
                                                'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/3003', 'legacyResourceId' => '3003'],
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 200);
            }
        }
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    expect($callCount)->toBe(3);
    expect(Product::where('shop_id', $shop->id)->count())->toBe(3);

    $p1 = Product::where('shop_id', $shop->id)->where('shopify_id', 1001)->first();
    $p2 = Product::where('shop_id', $shop->id)->where('shopify_id', 1002)->first();
    $p3 = Product::where('shop_id', $shop->id)->where('shopify_id', 1003)->first();

    expect($p1->title)
        ->toBe('Product Page 1')
        ->and($p1->status)
        ->toBe('active');
    expect($p2->title)
        ->toBe('Product Page 2')
        ->and($p2->status)
        ->toBe('draft');
    expect($p3->title)
        ->toBe('Product Page 3')
        ->and($p3->status)
        ->toBe('archived');
});

it('Test 3: correctly resolves variant inventory for the shop selected location', function () {
    $shop = Shop::create([
        'shop' => 'location-inventory-shop.myshopify.com',
        'access_token' => 'shpat_loc_token',
        'shopify_locations' => [
            ['id' => 10001, 'name' => 'Main Location', 'active' => true],
            ['id' => 10002, 'name' => 'Secondary Location', 'active' => true],
        ],
        'selected_location_index' => 1,  // Points to Location 10002
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/555',
                            'legacyResourceId' => '555',
                            'title' => 'Multi-Location Product',
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/666',
                                        'legacyResourceId' => '666',
                                        'title' => 'Default',
                                        'price' => '15.00',
                                        'inventoryQuantity' => 100,  // Total across store
                                        'inventoryItem' => [
                                            'id' => 'gid://shopify/InventoryItem/777',
                                            'legacyResourceId' => '777',
                                            'inventoryLevels' => [
                                                'nodes' => [
                                                    [
                                                        'location' => ['id' => 'gid://shopify/Location/10001', 'legacyResourceId' => '10001'],
                                                        'quantities' => [['name' => 'available', 'quantity' => 70]]
                                                    ],
                                                    [
                                                        'location' => ['id' => 'gid://shopify/Location/10002', 'legacyResourceId' => '10002'],
                                                        'quantities' => [['name' => 'available', 'quantity' => 30]]
                                                    ],
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    $dbProduct = Product::where('shop_id', $shop->id)->where('shopify_id', 555)->first();
    expect($dbProduct)->not->toBeNull();
    $variants = $dbProduct->variants;
    expect($variants[0]['inventory_quantity'])->toBe(30);  // Location 10002 quantity
});

it('Test 4: updates existing product in place without creating duplicate records', function () {
    $shop = Shop::create([
        'shop' => 'update-shop.myshopify.com',
        'access_token' => 'shpat_update_token',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    // Create existing product in DB
    $existing = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 88888,
        'title' => 'Old Title Before Sync',
        'description' => 'Old Description',
        'price' => 10.0,
        'status' => 'draft',
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/88888',
                            'legacyResourceId' => '88888',
                            'title' => 'Updated Title From Shopify GraphQL',
                            'descriptionHtml' => 'New Description',
                            'status' => 'ACTIVE',
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/99999',
                                        'legacyResourceId' => '99999',
                                        'title' => 'Default',
                                        'price' => '19.99',
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/11111', 'legacyResourceId' => '11111'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    expect(Product::where('shop_id', $shop->id)->where('shopify_id', 88888)->count())->toBe(1);

    $refreshed = Product::find($existing->id);
    expect($refreshed->title)
        ->toBe('Updated Title From Shopify GraphQL')
        ->and($refreshed->description)
        ->toBe('New Description')
        ->and((float) $refreshed->price)
        ->toEqual(19.99)
        ->and($refreshed->status)
        ->toBe('active');
});

it('Test 5: inserts new product record when shopify_id is not yet in database', function () {
    $shop = Shop::create([
        'shop' => 'new-product-shop.myshopify.com',
        'access_token' => 'shpat_new_token',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    expect(Product::where('shop_id', $shop->id)->count())->toBe(0);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/77777',
                            'legacyResourceId' => '77777',
                            'title' => 'Brand New Product',
                            'status' => 'ACTIVE',
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/88888',
                                        'legacyResourceId' => '88888',
                                        'title' => 'Default',
                                        'price' => '49.99',
                                        'inventoryQuantity' => 5,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/99999', 'legacyResourceId' => '99999'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    expect(Product::where('shop_id', $shop->id)->count())->toBe(1);
    $newProduct = Product::where('shop_id', $shop->id)->first();
    expect($newProduct->shopify_id)
        ->toBe(77777)
        ->and($newProduct->title)
        ->toBe('Brand New Product')
        ->and((float) $newProduct->price)
        ->toEqual(49.99);
});

it('Test 6: enforces strict tenant isolation between multiple shops', function () {
    $shopA = Shop::create([
        'shop' => 'shop-a.myshopify.com',
        'access_token' => 'shpat_shop_a',
        'shopify_locations' => [['id' => 10001, 'name' => 'Shop A Loc', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    $shopB = Shop::create([
        'shop' => 'shop-b.myshopify.com',
        'access_token' => 'shpat_shop_b',
        'shopify_locations' => [['id' => 20001, 'name' => 'Shop B Loc', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    Http::fake([
        'https://shop-a.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/12345',
                            'legacyResourceId' => '12345',
                            'title' => 'Shop A Product',
                            'status' => 'ACTIVE',
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/11111',
                                        'legacyResourceId' => '11111',
                                        'title' => 'Default',
                                        'price' => '10.00',
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/22222', 'legacyResourceId' => '22222'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], 200),
        'https://shop-b.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/67890',
                            'legacyResourceId' => '67890',
                            'title' => 'Shop B Product',
                            'status' => 'ACTIVE',
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/33333',
                                        'legacyResourceId' => '33333',
                                        'title' => 'Default',
                                        'price' => '20.00',
                                        'inventoryQuantity' => 20,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/44444', 'legacyResourceId' => '44444'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], 200),
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shopA);
    $controller->syncProductsToDB($shopB);

    $prodA = Product::where('shop_id', $shopA->id)->first();
    $prodB = Product::where('shop_id', $shopB->id)->first();

    expect($prodA)
        ->not
        ->toBeNull()
        ->and($prodB)
        ->not
        ->toBeNull()
        ->and($prodA->title)
        ->toBe('Shop A Product')
        ->and($prodB->title)
        ->toBe('Shop B Product')
        ->and($prodA->shop_id)
        ->toBe($shopA->id)
        ->and($prodB->shop_id)
        ->toBe($shopB->id);
});

it('Test 7: handles GraphQL top-level error cleanly without throwing unhandled exceptions', function () {
    $shop = Shop::create([
        'shop' => 'graphql-error-shop.myshopify.com',
        'access_token' => 'shpat_error_token',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'errors' => [
                ['message' => 'Throttled by Shopify GraphQL rate limiter', 'extensions' => ['code' => 'THROTTLED']]
            ]
        ], 200)
    ]);

    $controller = new ShopifyController();
    // Must not crash or throw unhandled exception
    $controller->syncProductsToDB($shop);

    expect(Product::where('shop_id', $shop->id)->count())->toBe(0);
});

it('Test 8: handles network/API exceptions cleanly without throwing unhandled exceptions', function () {
    $shop = Shop::create([
        'shop' => 'network-fail-shop.myshopify.com',
        'access_token' => 'shpat_network_token',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response('Gateway Timeout', 504)
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    expect(Product::where('shop_id', $shop->id)->count())->toBe(0);
});

it('Test 10: restores soft-deleted product and updates Shopify fields when synced again', function () {
    $shop = Shop::create([
        'shop' => 'restore-shop.myshopify.com',
        'access_token' => 'shpat_restore_token',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    $existing = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 99887766,
        'title' => 'Deleted T-Shirt',
        'price' => 15.00,
        'status' => 'draft',
    ]);
    $existing->delete(); // Soft-deleted

    expect(Product::where('shop_id', $shop->id)->where('shopify_id', 99887766)->count())->toBe(0);
    expect(Product::withTrashed()->where('shop_id', $shop->id)->where('shopify_id', 99887766)->count())->toBe(1);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/99887766',
                            'legacyResourceId' => '99887766',
                            'title' => 'Restored & Active T-Shirt',
                            'status' => 'ACTIVE',
                            'descriptionHtml' => '<p>Restored item</p>',
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/112233',
                                        'legacyResourceId' => '112233',
                                        'title' => 'Default Title',
                                        'price' => '29.99',
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/556677', 'legacyResourceId' => '556677'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    $restored = Product::where('shop_id', $shop->id)->where('shopify_id', 99887766)->first();
    expect($restored)
        ->not->toBeNull()
        ->and($restored->trashed())->toBeFalse()
        ->and($restored->title)->toBe('Restored & Active T-Shirt')
        ->and((float) $restored->price)->toEqual(29.99)
        ->and($restored->status)->toBe('active');
});

it('Test 11: preserves Amazon mapping fields (synced_to_amazon, needs_resync, amazon_product_id) during Shopify sync', function () {
    $shop = Shop::create([
        'shop' => 'amazon-preserve-shop.myshopify.com',
        'access_token' => 'shpat_amazon_token',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    $existing = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 55443322,
        'title' => 'Old Title',
        'price' => 10.00,
        'status' => 'active',
        'synced_to_amazon' => 1,
        'needs_resync' => 0,
        'amazon_product_id' => 888999,
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'products' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Product/55443322',
                            'legacyResourceId' => '55443322',
                            'title' => 'Updated Title From Shopify',
                            'status' => 'ACTIVE',
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/778899',
                                        'legacyResourceId' => '778899',
                                        'title' => 'Default',
                                        'price' => '19.99',
                                        'inventoryQuantity' => 15,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/112244', 'legacyResourceId' => '112244'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $controller = new ShopifyController();
    $controller->syncProductsToDB($shop);

    $updated = Product::where('shop_id', $shop->id)->where('shopify_id', 55443322)->first();
    expect($updated)
        ->not->toBeNull()
        ->and($updated->title)->toBe('Updated Title From Shopify')
        ->and((float) $updated->price)->toEqual(19.99)
        ->and((int) $updated->synced_to_amazon)->toBe(1)
        ->and((int) $updated->needs_resync)->toBe(0)
        ->and((int) $updated->amazon_product_id)->toBe(888999);
});

it('Test 12: GraphQL/API failure does not overwrite existing products in DB and avoids cache poisoning', function () {
    $shop = Shop::create([
        'shop' => 'fail-safe-shop.myshopify.com',
        'access_token' => 'shpat_fail_safe_token',
        'access_token_expires_at' => now()->addDays(30),
        'is_active' => true,
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    $existing = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 11223344,
        'title' => 'Important Existing Product',
        'price' => 50.00,
        'status' => 'active',
    ]);

    // Shopify returns 500 error
    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response(['errors' => ['Internal server error']], 500)
    ]);

    $controller = new ShopifyController();
    $request = new \Illuminate\Http\Request();
    $request->attributes->set('active_shop_model', $shop);

    // Call products page with cache miss
    $cacheKey = "products_shop_{$shop->id}";
    \Illuminate\Support\Facades\Cache::forget($cacheKey);

    $view = $controller->products($request);

    // Assert existing DB product was preserved and passed to view
    expect(Product::where('shop_id', $shop->id)->count())->toBe(1);
    $viewProducts = $view->getData()['products'];
    expect($viewProducts)->toHaveCount(1)
        ->and($viewProducts->first()->title)->toBe('Important Existing Product');

    // Cache should not be poisoned with []
    expect(\Illuminate\Support\Facades\Cache::get($cacheKey))->toBeNull();
});

it('Test 13: verifies query does not contain redundant top-level or variant media connections', function () {
    $shop = Shop::create([
        'shop' => 'lean-query-shop.myshopify.com',
        'access_token' => 'shpat_lean_token',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    $capturedQuery = '';

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$capturedQuery) {
            $capturedQuery = $request->data()['query'] ?? '';
            return Http::response([
                'data' => [
                    'products' => [
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'nodes' => []
                    ]
                ],
                'extensions' => [
                    'cost' => [
                        'requestedQueryCost' => 280,
                        'actualQueryCost' => 15,
                        'throttleStatus' => ['maximumAvailable' => 1000, 'currentlyAvailable' => 985, 'restoreRate' => 50]
                    ]
                ]
            ], 200);
        }
    ]);

    $shopifyService = new ShopifyService($shop->shop, $shop->access_token);
    $result = $shopifyService->getProductsForSync($shop, null, 20);

    expect($result['error'])->toBeFalse()
        ->and($capturedQuery)->toContain('GetProductsForSync')
        ->and($capturedQuery)->not->toContain('media(')
        ->and($capturedQuery)->toContain('images(first: 20)')
        ->and($capturedQuery)->toContain('variants(first: 50)');
});
