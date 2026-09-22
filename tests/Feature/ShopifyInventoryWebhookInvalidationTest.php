<?php

use App\Http\Controllers\ShopifyController;
use App\Models\Shop;
use App\Services\ShopifyInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);
    \Illuminate\Support\Facades\DB::purge();
    \Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('shop_name')->nullable();
            $table->string('domain')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('selected_location_id')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->boolean('is_active')->default(1);
            $table->string('store_status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    Cache::flush();
});

function createTestShop(string $domain): Shop
{
    return Shop::create([
        'shop' => $domain,
        'shop_name' => explode('.', $domain)[0],
        'access_token' => 'shpat_test_token',
        'is_active' => 1,
        'store_status' => 'active',
        'selected_location_index' => 0,
        'shopify_locations' => [
            ['id' => 'gid://shopify/Location/12345', 'name' => 'Main Location'],
        ],
    ]);
}

function calculateShopifyHmac(string $payload, string $secret = 'test-api-secret'): string
{
    return base64_encode(hash_hmac('sha256', $payload, $secret, true));
}

test('1. products/create webhook invalidates affected shop inventory cache', function () {
    $shop = createTestShop('store-a.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['sample_inventory_data'], 600);

    expect(Cache::has($cacheKey))->toBeTrue();

    $payload = json_encode(['id' => 101, 'title' => 'New Product']);
    $hmac = calculateShopifyHmac($payload);

    $req = Request::create('/webhooks/shopify/products/create', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $controller = app(ShopifyController::class);
    $response = $controller->handleProductsCreateWebhook($req);

    expect($response->getStatusCode())->toBe(200);
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('2. products/update webhook invalidates affected shop inventory cache', function () {
    $shop = createTestShop('store-a.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['sample_inventory_data'], 600);

    $payload = json_encode(['id' => 101, 'title' => 'Updated Product']);
    $hmac = calculateShopifyHmac($payload);

    $req = Request::create('/webhooks/shopify/products/update', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $controller = app(ShopifyController::class);
    $response = $controller->handleProductsUpdateWebhook($req);

    expect($response->getStatusCode())->toBe(200);
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('3. products/delete webhook invalidates affected shop inventory cache', function () {
    $shop = createTestShop('store-a.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['sample_inventory_data'], 600);

    $payload = json_encode(['id' => 101]);
    $hmac = calculateShopifyHmac($payload);

    $req = Request::create('/webhooks/shopify/products/delete', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $controller = app(ShopifyController::class);
    $response = $controller->handleProductsDeleteWebhook($req);

    expect($response->getStatusCode())->toBe(200);
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('4. inventory_levels/update webhook invalidates affected shop inventory cache', function () {
    $shop = createTestShop('store-a.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['sample_inventory_data'], 600);

    $payload = json_encode(['inventory_item_id' => 5001, 'location_id' => 12345, 'available' => 42]);
    $hmac = calculateShopifyHmac($payload);

    $req = Request::create('/webhooks/shopify/inventory_levels/update', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $controller = app(ShopifyController::class);
    $response = $controller->handleInventoryLevelsUpdateWebhook($req);

    expect($response->getStatusCode())->toBe(200);
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('5. Multi-tenant isolation: Shop A webhook invalidates Shop A cache ONLY while Shop B cache remains untouched', function () {
    $shopA = createTestShop('shop-alpha.myshopify.com');
    $shopB = createTestShop('shop-beta.myshopify.com');

    $cacheKeyA = "shopify_inventory_{$shopA->shop}_location_0";
    $cacheKeyB = "shopify_inventory_{$shopB->shop}_location_0";

    Cache::put($cacheKeyA, ['shop_a_inventory'], 600);
    Cache::put($cacheKeyB, ['shop_b_inventory'], 600);

    expect(Cache::has($cacheKeyA))->toBeTrue();
    expect(Cache::has($cacheKeyB))->toBeTrue();

    // Trigger webhook for Shop A
    $payload = json_encode(['id' => 999]);
    $hmac = calculateShopifyHmac($payload);

    $req = Request::create('/webhooks/shopify/inventory_levels/update', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shopA->shop,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $controller = app(ShopifyController::class);
    $response = $controller->handleInventoryLevelsUpdateWebhook($req);

    expect($response->getStatusCode())->toBe(200);

    // Shop A cache is cleared, Shop B cache remains intact
    expect(Cache::has($cacheKeyA))->toBeFalse();
    expect(Cache::has($cacheKeyB))->toBeTrue();
    expect(Cache::get($cacheKeyB))->toEqual(['shop_b_inventory']);
});

test('6. Invalid HMAC signature is rejected with 401 and does not clear cache', function () {
    $shop = createTestShop('store-a.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['sample_inventory_data'], 600);

    $payload = json_encode(['id' => 101]);
    $invalidHmac = 'invalid_hmac_hash_value';

    $req = Request::create('/webhooks/shopify/products/update', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $invalidHmac,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $controller = app(ShopifyController::class);
    $response = $controller->handleProductsUpdateWebhook($req);

    expect($response->getStatusCode())->toBe(401);
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('7. Unknown shop webhook returns 200 without error and does not touch existing shop cache', function () {
    $shopA = createTestShop('store-a.myshopify.com');
    $cacheKeyA = "shopify_inventory_{$shopA->shop}_location_0";
    Cache::put($cacheKeyA, ['sample_inventory_data'], 600);

    $payload = json_encode(['id' => 101]);
    $hmac = calculateShopifyHmac($payload);

    $req = Request::create('/webhooks/shopify/products/update', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'nonexistent-shop.myshopify.com',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $controller = app(ShopifyController::class);
    $response = $controller->handleProductsUpdateWebhook($req);

    expect($response->getStatusCode())->toBe(200);
    expect(Cache::has($cacheKeyA))->toBeTrue();
});

test('8. Duplicate webhook delivery is idempotent', function () {
    $shop = createTestShop('store-a.myshopify.com');
    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['sample_inventory_data'], 600);

    $payload = json_encode(['id' => 101]);
    $hmac = calculateShopifyHmac($payload);

    $controller = app(ShopifyController::class);

    for ($i = 0; $i < 3; $i++) {
        $req = Request::create('/webhooks/shopify/inventory_levels/update', 'POST', [], [], [], [
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response = $controller->handleInventoryLevelsUpdateWebhook($req);
        expect($response->getStatusCode())->toBe(200);
    }

    expect(Cache::has($cacheKey))->toBeFalse();
});
