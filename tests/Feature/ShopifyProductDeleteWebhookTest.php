<?php

use App\Models\AdminSetting;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ShopifyInventoryService;
use App\Services\ShopifyWebhookService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key' => 'test_api_key',
        'services.shopify.api_secret' => 'test_webhook_secret',
        'services.shopify.api_version' => '2026-07',
        'services.shopify.app_url' => 'https://zeosync.app',
        'services.shopify.redirect_uri' => 'https://zeosync.app/callback',
    ]);

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
            $table->string('selected_location_id')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
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
            $table->string('shopify_status')->nullable();
            $table->text('shopify_error')->nullable();
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

    AdminSetting::query()->whereIn('option_key', ['SHOPIFY_API_KEY', 'SHOPIFY_API_SECRET', 'SHOPIFY_APP_URL'])->delete();
    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');
    AdminSetting::forget('SHOPIFY_APP_URL');

    Product::query()->forceDelete();
    Shop::query()->forceDelete();
});

function createWebhookTestShop(string $domain = 'shop-a.myshopify.com', array $attributes = []): Shop
{
    return Shop::create(array_merge([
        'shop' => $domain,
        'shop_name' => 'Shop Test',
        'email' => 'test@' . $domain,
        'access_token' => 'shpat_test_token_' . uniqid(),
        'shopify_locations' => [
            ['id' => 1001, 'name' => 'Main Location', 'active' => true],
        ],
        'selected_location_index' => 0,
        'is_active' => true,
    ], $attributes));
}

function generateShopifyHmac(string $payload, string $secret = 'test_webhook_secret'): string
{
    return base64_encode(hash_hmac('sha256', $payload, $secret, true));
}

// CASE 1: First installation → PRODUCTS_DELETE does not exist → exactly one subscription is created.
test('case 1: first installation registers PRODUCTS_DELETE webhook when none exists', function () {
    $shop = createWebhookTestShop();
    $webhookService = new ShopifyWebhookService();
    $targetUrl = $webhookService->buildProductsDeleteWebhookUrl();

    Http::fake([
        'https://' . $shop->shop . '/admin/api/*/graphql.json' => function ($request) use ($targetUrl) {
            $body = $request->body();

            if (str_contains($body, 'ProductsDeleteWebhooks')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptions' => [
                            'edges' => [],
                        ],
                    ],
                ]);
            }

            if (str_contains($body, 'WebhookSubscriptionCreate')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptionCreate' => [
                            'webhookSubscription' => [
                                'id' => 'gid://shopify/WebhookSubscription/99001',
                                'topic' => 'PRODUCTS_DELETE',
                            ],
                            'userErrors' => [],
                        ],
                    ],
                ]);
            }

            return Http::response([], 200);
        },
    ]);

    $webhookService->ensureProductsDeleteWebhook($shop);

    Http::assertSent(function ($request) {
        return str_contains($request->body(), 'WebhookSubscriptionCreate')
            && str_contains($request->body(), 'PRODUCTS_DELETE');
    });
});

// CASE 2: Repeated installation → existing PRODUCTS_DELETE found → no duplicate subscription created.
test('case 2: repeated installation does not create duplicate webhook subscription', function () {
    $shop = createWebhookTestShop();
    $webhookService = new ShopifyWebhookService();
    $targetUrl = $webhookService->buildProductsDeleteWebhookUrl();

    Http::fake([
        'https://' . $shop->shop . '/admin/api/*/graphql.json' => function ($request) use ($targetUrl) {
            $body = $request->body();

            if (str_contains($body, 'ProductsDeleteWebhooks')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptions' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/WebhookSubscription/99001',
                                        'topic' => 'PRODUCTS_DELETE',
                                        'endpoint' => [
                                            '__typename' => 'WebhookHttpEndpoint',
                                            'callbackUrl' => $targetUrl,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response(['error' => 'should not be called'], 500);
        },
    ]);

    $webhookService->ensureProductsDeleteWebhook($shop);

    Http::assertNotSent(function ($request) {
        return str_contains($request->body(), 'WebhookSubscriptionCreate');
    });
});

// CASE 3: Valid products/delete webhook → soft-deletes matching DB product, invalidates both caches, and returns HTTP 200.
test('case 3: valid products/delete webhook soft-deletes DB product and invalidates both product and inventory caches', function () {
    $shop = createWebhookTestShop('test-store.myshopify.com');

    // Create local product for this shop
    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 1234567890,
        'title' => 'Product To Delete',
        'price' => 29.99,
        'status' => 'active',
    ]);

    $inventoryCacheKey = "shopify_inventory_{$shop->shop}_location_0";
    $productCacheKey = "products_shop_{$shop->id}";
    Cache::put($inventoryCacheKey, ['product_1' => 'sample'], 600);
    Cache::put($productCacheKey, [$product->toArray()], 600);

    expect(Cache::has($inventoryCacheKey))->toBeTrue();
    expect(Cache::has($productCacheKey))->toBeTrue();

    $payload = json_encode(['id' => 1234567890]);
    $hmac = generateShopifyHmac($payload);

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac,
        'X-Shopify-Webhook-Id' => 'wh_evt_1001',
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    // Product is soft-deleted
    expect(Product::where('id', $product->id)->exists())->toBeFalse();
    expect(Product::withTrashed()->where('id', $product->id)->exists())->toBeTrue();
    expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();

    // Both caches invalidated
    expect(Cache::has($inventoryCacheKey))->toBeFalse();
    expect(Cache::has($productCacheKey))->toBeFalse();
});

// CASE 4: Soft-deletion verification: DB record is not permanently deleted.
test('case 4: soft-deleted product is preserved with softDeletes and not force-deleted', function () {
    $shop = createWebhookTestShop('softdelete-store.myshopify.com');

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 88889999,
        'title' => 'Soft Delete Product',
        'price' => 49.99,
        'status' => 'active',
    ]);

    $payload = json_encode(['id' => 88889999]);
    $hmac = generateShopifyHmac($payload);

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(200);

    // Active count is 0, withTrashed count is 1
    expect(Product::where('shop_id', $shop->id)->count())->toBe(0);
    expect(Product::withTrashed()->where('shop_id', $shop->id)->count())->toBe(1);
});

// CASE 5: Product not found in DB handles cleanly, invalidates caches, and returns HTTP 200.
test('case 5: product not found in DB handles cleanly and invalidates caches', function () {
    $shop = createWebhookTestShop('notfound-store.myshopify.com');

    $inventoryCacheKey = "shopify_inventory_{$shop->shop}_location_0";
    $productCacheKey = "products_shop_{$shop->id}";
    Cache::put($inventoryCacheKey, ['dummy' => 1], 600);
    Cache::put($productCacheKey, ['dummy' => 2], 600);

    $payload = json_encode(['id' => 9999999999]);
    $hmac = generateShopifyHmac($payload);

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    expect(Cache::has($inventoryCacheKey))->toBeFalse();
    expect(Cache::has($productCacheKey))->toBeFalse();
});

// CASE 6: Invalid HMAC → HTTP 401 → DB product and caches remain untouched.
test('case 6: invalid HMAC returns 401 and leaves DB product and caches intact', function () {
    $shop = createWebhookTestShop('secure-store.myshopify.com');

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 7777777,
        'title' => 'Protected Product',
        'price' => 15.00,
        'status' => 'active',
    ]);

    $inventoryCacheKey = "shopify_inventory_{$shop->shop}_location_0";
    $productCacheKey = "products_shop_{$shop->id}";
    Cache::put($inventoryCacheKey, ['cached_data' => true], 600);
    Cache::put($productCacheKey, [$product->toArray()], 600);

    $payload = json_encode(['id' => 7777777]);
    $badHmac = base64_encode('invalid_signature');

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $badHmac,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(401);

    // DB product not deleted
    expect(Product::where('id', $product->id)->exists())->toBeTrue();
    // Caches untouched
    expect(Cache::has($inventoryCacheKey))->toBeTrue();
    expect(Cache::has($productCacheKey))->toBeTrue();
});

// CASE 7: Tenant isolation: Shop A webhook soft-deletes Shop A product and invalidates Shop A caches, leaving Shop B untouched.
test('case 7: tenant isolation: Shop A webhook only affects Shop A product and caches', function () {
    $shopA = createWebhookTestShop('shop-alpha.myshopify.com');
    $shopB = createWebhookTestShop('shop-beta.myshopify.com');

    $productA = Product::create([
        'shop_id' => $shopA->id,
        'shopify_id' => 5555,
        'title' => 'Shop A Product',
        'price' => 10.00,
        'status' => 'active',
    ]);

    $productB = Product::create([
        'shop_id' => $shopB->id,
        'shopify_id' => 6666,
        'title' => 'Shop B Product',
        'price' => 20.00,
        'status' => 'active',
    ]);

    $inventoryCacheKeyA = "shopify_inventory_{$shopA->shop}_location_0";
    $inventoryCacheKeyB = "shopify_inventory_{$shopB->shop}_location_0";
    $productCacheKeyA = "products_shop_{$shopA->id}";
    $productCacheKeyB = "products_shop_{$shopB->id}";

    Cache::put($inventoryCacheKeyA, ['data_a' => 1], 600);
    Cache::put($inventoryCacheKeyB, ['data_b' => 2], 600);
    Cache::put($productCacheKeyA, [$productA->toArray()], 600);
    Cache::put($productCacheKeyB, [$productB->toArray()], 600);

    $payload = json_encode(['id' => 5555]);
    $hmacA = generateShopifyHmac($payload);

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => $shopA->shop,
        'X-Shopify-Hmac-Sha256' => $hmacA,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(200);

    // Shop A product soft-deleted
    expect(Product::where('id', $productA->id)->exists())->toBeFalse();
    expect(Product::withTrashed()->where('id', $productA->id)->exists())->toBeTrue();

    // Shop B product still active
    expect(Product::where('id', $productB->id)->exists())->toBeTrue();
    expect(Product::where('id', $productB->id)->first()->trashed())->toBeFalse();

    // Shop A caches invalidated
    expect(Cache::has($inventoryCacheKeyA))->toBeFalse();
    expect(Cache::has($productCacheKeyA))->toBeFalse();

    // Shop B caches untouched
    expect(Cache::has($inventoryCacheKeyB))->toBeTrue();
    expect(Cache::has($productCacheKeyB))->toBeTrue();
});

// CASE 8: Duplicate webhook delivery is safe and idempotent.
test('case 8: duplicate webhook delivery is safe and idempotent', function () {
    $shop = createWebhookTestShop('retry-store.myshopify.com');

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 7777,
        'title' => 'Retry Test Product',
        'price' => 12.00,
        'status' => 'active',
    ]);

    $inventoryCacheKey = "shopify_inventory_{$shop->shop}_location_0";
    $productCacheKey = "products_shop_{$shop->id}";
    Cache::put($inventoryCacheKey, ['item' => 'val'], 600);
    Cache::put($productCacheKey, [$product->toArray()], 600);

    $payload = json_encode(['id' => 7777]);
    $hmac = generateShopifyHmac($payload);

    $headers = [
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac,
        'X-Shopify-Webhook-Id' => 'wh_retry_1',
        'Content-Type' => 'application/json',
    ];

    // First delivery
    $response1 = $this->withHeaders($headers)->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));
    $response1->assertStatus(200);
    expect(Product::where('id', $product->id)->exists())->toBeFalse();

    // Second delivery (retry)
    $response2 = $this->withHeaders($headers)->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));
    $response2->assertStatus(200);
    expect(Product::withTrashed()->where('id', $product->id)->exists())->toBeTrue();

    expect(Cache::has($inventoryCacheKey))->toBeFalse();
    expect(Cache::has($productCacheKey))->toBeFalse();
});

// CASE 9: Unknown shop domain handles safely without touching other shop products or caches.
test('case 9: unknown shop domain handles safely without touching other shop data', function () {
    $knownShop = createWebhookTestShop('known-store.myshopify.com');

    $knownProduct = Product::create([
        'shop_id' => $knownShop->id,
        'shopify_id' => 8888,
        'title' => 'Known Store Product',
        'price' => 99.00,
        'status' => 'active',
    ]);

    $knownInventoryKey = "shopify_inventory_{$knownShop->shop}_location_0";
    $knownProductKey = "products_shop_{$knownShop->id}";
    Cache::put($knownInventoryKey, ['data' => 'keep'], 600);
    Cache::put($knownProductKey, [$knownProduct->toArray()], 600);

    $payload = json_encode(['id' => 8888]);
    $hmac = generateShopifyHmac($payload);

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => 'unknown-store.myshopify.com',
        'X-Shopify-Hmac-Sha256' => $hmac,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(200);

    // Known product untouched
    expect(Product::where('id', $knownProduct->id)->exists())->toBeTrue();

    // Known caches untouched
    expect(Cache::has($knownInventoryKey))->toBeTrue();
    expect(Cache::has($knownProductKey))->toBeTrue();
});

// CASE 10: Webhook registration API failure → throws RuntimeException and logs error cleanly.
test('case 10: webhook registration GraphQL userErrors throws RuntimeException', function () {
    $shop = createWebhookTestShop('error-store.myshopify.com');
    $webhookService = new ShopifyWebhookService();

    Http::fake([
        'https://' . $shop->shop . '/admin/api/*/graphql.json' => function ($request) {
            $body = $request->body();

            if (str_contains($body, 'ProductsDeleteWebhooks')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptions' => [
                            'edges' => [],
                        ],
                    ],
                ]);
            }

            if (str_contains($body, 'WebhookSubscriptionCreate')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptionCreate' => [
                            'webhookSubscription' => null,
                            'userErrors' => [
                                [
                                    'field' => ['webhookSubscription', 'callbackUrl'],
                                    'message' => 'Invalid callback URL',
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response([], 200);
        },
    ]);

    expect(fn () => $webhookService->ensureProductsDeleteWebhook($shop))
        ->toThrow(\RuntimeException::class, 'Invalid callback URL');
});
