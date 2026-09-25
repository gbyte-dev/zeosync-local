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
    AllProduct::query()->delete();
    Shop::query()->forceDelete();
    ProductMarketplaceMapping::query()->delete();
    ProductMapping::query()->delete();
    ShopifyOrder::query()->delete();
    Cache::flush();
});

function createDashboardTestShop(array $attributes = []): Shop
{
    $random = uniqid() . '-' . mt_rand(1000, 9999);
    return Shop::create(array_merge([
        'shop' => 'dash-test-' . $random . '.myshopify.com',
        'shop_name' => 'Dashboard Test Store ' . $random,
        'email' => 'dash' . $random . '@example.com',
        'access_token' => 'shpat_test_' . $random,
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
    $sellerId = 'SELLER_A_' . uniqid();
    $shopA = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token_a',
    ]);

    // Shopify Products for Shop A
    Product::create(['title' => 'Shopify Prod A1', 'shop_id' => $shopA->id]);
    Product::create(['title' => 'Shopify Prod A2', 'shop_id' => $shopA->id]);
    Product::create(['title' => 'Shopify Prod A3', 'shop_id' => $shopA->id]);

    // Mapped Products for Shop A (Valid mappings require non-null amazon_sku and shopify_variant_id)
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '111', 'shopify_variant_id' => 'var-111', 'amazon_sku' => 'AMZ-SKU-1']);
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '222', 'shopify_variant_id' => 'var-222', 'amazon_sku' => 'AMZ-SKU-2']);

    // Amazon Products in Inventory Cache for Shop A
    Cache::put("amazon_inventory_{$shopA->id}_{$sellerId}", [
        ['sku' => 'AMZ-SKU-1', 'title' => 'Amazon Prod 1', 'quantity' => 10],
        ['sku' => 'AMZ-SKU-2', 'title' => 'Amazon Prod 2', 'quantity' => 20],
        ['sku' => 'AMZ-SKU-3', 'title' => 'Amazon Prod 3', 'quantity' => 5],
    ], 3600);

    // Shopify Orders for Shop A
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-1']);
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-2']);
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-3']);
    ShopifyOrder::create(['shop_id' => $shopA->id, 'order_id' => 'ORD-A-4']);

    // Amazon Orders in Cache for Shop A
    Cache::put('amazon_orders_' . $shopA->shop . '_' . $sellerId, [
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
    $response->assertViewHas('totalAmazonProducts', 3);
    $response->assertViewHas('totalShopifyOrders', 4);
    $response->assertViewHas('isAmazonConnected', true);

    // Verify Connection Status in HTML
    $response->assertSee('● Connected');
});

test('dashboard accurately displays all six cards for a shop with amazon disconnected', function () {
    $shopB = createDashboardTestShop([
        'amazon_seller_id' => null,
        'amazon_refresh_token' => null,
    ]);

    // Shopify Products for Shop B
    Product::create(['title' => 'Shopify Prod B1', 'shop_id' => $shopB->id]);

    // Mapped Products for Shop B
    ProductMarketplaceMapping::create(['shop_id' => $shopB->id, 'shopify_product_id' => '999', 'shopify_variant_id' => 'var-999', 'amazon_sku' => 'AMZ-SKU-999']);

    // Shopify Orders for Shop B
    ShopifyOrder::create(['shop_id' => $shopB->id, 'order_id' => 'ORD-B-1']);

    $response = $this->withSession(authDashboardSession($shopB))
        ->get('/dashboard?shop=' . $shopB->shop);

    $response->assertStatus(200);

    // Verify view received exact counts and disconnected status
    $response->assertViewHas('totalShopifyProducts', 1);
    $response->assertViewHas('totalMappedProducts', 1);
    $response->assertViewHas('totalAmazonOrders', 0);
    $response->assertViewHas('totalAmazonProducts', 0);
    $response->assertViewHas('totalShopifyOrders', 1);
    $response->assertViewHas('isAmazonConnected', false);

    // Verify Disconnected Status in HTML
    $response->assertSee('● Not Connected');
});

test('strict multi-store tenant isolation: Store A counts never leak into Store B dashboard', function () {
    $sellerA = 'SELLER_A_TENANT_' . uniqid();
    $shopA = createDashboardTestShop([
        'amazon_seller_id' => $sellerA,
        'amazon_refresh_token' => 'dummy_token_a',
    ]);

    $shopB = createDashboardTestShop([
        'amazon_seller_id' => null,
        'amazon_refresh_token' => null,
    ]);

    // Store A data
    Product::create(['title' => 'Shopify Prod A1', 'shop_id' => $shopA->id]);
    Product::create(['title' => 'Shopify Prod A2', 'shop_id' => $shopA->id]);
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '101', 'shopify_variant_id' => 'var-101', 'amazon_sku' => 'AMZ-SKU-A1']);
    Cache::put("amazon_inventory_{$shopA->id}_{$sellerA}", [
        ['sku' => 'AMZ-SKU-A1', 'title' => 'Amz Product A1', 'quantity' => 15],
    ], 3600);
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

    // 4. Amazon Products link (Inventory page with Amazon tab)
    $expectedAmazonProductsUrl = route('shopify.inventory.index', [
        'shop' => $shop->shop,
        'tab' => 'amazon',
    ]);
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

test('inventory page opens with amazon tab active when navigating with tab=amazon', function () {
    $shop = createDashboardTestShop([
        'amazon_seller_id' => 'SELLER_AMZ_TAB_123',
        'amazon_refresh_token' => 'dummy_token',
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get(route('shopify.inventory.index', ['shop' => $shop->shop, 'tab' => 'amazon']));

    $response->assertStatus(200);

    $content = $response->getContent();

    // Amazon tab button must be active
    expect($content)->toMatch('/<button[^>]*class="[^"]*active[^"]*"[^>]*id="amazon-tab"/');

    // Amazon tab content pane must have show active
    expect($content)->toMatch('/<div[^>]*class="[^"]*show\s+active[^"]*"[^>]*id="amazonTab"/');

    // DOMContentLoaded should invoke switchToAmazonTab()
    expect($content)->toContain('switchToAmazonTab();');
});

test('amazon products card shows inline spinner when cache is not ready and sync is refreshing', function () {
    $sellerId = 'SELLER_LOADING_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // Ensure inventory cache does NOT exist
    Cache::forget("amazon_inventory_{$shop->id}_{$sellerId}");

    // Set status indicating sync/cache generation is in progress
    Cache::forever("amazon_inventory_status_{$shop->id}_{$sellerId}", [
        'refreshing' => true,
        'sync_completed' => false,
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertViewHas('isAmazonInventoryLoading', true);

    $content = $response->getContent();
    expect($content)->toMatch('/<div class="saas-stat-value" id="amazonProductsStatValue">\s*<span class="[^"]*amazon-products-spinner/');
});

test('amazon products card displays 0 and not loading spinner when cache is valid and empty', function () {
    $sellerId = 'SELLER_EMPTY_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // Inventory cache exists with 0 products
    Cache::put("amazon_inventory_{$shop->id}_{$sellerId}", [], 3600);

    Cache::forever("amazon_inventory_status_{$shop->id}_{$sellerId}", [
        'refreshing' => false,
        'sync_completed' => true,
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertViewHas('isAmazonInventoryLoading', false);
    $response->assertViewHas('totalAmazonProducts', 0);

    $content = $response->getContent();
    expect($content)->not->toMatch('/<div class="saas-stat-value" id="amazonProductsStatValue">\s*<span class="[^"]*amazon-products-spinner/');
    expect($content)->toContain('0');
});

test('multi-store: Store A loading state does not affect Store B with cached Amazon products', function () {
    $sellerA = 'SELLER_A_LOAD_' . uniqid();
    $shopA = createDashboardTestShop([
        'amazon_seller_id' => $sellerA,
        'amazon_refresh_token' => 'dummy_token_a',
    ]);

    $sellerB = 'SELLER_B_READY_' . uniqid();
    $shopB = createDashboardTestShop([
        'amazon_seller_id' => $sellerB,
        'amazon_refresh_token' => 'dummy_token_b',
    ]);

    // Store A is refreshing without cache
    Cache::forget("amazon_inventory_{$shopA->id}_{$sellerA}");
    Cache::forever("amazon_inventory_status_{$shopA->id}_{$sellerA}", [
        'refreshing' => true,
        'sync_completed' => false,
    ]);

    // Store B has cached 25 products
    $storeBProducts = array_map(fn($i) => ['sku' => "SKU-{$i}", 'quantity' => 10], range(1, 25));
    Cache::put("amazon_inventory_{$shopB->id}_{$sellerB}", $storeBProducts, 3600);
    Cache::forever("amazon_inventory_status_{$shopB->id}_{$sellerB}", [
        'refreshing' => false,
        'sync_completed' => true,
    ]);

    // Check Store A
    $responseA = $this->withSession(authDashboardSession($shopA))
        ->get('/dashboard?shop=' . $shopA->shop);
    $responseA->assertStatus(200);
    $responseA->assertViewHas('isAmazonInventoryLoading', true);
    expect($responseA->getContent())->toMatch('/<div class="saas-stat-value" id="amazonProductsStatValue">\s*<span class="[^"]*amazon-products-spinner/');
    expect($responseA->getContent())->toMatch('/<div class="saas-stat-value" id="mappedProductsStatValue">\s*<span class="[^"]*mapped-products-spinner/');

    // Check Store B
    $responseB = $this->withSession(authDashboardSession($shopB))
        ->get('/dashboard?shop=' . $shopB->shop);
    $responseB->assertStatus(200);
    $responseB->assertViewHas('isAmazonInventoryLoading', false);
    $responseB->assertViewHas('totalAmazonProducts', 25);
    expect($responseB->getContent())->not->toMatch('/<div class="saas-stat-value" id="amazonProductsStatValue">\s*<span class="[^"]*amazon-products-spinner/');
    expect($responseB->getContent())->not->toMatch('/<div class="saas-stat-value" id="mappedProductsStatValue">\s*<span class="[^"]*mapped-products-spinner/');
    expect($responseB->getContent())->toContain('25');
});

test('mapped products card shows inline spinner and syncing text when amazon cache is not ready (State B)', function () {
    $sellerId = 'SELLER_MAP_LOAD_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // Ensure Amazon cache does NOT exist
    Cache::forget("amazon_inventory_{$shop->id}_{$sellerId}");

    // Set status indicating sync is in progress
    Cache::forever("amazon_inventory_status_{$shop->id}_{$sellerId}", [
        'refreshing' => true,
        'sync_completed' => false,
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertViewHas('isAmazonInventoryLoading', true);

    $content = $response->getContent();
    expect($content)->toMatch('/<div class="saas-stat-value" id="mappedProductsStatValue">\s*<span class="[^"]*mapped-products-spinner/');
    expect($content)->toContain('Syncing...');
});

test('mapped products card displays actual mapped count without spinner when amazon cache exists (State A)', function () {
    $sellerId = 'SELLER_MAP_READY_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // Create mappings in database with valid shopify_variant_id and amazon_sku
    ProductMarketplaceMapping::create(['shop_id' => $shop->id, 'shopify_product_id' => '101', 'shopify_variant_id' => 'var-101', 'amazon_sku' => 'SKU-A']);
    ProductMarketplaceMapping::create(['shop_id' => $shop->id, 'shopify_product_id' => '102', 'shopify_variant_id' => 'var-102', 'amazon_sku' => 'SKU-B']);
    ProductMarketplaceMapping::create(['shop_id' => $shop->id, 'shopify_product_id' => '103', 'shopify_variant_id' => 'var-103', 'amazon_sku' => 'SKU-C']);

    // Amazon inventory cache exists
    Cache::put("amazon_inventory_{$shop->id}_{$sellerId}", [
        ['sku' => 'SKU-A', 'quantity' => 10],
        ['sku' => 'SKU-B', 'quantity' => 20],
        ['sku' => 'SKU-C', 'quantity' => 30],
    ], 3600);

    Cache::forever("amazon_inventory_status_{$shop->id}_{$sellerId}", [
        'refreshing' => false,
        'sync_completed' => true,
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertViewHas('isAmazonInventoryLoading', false);
    $response->assertViewHas('totalMappedProducts', 3);

    $content = $response->getContent();
    expect($content)->not->toMatch('/<div class="saas-stat-value" id="mappedProductsStatValue">\s*<span class="[^"]*mapped-products-spinner/');
    expect($content)->toContain('3');
});

test('mapped products card displays 0 without spinner when amazon cache exists but 0 mappings exist (State D)', function () {
    $sellerId = 'SELLER_MAP_ZERO_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // Amazon inventory cache exists with products, but no mappings exist
    Cache::put("amazon_inventory_{$shop->id}_{$sellerId}", [
        ['sku' => 'SKU-X', 'quantity' => 10],
    ], 3600);

    Cache::forever("amazon_inventory_status_{$shop->id}_{$sellerId}", [
        'refreshing' => false,
        'sync_completed' => true,
    ]);

    $response = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertViewHas('isAmazonInventoryLoading', false);
    $response->assertViewHas('totalMappedProducts', 0);

    $content = $response->getContent();
    expect($content)->not->toMatch('/<div class="saas-stat-value" id="mappedProductsStatValue">\s*<span class="[^"]*mapped-products-spinner/');
    expect($content)->toContain('0');
});

test('exact parity: 3 DB mapping rows with only 2 satisfying display criteria results in count = 2 on Dashboard and Inventory Mapping', function () {
    $sellerId = 'SELLER_PARITY_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // Create 3 mapping rows in DB:
    // Row 1: Valid mapping (both shopify_variant_id and amazon_sku present)
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '101',
        'shopify_variant_id' => 'var-101',
        'amazon_sku' => 'SKU-VALID-1',
    ]);

    // Row 2: Valid mapping (both shopify_variant_id and amazon_sku present)
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '102',
        'shopify_variant_id' => 'var-102',
        'amazon_sku' => 'SKU-VALID-2',
    ]);

    // Row 3: Incomplete/Unmapped row (empty/null amazon_sku or shopify_variant_id)
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '103',
        'shopify_variant_id' => 'var-103',
        'amazon_sku' => null,
    ]);

    // Verify DB count is 3
    expect(ProductMarketplaceMapping::where('shop_id', $shop->id)->count())->toBe(3);

    // 1. Inventory Mapping API endpoint returns exactly 2
    $mappingApiResponse = $this->withSession(authDashboardSession($shop))
        ->getJson(route('inventory.mappings', ['shop' => $shop->shop]));
    $mappingApiResponse->assertStatus(200);
    $mappingApiResponse->assertJsonPath('success', true);
    $mappingApiResponse->assertJsonCount(2, 'mappings');

    // 2. Inventory Index page receives exactly 2 mappedproducts
    $inventoryResponse = $this->withSession(authDashboardSession($shop))
        ->get(route('shopify.inventory.index', ['shop' => $shop->shop, 'tab' => 'mapped']));
    $inventoryResponse->assertStatus(200);
    $inventoryResponse->assertViewHas('mappedproducts', function ($mapped) {
        return $mapped->count() === 2;
    });

    // 3. Dashboard receives and displays exactly 2
    Cache::put("amazon_inventory_{$shop->id}_{$sellerId}", [
        ['sku' => 'SKU-VALID-1', 'quantity' => 10],
        ['sku' => 'SKU-VALID-2', 'quantity' => 20],
    ], 3600);

    $dashboardResponse = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);
    $dashboardResponse->assertStatus(200);
    $dashboardResponse->assertViewHas('totalMappedProducts', 2);
    $dashboardContent = $dashboardResponse->getContent();
    expect($dashboardContent)->toContain('2');
});

test('mapped products displays 0 on Dashboard and Inventory Mapping when all DB rows are incomplete', function () {
    $sellerId = 'SELLER_INCOMPLETE_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // 2 DB rows, both incomplete
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '101',
        'shopify_variant_id' => 'var-101',
        'amazon_sku' => '',
    ]);
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '102',
        'shopify_variant_id' => null,
        'amazon_sku' => 'SKU-102',
    ]);

    Cache::put("amazon_inventory_{$shop->id}_{$sellerId}", [], 3600);

    // Dashboard
    $dashboardResponse = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);
    $dashboardResponse->assertStatus(200);
    $dashboardResponse->assertViewHas('totalMappedProducts', 0);

    // Inventory Mappings API
    $apiResponse = $this->withSession(authDashboardSession($shop))
        ->getJson(route('inventory.mappings', ['shop' => $shop->shop]));
    $apiResponse->assertStatus(200);
    $apiResponse->assertJsonCount(0, 'mappings');

    // Inventory Index View
    $invResponse = $this->withSession(authDashboardSession($shop))
        ->get(route('shopify.inventory.index', ['shop' => $shop->shop, 'tab' => 'mapped']));
    $invResponse->assertStatus(200);
    $invResponse->assertViewHas('mappedproducts', function ($m) {
        return $m->count() === 0;
    });
});

test('mapped products displays exact count for 1 visible mapping', function () {
    $sellerId = 'SELLER_ONE_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '101',
        'shopify_variant_id' => 'var-101',
        'amazon_sku' => 'SKU-1',
    ]);

    Cache::put("amazon_inventory_{$shop->id}_{$sellerId}", [
        ['sku' => 'SKU-1', 'quantity' => 5],
    ], 3600);

    $dashboardResponse = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);
    $dashboardResponse->assertStatus(200);
    $dashboardResponse->assertViewHas('totalMappedProducts', 1);

    $apiResponse = $this->withSession(authDashboardSession($shop))
        ->getJson(route('inventory.mappings', ['shop' => $shop->shop]));
    $apiResponse->assertStatus(200);
    $apiResponse->assertJsonCount(1, 'mappings');
});

test('mapped products displays exact count for multiple visible mappings (e.g. 10)', function () {
    $sellerId = 'SELLER_TEN_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    for ($i = 1; $i <= 10; $i++) {
        ProductMarketplaceMapping::create([
            'shop_id' => $shop->id,
            'shopify_product_id' => (string) (100 + $i),
            'shopify_variant_id' => 'var-' . (100 + $i),
            'amazon_sku' => 'SKU-' . $i,
        ]);
    }

    Cache::put("amazon_inventory_{$shop->id}_{$sellerId}", array_map(fn($i) => ['sku' => "SKU-{$i}", 'quantity' => 10], range(1, 10)), 3600);

    $dashboardResponse = $this->withSession(authDashboardSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);
    $dashboardResponse->assertStatus(200);
    $dashboardResponse->assertViewHas('totalMappedProducts', 10);

    $apiResponse = $this->withSession(authDashboardSession($shop))
        ->getJson(route('inventory.mappings', ['shop' => $shop->shop]));
    $apiResponse->assertStatus(200);
    $apiResponse->assertJsonCount(10, 'mappings');
});

test('multi-store mapping count isolation across different shops', function () {
    $shopA = createDashboardTestShop(['amazon_seller_id' => 'SELLER_SHOP_A', 'amazon_refresh_token' => 'tok_a']);
    $shopB = createDashboardTestShop(['amazon_seller_id' => 'SELLER_SHOP_B', 'amazon_refresh_token' => 'tok_b']);

    // Shop A: 2 valid mappings + 1 invalid
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '101', 'shopify_variant_id' => 'var-101', 'amazon_sku' => 'SKU-A1']);
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '102', 'shopify_variant_id' => 'var-102', 'amazon_sku' => 'SKU-A2']);
    ProductMarketplaceMapping::create(['shop_id' => $shopA->id, 'shopify_product_id' => '103', 'shopify_variant_id' => 'var-103', 'amazon_sku' => null]);

    // Shop B: 5 valid mappings + 2 invalid
    for ($i = 1; $i <= 5; $i++) {
        ProductMarketplaceMapping::create(['shop_id' => $shopB->id, 'shopify_product_id' => (string)(200+$i), 'shopify_variant_id' => 'var-'.(200+$i), 'amazon_sku' => 'SKU-B'.$i]);
    }
    ProductMarketplaceMapping::create(['shop_id' => $shopB->id, 'shopify_product_id' => '298', 'shopify_variant_id' => 'var-298', 'amazon_sku' => '']);
    ProductMarketplaceMapping::create(['shop_id' => $shopB->id, 'shopify_product_id' => '299', 'shopify_variant_id' => null, 'amazon_sku' => 'SKU-B99']);

    Cache::put("amazon_inventory_{$shopA->id}_SELLER_SHOP_A", [['sku' => 'SKU-A1', 'quantity' => 1]], 3600);
    Cache::put("amazon_inventory_{$shopB->id}_SELLER_SHOP_B", [['sku' => 'SKU-B1', 'quantity' => 1]], 3600);

    // Shop A assertions
    $respA = $this->withSession(authDashboardSession($shopA))->get('/dashboard?shop=' . $shopA->shop);
    $respA->assertStatus(200)->assertViewHas('totalMappedProducts', 2);

    $apiA = $this->withSession(authDashboardSession($shopA))->getJson(route('inventory.mappings', ['shop' => $shopA->shop]));
    $apiA->assertStatus(200)->assertJsonCount(2, 'mappings');

    // Shop B assertions
    $respB = $this->withSession(authDashboardSession($shopB))->get('/dashboard?shop=' . $shopB->shop);
    $respB->assertStatus(200)->assertViewHas('totalMappedProducts', 5);

    $apiB = $this->withSession(authDashboardSession($shopB))->getJson(route('inventory.mappings', ['shop' => $shopB->shop]));
    $apiB->assertStatus(200)->assertJsonCount(5, 'mappings');
});

test('cache completion updates Dashboard to exact Mapping count via AJAX endpoint', function () {
    $sellerId = 'SELLER_AJAX_REFRESH_' . uniqid();
    $shop = createDashboardTestShop([
        'amazon_seller_id' => $sellerId,
        'amazon_refresh_token' => 'dummy_refresh_token',
    ]);

    // Create 3 rows in DB, only 2 valid
    ProductMarketplaceMapping::create(['shop_id' => $shop->id, 'shopify_product_id' => '1', 'shopify_variant_id' => 'v1', 'amazon_sku' => 'SKU-1']);
    ProductMarketplaceMapping::create(['shop_id' => $shop->id, 'shopify_product_id' => '2', 'shopify_variant_id' => 'v2', 'amazon_sku' => 'SKU-2']);
    ProductMarketplaceMapping::create(['shop_id' => $shop->id, 'shopify_product_id' => '3', 'shopify_variant_id' => 'v3', 'amazon_sku' => null]);

    // Simulate AJAX call used after cache completion (route: inventory.mappings)
    $response = $this->withSession(authDashboardSession($shop))
        ->getJson(route('inventory.mappings', ['shop' => $shop->shop]));

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonCount(2, 'mappings');

    $mappings = $response->json('mappings');
    $skus = array_column($mappings, 'amazon_sku');
    expect($skus)->toEqualCanonicalizing(['SKU-1', 'SKU-2']);
});
