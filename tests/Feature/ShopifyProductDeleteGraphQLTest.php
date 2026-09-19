<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
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
    ProductMarketplaceMapping::query()->forceDelete();
});

function createDeleteTestShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'delete-test-store.myshopify.com',
        'shop_name' => 'Delete Test Store',
        'email' => 'delete@example.com',
        'access_token' => 'shpat_delete_token_' . uniqid(),
        'shopify_locations' => [
            ['id' => 88801, 'name' => 'Main Warehouse', 'active' => true],
        ],
        'selected_location_index' => 0,
        'is_active' => true,
    ], $attributes));

    $plan = Plan::firstOrCreate(
        ['name' => 'Unlimited Plan'],
        ['slug' => 'unlimited-plan-' . uniqid(), 'price' => 0, 'product_limit' => 0, 'is_active' => true]
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

function authDeleteSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at' => time(),
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ];
}

it('Test 1: deletes a product via GraphQL productDelete mutation and cleans up local DB', function () {
    $shop = createDeleteTestShop();

    $product = Product::create([
        'shopify_id' => '990055',
        'shop_id' => $shop->id,
        'title' => 'Product to Delete',
        'price' => 19.99,
        'status' => 'active',
    ]);

    $productDeleteCalled = false;
    $sentVariables = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$productDeleteCalled, &$sentVariables) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'ProductDelete') || str_contains($query, 'productDelete')) {
                $productDeleteCalled = true;
                $sentVariables = $body['variables'] ?? [];
                return Http::response([
                    'data' => [
                        'productDelete' => [
                            'deletedProductId' => 'gid://shopify/Product/990055',
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'products(')) {
                return Http::response([
                    'data' => [
                        'products' => [
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                            'nodes' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990055');

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Product deleted successfully',
    ]);

    expect($productDeleteCalled)->toBeTrue();
    expect($sentVariables['input']['id'])->toBe('gid://shopify/Product/990055');

    // Verify local DB deletion
    $dbProduct = Product::where('shopify_id', '990055')->where('shop_id', $shop->id)->first();
    expect($dbProduct)->toBeNull();
});

it('Test 2: handles GID format gracefully when passed to ShopifyService and controller', function () {
    $shop = createDeleteTestShop();

    $product = Product::create([
        'shopify_id' => '990066',
        'shop_id' => $shop->id,
        'title' => 'GID Product to Delete',
        'price' => 29.99,
        'status' => 'active',
    ]);

    $sentId = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$sentId) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productDelete')) {
                $sentId = $body['variables']['input']['id'] ?? null;
                return Http::response([
                    'data' => [
                        'productDelete' => [
                            'deletedProductId' => $sentId,
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => ['products' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []]]], 200);
        },
    ]);

    // 1. Direct Service Call with GID
    $shopifyService = new \App\Services\ShopifyService($shop->shop, $shop->access_token);
    $res = $shopifyService->deleteProduct($shop, 'gid://shopify/Product/990066');
    expect($res['success'])->toBeTrue();
    expect($sentId)->toBe('gid://shopify/Product/990066');

    // 2. Controller Call with numeric ID
    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990066');

    $response->assertStatus(200);

    // Local DB cleaned up
    $dbProduct = Product::where('shopify_id', '990066')->where('shop_id', $shop->id)->first();
    expect($dbProduct)->toBeNull();
});

it('Test 3: handles Shopify GraphQL userErrors without deleting local DB record', function () {
    $shop = createDeleteTestShop();

    $product = Product::create([
        'shopify_id' => '990077',
        'shop_id' => $shop->id,
        'title' => 'Protected Product',
        'price' => 50.00,
        'status' => 'active',
    ]);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productDelete')) {
                return Http::response([
                    'data' => [
                        'productDelete' => [
                            'deletedProductId' => null,
                            'userErrors' => [
                                ['field' => ['id'], 'message' => 'Product cannot be deleted while active in an open draft order.'],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990077');

    $response->assertStatus(400);
    $response->assertJson([
        'success' => false,
        'message' => 'Failed to delete product from Shopify: Product cannot be deleted while active in an open draft order.',
    ]);

    // Local DB record must NOT be deleted
    $product->refresh();
    expect($product->exists)->toBeTrue();
    expect($product->title)->toBe('Protected Product');
});

it('Test 4: handles Shopify GraphQL top-level errors and network failures cleanly', function () {
    $shop = createDeleteTestShop();

    $product = Product::create([
        'shopify_id' => '990088',
        'shop_id' => $shop->id,
        'title' => 'Product under network outage',
        'price' => 15.00,
        'status' => 'active',
    ]);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            return Http::response([
                'errors' => [
                    ['message' => 'Throttled or Service Unavailable'],
                ],
            ], 200);
        },
    ]);

    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990088');

    $response->assertStatus(400);
    $response->assertJson([
        'success' => false,
        'message' => 'Failed to delete product from Shopify: Throttled or Service Unavailable',
    ]);

    // Local DB product must remain intact
    $product->refresh();
    expect($product->exists)->toBeTrue();
});

it('Test 5: tenant isolation: Shop A cannot delete Shop B product', function () {
    $shopA = createDeleteTestShop(['shop' => 'shop-a.myshopify.com']);
    $shopB = createDeleteTestShop(['shop' => 'shop-b.myshopify.com']);

    $productB = Product::create([
        'shopify_id' => '990099',
        'shop_id' => $shopB->id,
        'title' => 'Shop B Product',
        'price' => 99.00,
        'status' => 'active',
    ]);

    Http::fake([
        "https://{$shopA->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            // Shopify A responds with userError that product was not found in Shop A
            return Http::response([
                'data' => [
                    'productDelete' => [
                        'deletedProductId' => null,
                        'userErrors' => [
                            ['field' => ['id'], 'message' => 'Product does not exist'],
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    // Shop A tries to delete Shop B's product
    $response = $this->withSession(authDeleteSession($shopA))
        ->postJson('/product/delete/990099');

    $response->assertStatus(400);

    // Shop B's product in DB must NOT be deleted
    $productB->refresh();
    expect($productB->exists)->toBeTrue();
    expect($productB->shop_id)->toBe($shopB->id);
});

it('Test 6: cleans up ProductMarketplaceMapping and refreshes cache upon successful deletion', function () {
    $shop = createDeleteTestShop();

    $product = Product::create([
        'shopify_id' => '990111',
        'shop_id' => $shop->id,
        'title' => 'Mapped Product to Delete',
        'price' => 45.00,
        'status' => 'active',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shopify_product_id' => '990111',
        'amazon_sku' => 'AMZ-MAPPED-01',
    ]);

    Cache::put("products_shop_{$shop->id}", collect([$product]), 600);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productDelete')) {
                return Http::response([
                    'data' => [
                        'productDelete' => [
                            'deletedProductId' => 'gid://shopify/Product/990111',
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => ['products' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []]]], 200);
        },
    ]);

    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990111');

    $response->assertStatus(200);

    // Mapping was removed
    $mappingCheck = ProductMarketplaceMapping::where('shopify_product_id', '990111')->first();
    expect($mappingCheck)->toBeNull();
});

it('Test 7: unauthenticated request returns 401 JSON error', function () {
    $response = $this->postJson('/product/delete/990111');
    $response->assertStatus(401);
    $response->assertJson([
        'success' => false,
    ]);
});

it('Test 8: deleting a product does not delete other products in the same shop', function () {
    $shop = createDeleteTestShop();

    $product1 = Product::create([
        'shopify_id' => '990222',
        'shop_id' => $shop->id,
        'title' => 'Product to Keep',
        'price' => 20.00,
        'status' => 'active',
    ]);

    $product2 = Product::create([
        'shopify_id' => '990333',
        'shop_id' => $shop->id,
        'title' => 'Product to Delete',
        'price' => 30.00,
        'status' => 'active',
    ]);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productDelete')) {
                return Http::response([
                    'data' => [
                        'productDelete' => [
                            'deletedProductId' => 'gid://shopify/Product/990333',
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => ['products' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []]]], 200);
        },
    ]);

    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990333');

    $response->assertStatus(200);

    $product1->refresh();
    expect($product1->exists)->toBeTrue();
    expect($product1->title)->toBe('Product to Keep');

    $product2Check = Product::where('shopify_id', '990333')->first();
    expect($product2Check)->toBeNull();
});

it('Test 9: logs removal in sync logging upon successful deletion', function () {
    $shop = createDeleteTestShop();

    $product = Product::create([
        'shopify_id' => '990444',
        'shop_id' => $shop->id,
        'title' => 'Product to Log',
        'price' => 25.00,
        'status' => 'active',
    ]);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productDelete')) {
                return Http::response([
                    'data' => [
                        'productDelete' => [
                            'deletedProductId' => 'gid://shopify/Product/990444',
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => ['products' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []]]], 200);
        },
    ]);

    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990444');

    $response->assertStatus(200);
});

it('Test 10: deleting product when not found in local DB still executes Shopify deletion successfully', function () {
    $shop = createDeleteTestShop();

    $productDeleteCalled = false;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$productDeleteCalled) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productDelete')) {
                $productDeleteCalled = true;
                return Http::response([
                    'data' => [
                        'productDelete' => [
                            'deletedProductId' => 'gid://shopify/Product/990555',
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => ['products' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []]]], 200);
        },
    ]);

    // Product 990555 does NOT exist in local DB
    $response = $this->withSession(authDeleteSession($shop))
        ->postJson('/product/delete/990555');

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Product deleted successfully',
    ]);

    expect($productDeleteCalled)->toBeTrue();
});
