<?php

use App\Models\AllProduct;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopifyOrder;
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

    if (!Schema::hasTable('allproducts')) {
        Schema::create('allproducts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schema_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('submission_status')->nullable();
            $table->timestamp('submitted_on')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
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
            $table->string('sync_status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('order_id')->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    Product::query()->forceDelete();
    AllProduct::query()->delete();
    Shop::query()->forceDelete();
    ProductMarketplaceMapping::query()->delete();
    ProductMapping::query()->delete();
    ShopifyOrder::query()->delete();
});

function createDashboardTestShop(array $attributes = []): Shop
{
    return Shop::create(array_merge([
        'shop' => 'dash-test-' . uniqid() . '.myshopify.com',
        'shop_name' => 'Dashboard Test Store',
        'email' => 'dash@example.com',
        'access_token' => 'shpat_test_' . uniqid(),
        'is_active' => true,
    ], $attributes));
}

function authDashboardSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

test('dashboard accurately displays all six cards for a shop with amazon connected', function () {
    $shopA = createDashboardTestShop([
        'shop' => 'store-a.myshopify.com',
        'shop_name' => 'Store A',
        'amazon_seller_id' => 'SELLER_A_123',
        'amazon_refresh_token' => 'dummy_refresh_token_a',
    ]);

    // Shopify Products for Shop A
    Product::create(['title' => 'Shopify Prod A1', 'shop_id' => $shopA->id]);
    Product::create(['title' => 'Shopify Prod A2', 'shop_id' => $shopA->id]);
    Product::create(['title' => 'Shopify Prod A3', 'shop_id' => $shopA->id]);

    // Mapped Products for Shop A
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '111']);
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '222']);

    // Amazon Products for Shop A (user_id = shopA->id)
    AllProduct::create(['sku' => 'AMZ-SKU-1', 'user_id' => $shopA->id, 'status' => 'draft']);
    AllProduct::create(['sku' => 'AMZ-SKU-2', 'user_id' => $shopA->id, 'submission_status' => 'SUCCESS']);

    // Shopify Orders for Shop A
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-1']);
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-2']);
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-3']);
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-4']);

    // Amazon Orders in Cache for Shop A
    Cache::put('amazon_orders_store-a.myshopify.com_SELLER_A_123', [
        ['AmazonOrderId' => 'AMZ-ORD-1'],
        ['AmazonOrderId' => 'AMZ-ORD-2'],
        ['AmazonOrderId' => 'AMZ-ORD-3'],
        ['AmazonOrderId' => 'AMZ-ORD-4'],
        ['AmazonOrderId' => 'AMZ-ORD-5'],
    ], 3600);

    $response = $this->withSession(authDashboardSession($shopA))
        ->get('/dashboard?shop=' . $shopA->shop);

    $response->assertStatus(200);

    // Verify all 6 card titles are present
    $response->assertSee('Shopify Products');
    $response->assertSee('Mapped Products');
    $response->assertSee('Amazon Orders');
    $response->assertSee('Amazon Products');
    $response->assertSee('Shopify Orders');
    $response->assertSee('Amazon Status');

    // Verify view received exact counts and status
    $response->assertViewHas('totalShopifyProducts', 3);
    $response->assertViewHas('totalMappedProducts', 2);
    $response->assertViewHas('totalAmazonOrders', 5);
    $response->assertViewHas('totalAmazonProducts', 2);
    $response->assertViewHas('totalShopifyOrders', 4);
    $response->assertViewHas('isAmazonConnected', true);

    // Verify Connection Status in HTML
    $response->assertSee('● Connected');
});

test('dashboard accurately displays all six cards for a shop with amazon disconnected', function () {
    $shopB = createDashboardTestShop([
        'shop' => 'store-b.myshopify.com',
        'shop_name' => 'Store B',
        'amazon_seller_id' => null,
        'amazon_refresh_token' => null,
    ]);

    // Shopify Products for Shop B
    Product::create(['title' => 'Shopify Prod B1', 'shop_id' => $shopB->id]);

    // Mapped Products for Shop B
    ProductMarketplaceMapping::create(['shop_id' => $shopB->id, 'shopify_product_id' => '999']);

    // Amazon Products for Shop B
    AllProduct::create(['sku' => 'AMZ-SKU-B1', 'user_id' => $shopB->id, 'status' => 'draft']);

    // Shopify Orders for Shop B
    ShopifyOrder::create(['shop_id' => $shopB->id, 'order_id' => 'ORD-B-1']);

    $response = $this->withSession(authDashboardSession($shopB))
        ->get('/dashboard?shop=' . $shopB->shop);

    $response->assertStatus(200);

    // Verify view received exact counts and disconnected status
    $response->assertViewHas('totalShopifyProducts', 1);
    $response->assertViewHas('totalMappedProducts', 1);
    $response->assertViewHas('totalAmazonOrders', 0);
    $response->assertViewHas('totalAmazonProducts', 1);
    $response->assertViewHas('totalShopifyOrders', 1);
    $response->assertViewHas('isAmazonConnected', false);

    // Verify Disconnected Status in HTML
    $response->assertSee('● Not Connected');
});

test('strict multi-store tenant isolation: Store A counts never leak into Store B dashboard', function () {
    $shopA = createDashboardTestShop([
        'shop' => 'store-tenant-a.myshopify.com',
        'shop_name' => 'Store Tenant A',
        'amazon_seller_id' => 'SELLER_A_TENANT',
        'amazon_refresh_token' => 'dummy_token_a',
    ]);

    $shopB = createDashboardTestShop([
        'shop' => 'store-tenant-b.myshopify.com',
        'shop_name' => 'Store Tenant B',
        'amazon_seller_id' => null,
        'amazon_refresh_token' => null,
    ]);

    // Store A data
    Product::create(['title' => 'Shopify Prod A1', 'shop_id' => $shopA->id]);
    Product::create(['title' => 'Shopify Prod A2', 'shop_id' => $shopA->id]);
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '101']);
    AllProduct::create(['sku' => 'AMZ-SKU-A1', 'user_id' => $shopA->id, 'status' => 'draft']);
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-1']);

    // Store B data is empty
    $responseB = $this->withSession(authDashboardSession($shopB))
        ->get('/dashboard?shop=' . $shopB->shop);

    $responseB->assertStatus(200);
    $responseB->assertViewHas('totalShopifyProducts', 0);
    $responseB->assertViewHas('totalMappedProducts', 0);
    $responseB->assertViewHas('totalAmazonOrders', 0);
    $responseB->assertViewHas('totalAmazonProducts', 0);
    $responseB->assertViewHas('totalShopifyOrders', 0);
    $responseB->assertViewHas('isAmazonConnected', false);
    $responseB->assertSee('● Not Connected');

    // Store A response
    $responseA = $this->withSession(authDashboardSession($shopA))
        ->get('/dashboard?shop=' . $shopA->shop);

    $responseA->assertStatus(200);
    $responseA->assertViewHas('totalShopifyProducts', 2);
    $responseA->assertViewHas('totalMappedProducts', 1);
    $responseA->assertViewHas('totalAmazonProducts', 1);
    $responseA->assertViewHas('totalShopifyOrders', 1);
    $responseA->assertViewHas('isAmazonConnected', true);
    $responseA->assertSee('● Connected');
});

test('all six dashboard cards are clickable and navigate to correct shop-scoped routes', function () {
    $shop = createDashboardTestShop([
        'shop' => 'clickable-test.myshopify.com',
        'shop_name' => 'Clickable Store',
        'amazon_seller_id' => 'SELLER_CLICK_123',
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);

    $content = $response->getContent();

    // 1. Shopify Products link
    $expectedShopifyProductsUrl = route('shopify.products', ['shop' => $shop->shop]);
    expect($content)->toContain('href="' . e($expectedShopifyProductsUrl) . '"');

    // 2. Shopify Orders link
    $expectedShopifyOrdersUrl = url('/orders') . '?' . http_build_query([
        'shop' => $shop->shop,
        'source' => 'shopify',
    ]);
    expect($content)->toContain('href="' . e($expectedShopifyOrdersUrl) . '"');

    // 3. Amazon Orders link
    $expectedAmazonOrdersUrl = url('/orders') . '?' . http_build_query([
        'shop' => $shop->shop,
        'source' => 'amazon',
    ]);
    expect($content)->toContain('href="' . e($expectedAmazonOrdersUrl) . '"');

    // 4. Amazon Products link
    $expectedAmazonProductsUrl = route('user.product.showProducts', ['shop' => $shop->shop]);
    expect($content)->toContain('href="' . e($expectedAmazonProductsUrl) . '"');

    // 5. Mapped Products link (Inventory page with Mapped tab)
    $expectedMappedProductsUrl = route('shopify.inventory.index', [
        'shop' => $shop->shop,
        'tab' => 'mapped',
    ]);
    expect($content)->toContain('href="' . e($expectedMappedProductsUrl) . '"');

    // 6. Amazon Status link
    $expectedAmazonConnectUrl = route('amazon.connect', ['shop' => $shop->shop]);
    expect($content)->toContain('href="' . e($expectedAmazonConnectUrl) . '"');
});

test('inventory page opens with mapped tab active when navigating with tab=mapped', function () {
    $shop = createDashboardTestShop([
        'shop' => 'tab-test.myshopify.com',
        'shop_name' => 'Tab Store',
        'amazon_seller_id' => 'SELLER_TAB_123',
        'amazon_refresh_token' => 'dummy_token',
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get(route('shopify.inventory.index', ['shop' => $shop->shop, 'tab' => 'mapped']));

    $response->assertStatus(200);

    $content = $response->getContent();

    // Mappings tab button must be active
    expect($content)->toMatch('/<button[^>]*class="[^"]*active[^"]*"[^>]*id="mapped-tab"/');

    // Mappings tab content pane must have show active
    expect($content)->toMatch('/<div[^>]*class="[^"]*show\s+active[^"]*"[^>]*id="mappedAmazonTab"/');
});
