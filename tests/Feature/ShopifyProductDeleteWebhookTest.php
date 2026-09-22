<?php

use App\Models\AdminSetting;
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

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');
    AdminSetting::forget('SHOPIFY_APP_URL');

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

// CASE 3: Valid products/delete webhook → HMAC passes → correct shop resolved → only that shop cache invalidated → HTTP 200.
test('case 3: valid products/delete webhook verifies HMAC and invalidates shop inventory cache', function () {
    $shop = createWebhookTestShop('test-store.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['product_1' => 'sample'], 600);
    expect(Cache::has($cacheKey))->toBeTrue();

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
    expect(Cache::has($cacheKey))->toBeFalse();
});

// CASE 4: Invalid HMAC → HTTP 401 → cache remains untouched.
test('case 4: invalid HMAC returns 401 and leaves inventory cache intact', function () {
    $shop = createWebhookTestShop('secure-store.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['cached_data' => true], 600);

    $payload = json_encode(['id' => 9999]);
    $badHmac = base64_encode('invalid_signature');

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $badHmac,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(401);
    expect(Cache::has($cacheKey))->toBeTrue();
});

// CASE 5: Shop A webhook → Shop A cache invalidated → Shop B cache remains unchanged.
test('case 5: Shop A webhook invalidates Shop A cache while Shop B cache remains untouched', function () {
    $shopA = createWebhookTestShop('shop-alpha.myshopify.com');
    $shopB = createWebhookTestShop('shop-beta.myshopify.com');

    $cacheKeyA = "shopify_inventory_{$shopA->shop}_location_0";
    $cacheKeyB = "shopify_inventory_{$shopB->shop}_location_0";

    Cache::put($cacheKeyA, ['data_a' => 1], 600);
    Cache::put($cacheKeyB, ['data_b' => 2], 600);

    $payload = json_encode(['id' => 5555]);
    $hmacA = generateShopifyHmac($payload);

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => $shopA->shop,
        'X-Shopify-Hmac-Sha256' => $hmacA,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(200);
    expect(Cache::has($cacheKeyA))->toBeFalse();
    expect(Cache::has($cacheKeyB))->toBeTrue();
});

// CASE 6: Same webhook delivered twice → no harmful side effects.
test('case 6: duplicate webhook delivery is safe and returns HTTP 200', function () {
    $shop = createWebhookTestShop('retry-store.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['item' => 'val'], 600);

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

    // Second delivery (retry)
    $response2 = $this->withHeaders($headers)->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));
    $response2->assertStatus(200);
    expect(Cache::has($cacheKey))->toBeFalse();
});

// CASE 7: Unknown shop domain → no other shop cache is touched.
test('case 7: unknown shop domain handles safely without touching other shop caches', function () {
    $knownShop = createWebhookTestShop('known-store.myshopify.com');
    $knownCacheKey = "shopify_inventory_{$knownShop->shop}_location_0";
    Cache::put($knownCacheKey, ['data' => 'keep'], 600);

    $payload = json_encode(['id' => 8888]);
    $hmac = generateShopifyHmac($payload);

    $response = $this->withHeaders([
        'X-Shopify-Shop-Domain' => 'unknown-store.myshopify.com',
        'X-Shopify-Hmac-Sha256' => $hmac,
        'Content-Type' => 'application/json',
    ])->postJson(route('shopify.webhooks.products.delete'), json_decode($payload, true));

    $response->assertStatus(200);
    expect(Cache::has($knownCacheKey))->toBeTrue();
});

// CASE 8: Webhook registration API failure → throws RuntimeException and logs error cleanly.
test('case 8: webhook registration GraphQL userErrors throws RuntimeException', function () {
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
