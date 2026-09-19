<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\AmazonProduct;
use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key' => 'test_api_key',
        'services.shopify.api_secret' => 'test_api_secret',
        'services.shopify.api_version' => '2026-07',
        'app.disable_subscription' => false,
    ]);

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

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
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('shopify_connection_status')->nullable();
            $table->string('store_status')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->string('hmac')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    Shop::query()->forceDelete();
    ShopSubscription::query()->forceDelete();
    Product::query()->forceDelete();
    AmazonProduct::query()->forceDelete();
});

function createEditShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'edit-test-store.myshopify.com',
        'shop_name' => 'Edit Test Store',
        'email' => 'edit@example.com',
        'access_token' => 'shpat_edit_token_' . uniqid(),
        'shopify_locations' => [
            ['id' => 10001, 'name' => 'Primary Warehouse', 'active' => true],
            ['id' => 10002, 'name' => 'Secondary Warehouse', 'active' => true],
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

function authEditSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at' => time(),
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ];
}

it('Test 1: successfully loads product for editing via GraphQL and renders EditProduct Blade', function () {
    $shop = createEditShop();

    $graphqlProduct = [
        'id' => 'gid://shopify/Product/778899',
        'legacyResourceId' => '778899',
        'title' => 'Vintage Leather Jacket',
        'handle' => 'vintage-leather-jacket',
        'descriptionHtml' => '<p>Premium handmade jacket</p>',
        'vendor' => 'RetroWear',
        'productType' => 'Outerwear',
        'status' => 'ACTIVE',
        'tags' => ['vintage', 'leather', 'winter'],
        'createdAt' => '2026-01-15T08:30:00Z',
        'updatedAt' => '2026-02-20T12:00:00Z',
        'featuredImage' => [
            'id' => 'gid://shopify/ProductImage/111',
            'url' => 'https://cdn.shopify.com/jacket-main.jpg',
            'altText' => 'Main Jacket Image',
            'width' => 800,
            'height' => 800,
        ],
        'options' => [
            [
                'id' => 'gid://shopify/ProductOption/222',
                'name' => 'Size',
                'position' => 1,
                'values' => ['Medium', 'Large'],
            ],
        ],
        'images' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductImage/111',
                    'url' => 'https://cdn.shopify.com/jacket-main.jpg',
                    'altText' => 'Main Jacket Image',
                    'width' => 800,
                    'height' => 800,
                ],
            ],
        ],
        'variants' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductVariant/3331',
                    'legacyResourceId' => '3331',
                    'title' => 'Medium',
                    'sku' => 'JACKET-MED',
                    'barcode' => '890123456789',
                    'price' => '149.99',
                    'compareAtPrice' => '199.99',
                    'position' => 1,
                    'selectedOptions' => [
                        ['name' => 'Size', 'value' => 'Medium'],
                    ],
                    'image' => [
                        'url' => 'https://cdn.shopify.com/jacket-main.jpg',
                    ],
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/4441',
                        'legacyResourceId' => '4441',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/10001',
                                        'legacyResourceId' => '10001',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 12],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => Http::response([
            'data' => [
                'product' => $graphqlProduct,
            ],
        ], 200),
    ]);

    $response = $this->withSession(authEditSession($shop))
        ->get("/editProduct/778899?shop={$shop->shop}");

    $response->assertStatus(200);
    $response->assertViewIs('EditProduct');
    $response->assertViewHas('product');
    $response->assertViewHas('activeShop', $shop->shop);
    $response->assertSee('Vintage Leather Jacket');
    $response->assertSee('JACKET-MED');
    $response->assertSee('149.99');

    $viewProduct = $response->viewData('product');
    expect($viewProduct['id'])->toBe(778899);
    expect($viewProduct['title'])->toBe('Vintage Leather Jacket');
    expect($viewProduct['body'])->toBe('<p>Premium handmade jacket</p>');
    expect($viewProduct['status'])->toBe('active');
    expect($viewProduct['variants'][0]['inventory_quantity'])->toBe(12);
    expect($viewProduct['variants'][0]['sku'])->toBe('JACKET-MED');
});

it('Test 2: resolves location-specific inventory for selected location index in editProduct', function () {
    $shop = createEditShop([
        'selected_location_index' => 1, // Secondary Warehouse (10002)
    ]);

    $graphqlProduct = [
        'id' => 'gid://shopify/Product/555',
        'legacyResourceId' => '555',
        'title' => 'Multi-Location Sneaker',
        'variants' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductVariant/5551',
                    'legacyResourceId' => '5551',
                    'title' => 'Size 10',
                    'sku' => 'SNK-10',
                    'price' => '89.00',
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/6661',
                        'legacyResourceId' => '6661',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/10001',
                                        'legacyResourceId' => '10001',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 100],
                                    ],
                                ],
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/10002',
                                        'legacyResourceId' => '10002',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 7],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => Http::response([
            'data' => [
                'product' => $graphqlProduct,
            ],
        ], 200),
    ]);

    $response = $this->withSession(authEditSession($shop))
        ->get("/editProduct/555?shop={$shop->shop}");

    $response->assertStatus(200);
    $viewProduct = $response->viewData('product');
    // Location 10002 has quantity 7 (not 100 from location 10001)
    expect($viewProduct['variants'][0]['inventory_quantity'])->toBe(7);
});

it('Test 3: connects dbProduct and amazonData when product exists in local database', function () {
    $shop = createEditShop();

    $dbProduct = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 888999,
        'title' => 'Local Product Title',
        'sku' => 'LOCAL-SKU',
        'category_id' => 42,
        'sub_category_id' => 105,
    ]);

    $amazonProduct = AmazonProduct::create([
        'product_id' => $dbProduct->id,
        'amazon_title' => 'Amazon Listed Title',
        'sku' => 'AMZ-SKU',
    ]);

    $graphqlProduct = [
        'id' => 'gid://shopify/Product/888999',
        'legacyResourceId' => '888999',
        'title' => 'Local Product Title',
        'variants' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductVariant/9991',
                    'legacyResourceId' => '9991',
                    'title' => 'Default',
                    'sku' => 'LOCAL-SKU',
                    'price' => '25.00',
                ],
            ],
        ],
    ];

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => Http::response([
            'data' => [
                'product' => $graphqlProduct,
            ],
        ], 200),
    ]);

    $response = $this->withSession(authEditSession($shop))
        ->get("/editProduct/888999?shop={$shop->shop}");

    $response->assertStatus(200);
    $response->assertViewHas('dbProduct');
    $response->assertViewHas('amazonData');
    expect($response->viewData('dbProduct')->id)->toBe($dbProduct->id);
    expect($response->viewData('amazonData')->id)->toBe($amazonProduct->id);
});

it('Test 4: redirects to dashboard with error flash when product is not found on Shopify', function () {
    $shop = createEditShop();

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => Http::response([
            'data' => [
                'product' => null,
            ],
        ], 200),
    ]);

    $response = $this->withSession(authEditSession($shop))
        ->get("/editProduct/999999?shop={$shop->shop}");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Product not found');
    expect($response->headers->get('Location'))->toContain('/dashboard');
});

it('Test 5: handles GraphQL top-level errors and network failures by redirecting with error', function () {
    $shop = createEditShop();

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => Http::response([
            'errors' => [
                ['message' => 'Internal server error on Shopify GraphQL API'],
            ],
        ], 200),
    ]);

    $response = $this->withSession(authEditSession($shop))
        ->get("/editProduct/12345?shop={$shop->shop}");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Product not found');
    expect($response->headers->get('Location'))->toContain('/dashboard');
});

it('Test 6: enforces shop tenant isolation on editProduct route', function () {
    $shopA = createEditShop([
        'shop' => 'shop-a.myshopify.com',
    ]);
    $shopB = createEditShop([
        'shop' => 'shop-b.myshopify.com',
    ]);

    Http::fake([
        "https://shop-a.myshopify.com/admin/api/2026-07/graphql.json" => Http::response([
            'data' => [
                'product' => [
                    'id' => 'gid://shopify/Product/100',
                    'legacyResourceId' => '100',
                    'title' => 'Shop A Exclusive Item',
                    'variants' => ['nodes' => []],
                ],
            ],
        ], 200),
        "https://shop-b.myshopify.com/admin/api/2026-07/graphql.json" => Http::response([
            'data' => [
                'product' => null,
            ],
        ], 200),
    ]);

    // Shop A session fetching product 100 succeeds
    $responseA = $this->withSession(authEditSession($shopA))
        ->get("/editProduct/100?shop={$shopA->shop}");
    $responseA->assertStatus(200);
    $responseA->assertSee('Shop A Exclusive Item');
    expect($responseA->viewData('activeShop'))->toBe($shopA->shop);

    // Shop B session trying to fetch Shop A's product 100 fails on Shop B's store and redirects to dashboard
    $responseB = $this->withSession(authEditSession($shopB))
        ->get("/editProduct/100?shop={$shopB->shop}");
    $responseB->assertRedirect();
    $responseB->assertSessionHas('error', 'Product not found');
    expect($responseB->headers->get('Location'))->toContain('/dashboard');
});
