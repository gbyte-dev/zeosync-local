<?php

use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Services\ShopifyInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

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
            $table->string('amazon_seller_id')->nullable();
            $table->text('amazon_refresh_token')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
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
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('order_id')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('sync_status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_mappings')) {
        Schema::create('product_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_product_title')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->nullable()->index();
            $table->longText('option_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('sync_limit')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_sync_logs')) {
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    Product::query()->forceDelete();
    Shop::query()->forceDelete();
    ProductMarketplaceMapping::query()->delete();
    ProductMapping::query()->delete();
    ShopifyOrder::query()->delete();
    Cache::flush();
});

function createLinkTestShop(array $attributes = []): Shop
{
    $random = uniqid() . '-' . mt_rand(1000, 9999);
    return Shop::create(array_merge([
        'shop' => 'dash-link-' . $random . '.myshopify.com',
        'shop_name' => 'Link Test Store ' . $random,
        'email' => 'link' . $random . '@example.com',
        'access_token' => 'shpat_test_' . $random,
        'is_active' => true,
    ], $attributes));
}

function authLinkSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

test('renders clickable product names in Shopify Low Inventory pointing to shopify.product.view', function () {
    $shop = createLinkTestShop([
        'amazon_seller_id' => 'AMZ_SELLER_TEST',
        'amazon_refresh_token' => 'amz_refresh_test',
    ]);

    // Mock Shopify inventory service returning items with pid
    $mockInventoryService = Mockery::mock(ShopifyInventoryService::class);
    $mockInventoryService->shouldReceive('getInventory')->with(Mockery::on(fn($s) => $s->id === $shop->id))->andReturn([
        [
            'pid' => '9988776655',
            'vid' => '1122334455',
            'product' => 'Low Stock Shopify T-Shirt',
            'variant' => 'Small / Blue',
            'sku' => 'SHOPIFY-TSHIRT-S',
            'available' => 4,
            'qty' => 4,
        ],
        [
            'pid' => null, // item without pid to test fallback
            'vid' => '1122334456',
            'product' => 'No PID Shopify Mug',
            'variant' => 'Default',
            'sku' => 'SHOPIFY-MUG',
            'available' => 2,
            'qty' => 2,
        ]
    ]);
    app()->instance(ShopifyInventoryService::class, $mockInventoryService);

    $response = $this->withSession(authLinkSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);

    // Clickable link with pid
    $expectedUrl = route('shopify.product.view', ['id' => '9988776655', 'shop' => $shop->shop]);
    $response->assertSee('href="' . $expectedUrl . '"', false);
    $response->assertSee('Low Stock Shopify T-Shirt', false);

    // Item without pid is rendered as plain text without broken link
    $response->assertSee('No PID Shopify Mug', false);
});

test('renders clickable product names in Amazon Low Inventory (server-rendered) pointing to user.product.amazonView', function () {
    $shop = createLinkTestShop([
        'amazon_seller_id' => 'AMZ_SELLER_LINKS',
        'amazon_refresh_token' => 'amz_refresh_links',
    ]);

    // Populate Amazon cache
    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku' => 'AMZ-LOW-SKU-001',
            'title' => 'Amazon Low Stock Wireless Headphones',
            'quantity' => 3,
        ],
        [
            'sku' => null, // fallback edge case
            'title' => 'Amazon No SKU Cable',
            'quantity' => 1,
        ]
    ], 300);

    $response = $this->withSession(authLinkSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);

    // Clickable link with sku
    $expectedUrl = route('user.product.amazonView', ['sku' => 'AMZ-LOW-SKU-001', 'shop' => $shop->shop]);
    $response->assertSee('href="' . $expectedUrl . '"', false);
    $response->assertSee('Amazon Low Stock Wireless Headphones', false);

    // Item without SKU is rendered as plain text
    $response->assertSee('Amazon No SKU Cable', false);
});

test('includes the user.product.amazonView route template in dynamic JS loadAmazonInventory()', function () {
    $shop = createLinkTestShop([
        'amazon_seller_id' => 'AMZ_SELLER_JS',
        'amazon_refresh_token' => 'amz_refresh_js',
    ]);

    $response = $this->withSession(authLinkSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);

    // Verify JS contains route template replacement for detailUrl
    $jsTemplatePattern = route('user.product.amazonView', ['sku' => '__SKU__', 'shop' => $shop->shop]);
    $response->assertSee($jsTemplatePattern, false);
});

test('enforces multi-store isolation for product links across different shops', function () {
    $shopA = createLinkTestShop([
        'amazon_seller_id' => 'SELLER_A_' . uniqid(),
        'amazon_refresh_token' => 'refresh_a',
    ]);

    $shopB = createLinkTestShop([
        'amazon_seller_id' => 'SELLER_B_' . uniqid(),
        'amazon_refresh_token' => 'refresh_b',
    ]);

    // Store A cached Amazon product
    Cache::put("amazon_inventory_{$shopA->id}_{$shopA->amazon_seller_id}", [
        ['sku' => 'SKU-STORE-A', 'title' => 'Product for Store A', 'quantity' => 2],
    ], 300);

    // Store B cached Amazon product
    Cache::put("amazon_inventory_{$shopB->id}_{$shopB->amazon_seller_id}", [
        ['sku' => 'SKU-STORE-B', 'title' => 'Product for Store B', 'quantity' => 5],
    ], 300);

    // Request Store A dashboard
    $resA = $this->withSession(authLinkSession($shopA))
        ->get('/dashboard?shop=' . $shopA->shop);

    $urlA = route('user.product.amazonView', ['sku' => 'SKU-STORE-A', 'shop' => $shopA->shop]);
    $resA->assertStatus(200);
    $resA->assertSee($urlA, false);
    $resA->assertDontSee($shopB->shop, false);

    // Request Store B dashboard
    $resB = $this->withSession(authLinkSession($shopB))
        ->get('/dashboard?shop=' . $shopB->shop);

    $urlB = route('user.product.amazonView', ['sku' => 'SKU-STORE-B', 'shop' => $shopB->shop]);
    $resB->assertStatus(200);
    $resB->assertSee($urlB, false);
    $resB->assertDontSee($shopA->shop, false);
});
