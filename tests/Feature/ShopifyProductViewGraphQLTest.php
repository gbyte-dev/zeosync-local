<?php

use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\ShopifyService;
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

    ShopSubscription::query()->forceDelete();
    Shop::query()->forceDelete();
    Plan::query()->forceDelete();
});

function createActiveTestShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'view-test-shop-' . uniqid() . '.myshopify.com',
        'shop_name' => 'View Test Store',
        'email' => 'view@example.com',
        'access_token' => 'shpat_test_token_' . uniqid(),
        'shopify_locations' => [
            ['id' => 10001, 'name' => 'Main Warehouse', 'active' => true]
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

function authSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

it('Test 1: successfully fetches product details via GraphQL and renders product-view Blade', function () {
    $shop = createActiveTestShop([
        'shop' => 'view-product-shop.myshopify.com',
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) {
            $query = $request->data()['query'] ?? '';
            expect($query)->toContain('GetProductForView');
            expect($request->data()['variables']['id'])->toBe('gid://shopify/Product/123456');

            return Http::response([
                'data' => [
                    'product' => [
                        'id' => 'gid://shopify/Product/123456',
                        'legacyResourceId' => '123456',
                        'title' => 'Classic Cotton Hoodie',
                        'handle' => 'classic-cotton-hoodie',
                        'descriptionHtml' => '<p>Ultra-warm fleece lined hoodie</p>',
                        'vendor' => 'CozyWear',
                        'productType' => 'Hoodies',
                        'status' => 'ACTIVE',
                        'tags' => ['winter', 'hoodie', 'fleece'],
                        'createdAt' => '2026-09-01T10:00:00Z',
                        'updatedAt' => '2026-09-19T10:00:00Z',
                        'featuredImage' => [
                            'id' => 'gid://shopify/ProductImage/991',
                            'url' => 'https://cdn.shopify.com/hoodie-main.jpg',
                            'altText' => 'Hoodie front',
                        ],
                        'images' => [
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/ProductImage/991',
                                    'url' => 'https://cdn.shopify.com/hoodie-main.jpg',
                                    'altText' => 'Hoodie front',
                                    'width' => 1200,
                                    'height' => 1200,
                                ],
                                [
                                    'id' => 'gid://shopify/ProductImage/992',
                                    'url' => 'https://cdn.shopify.com/hoodie-back.jpg',
                                    'altText' => 'Hoodie back',
                                    'width' => 1200,
                                    'height' => 1200,
                                ],
                            ]
                        ],
                        'options' => [
                            [
                                'id' => 'gid://shopify/ProductOption/881',
                                'name' => 'Color',
                                'position' => 1,
                                'values' => ['Black', 'Navy'],
                            ],
                            [
                                'id' => 'gid://shopify/ProductOption/882',
                                'name' => 'Size',
                                'position' => 2,
                                'values' => ['M', 'L'],
                            ],
                        ],
                        'variants' => [
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/ProductVariant/771',
                                    'legacyResourceId' => '771',
                                    'title' => 'Black / M',
                                    'sku' => 'HD-BLK-M',
                                    'barcode' => '111222333444',
                                    'price' => '49.99',
                                    'compareAtPrice' => '59.99',
                                    'position' => 1,
                                    'selectedOptions' => [
                                        ['name' => 'Color', 'value' => 'Black'],
                                        ['name' => 'Size', 'value' => 'M'],
                                    ],
                                    'image' => [
                                        'id' => 'gid://shopify/ProductImage/991',
                                        'url' => 'https://cdn.shopify.com/hoodie-main.jpg',
                                    ],
                                    'inventoryQuantity' => 20,
                                    'inventoryItem' => [
                                        'id' => 'gid://shopify/InventoryItem/661',
                                        'legacyResourceId' => '661',
                                        'inventoryLevels' => [
                                            'nodes' => [
                                                [
                                                    'location' => [
                                                        'id' => 'gid://shopify/Location/10001',
                                                        'legacyResourceId' => '10001',
                                                    ],
                                                    'quantities' => [
                                                        ['name' => 'available', 'quantity' => 18],
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

    $response = $this->withSession(authSession($shop))
        ->get("/product/123456?shop={$shop->shop}");

    $response->assertOk();
    $response->assertViewIs('product-view');
    $response->assertViewHas('product');
    $response->assertViewHas('activeShop', $shop->shop);

    $viewData = $response->viewData('product');
    expect($viewData['id'])->toBe(123456)
        ->and($viewData['title'])->toBe('Classic Cotton Hoodie')
        ->and($viewData['vendor'])->toBe('CozyWear')
        ->and($viewData['product_type'])->toBe('Hoodies')
        ->and($viewData['status'])->toBe('active')
        ->and($viewData['body_html'])->toBe('<p>Ultra-warm fleece lined hoodie</p>')
        ->and($viewData['variants'][0]['inventory_quantity'])->toBe(18); // Selected location inventory
});

it('Test 2: resolves location-specific inventory for chosen location index', function () {
    $shop = createActiveTestShop([
        'shop' => 'view-location-shop.myshopify.com',
        'shopify_locations' => [
            ['id' => 10001, 'name' => 'Location 1', 'active' => true],
            ['id' => 10002, 'name' => 'Location 2', 'active' => true],
        ],
        'selected_location_index' => 1, // Points to Location 10002
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'product' => [
                    'id' => 'gid://shopify/Product/999',
                    'legacyResourceId' => '999',
                    'title' => 'Multi-Location Item',
                    'variants' => [
                        'nodes' => [
                            [
                                'id' => 'gid://shopify/ProductVariant/888',
                                'legacyResourceId' => '888',
                                'title' => 'Default',
                                'price' => '20.00',
                                'inventoryQuantity' => 100,
                                'inventoryItem' => [
                                    'id' => 'gid://shopify/InventoryItem/777',
                                    'legacyResourceId' => '777',
                                    'inventoryLevels' => [
                                        'nodes' => [
                                            [
                                                'location' => ['id' => 'gid://shopify/Location/10001', 'legacyResourceId' => '10001'],
                                                'quantities' => [['name' => 'available', 'quantity' => 60]]
                                            ],
                                            [
                                                'location' => ['id' => 'gid://shopify/Location/10002', 'legacyResourceId' => '10002'],
                                                'quantities' => [['name' => 'available', 'quantity' => 40]]
                                            ],
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

    $response = $this->withSession(authSession($shop))
        ->get("/product/999?shop={$shop->shop}");

    $response->assertOk();
    $viewData = $response->viewData('product');
    expect($viewData['variants'][0]['inventory_quantity'])->toBe(40); // Resolved to Location 10002
});

it('Test 3: redirects to dashboard with error message when product is not found on Shopify', function () {
    $shop = createActiveTestShop([
        'shop' => 'view-missing-shop.myshopify.com',
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'product' => null
            ]
        ], 200)
    ]);

    $response = $this->withSession(authSession($shop))
        ->get("/product/non_existent_id?shop={$shop->shop}");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Product not found');
});

it('Test 4: handles GraphQL top-level errors and network failures by redirecting with error', function () {
    $shop = createActiveTestShop([
        'shop' => 'view-error-shop.myshopify.com',
    ]);

    Http::fake([
        '*/admin/api/2026-07/graphql.json' => Http::response([
            'errors' => [
                ['message' => 'Internal server error on Shopify']
            ]
        ], 200)
    ]);

    $response = $this->withSession(authSession($shop))
        ->get("/product/55555?shop={$shop->shop}");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Product not found');
});

it('Test 5: enforces tenant isolation across distinct shops', function () {
    $shopA = createActiveTestShop([
        'shop' => 'shop-a-view.myshopify.com',
        'shopify_locations' => [['id' => 10001, 'name' => 'Main A', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    $shopB = createActiveTestShop([
        'shop' => 'shop-b-view.myshopify.com',
        'shopify_locations' => [['id' => 20001, 'name' => 'Main B', 'active' => true]],
        'selected_location_index' => 0,
    ]);

    Http::fake([
        'https://shop-a-view.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'product' => [
                    'id' => 'gid://shopify/Product/111',
                    'legacyResourceId' => '111',
                    'title' => 'Shop A Exclusive Item',
                    'variants' => [
                        'nodes' => [
                            ['id' => 'gid://shopify/ProductVariant/222', 'legacyResourceId' => '222', 'title' => 'Default', 'price' => '10.00']
                        ]
                    ]
                ]
            ]
        ], 200),
    ]);

    $response = $this->withSession(authSession($shopA))
        ->get("/product/111?shop={$shopA->shop}");

    $response->assertOk();
    expect($response->viewData('product')['title'])->toBe('Shop A Exclusive Item')
        ->and($response->viewData('activeShop'))->toBe($shopA->shop);
});
