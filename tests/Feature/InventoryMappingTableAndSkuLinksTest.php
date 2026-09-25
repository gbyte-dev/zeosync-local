<?php

use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Models\Plan;
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
            $table->json('variants')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('sync_status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
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

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->nullable()->index();
            $table->longText('option_value')->nullable();
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
    Cache::flush();
});

if (!function_exists('createInventoryTableTestShop')) {
    function createInventoryTableTestShop(array $attributes = []): Shop
    {
        $random = uniqid() . '-' . mt_rand(1000, 9999);
        return Shop::create(array_merge([
            'shop' => 'inv-table-' . $random . '.myshopify.com',
            'shop_name' => 'Table Test Store ' . $random,
            'email' => 'table' . $random . '@example.com',
            'access_token' => 'shpat_test_' . $random,
            'is_active' => true,
            'shopify_locations' => [
                ['id' => 'gid://shopify/Location/5001', 'name' => 'East Coast Warehouse'],
                ['id' => 'gid://shopify/Location/5002', 'name' => 'West Coast Warehouse'],
            ],
            'selected_location_index' => 0,
            'amazon_seller_id' => 'SELLER_' . $random,
            'amazon_refresh_token' => 'amz_refresh_' . $random,
            'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        ], $attributes));
    }
}

if (!function_exists('authTableSession')) {
    function authTableSession(Shop $shop): array
    {
        return [
            '_shopify_verified_shop' => $shop->shop,
            '_shopify_verified_at'   => time(),
            'active_shop'            => $shop->shop,
            'active_shop_id'         => $shop->id,
        ];
    }
}

test('Dashboard SKU link: valid SKU renders as clickable link with exact same URL as Product Name', function () {
    $shop = createInventoryTableTestShop();

    $mockInventoryService = Mockery::mock(ShopifyInventoryService::class);
    $mockInventoryService->shouldReceive('getInventory')->with(Mockery::on(fn($s) => $s->id === $shop->id))->andReturn([
        [
            'pid' => '12345678',
            'vid' => '87654321',
            'product' => 'Summer T-Shirt',
            'variant' => 'Medium',
            'sku' => 'TSHIRT-MED-BLUE',
            'available' => 2,
        ]
    ]);
    app()->instance(ShopifyInventoryService::class, $mockInventoryService);

    $response = $this->withSession(authTableSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);

    $expectedUrl = route('shopify.product.view', ['id' => '12345678', 'shop' => $shop->shop]);

    // Both Product Name and SKU have the exact same link
    $response->assertSee('href="' . $expectedUrl . '"', false);
    $response->assertSee('Summer T-Shirt', false);
    $response->assertSee('>' . 'TSHIRT-MED-BLUE' . '</a>', false);
});

test('Dashboard SKU link: null, empty, or hyphen SKU renders as plain text without link', function () {
    $shop = createInventoryTableTestShop();

    $mockInventoryService = Mockery::mock(ShopifyInventoryService::class);
    $mockInventoryService->shouldReceive('getInventory')->with(Mockery::on(fn($s) => $s->id === $shop->id))->andReturn([
        [
            'pid' => '12345679',
            'vid' => '87654322',
            'product' => 'No SKU Item',
            'variant' => 'Default',
            'sku' => null,
            'available' => 1,
        ],
        [
            'pid' => '12345680',
            'vid' => '87654323',
            'product' => 'Hyphen SKU Item',
            'variant' => 'Default',
            'sku' => '-',
            'available' => 1,
        ]
    ]);
    app()->instance(ShopifyInventoryService::class, $mockInventoryService);

    $response = $this->withSession(authTableSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);

    $response->assertStatus(200);

    // Should NOT have SKU link for null or hyphen SKU
    $response->assertDontSee('>-</a>', false);
    $response->assertDontSee('></a>', false);
    $response->assertSee('No SKU Item', false);
    $response->assertSee('Hyphen SKU Item', false);
});

test('Inventory Mapping tab: renders data table with all fields when mappings exist', function () {
    $shop = createInventoryTableTestShop();

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 99887711,
        'title' => 'Vintage Leather Jacket',
        'variants' => [
            [
                'id' => '55443322',
                'title' => 'Large / Brown',
                'sku' => 'JKT-LRG-BRN',
                'inventory_item_id' => '99001',
            ]
        ]
    ]);

    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'shopify_product_id' => '99887711',
        'shopify_variant_id' => '55443322',
        'shopify_inventory_item_id' => '99001',
        'shopify_location_id' => 'gid://shopify/Location/5001',
        'amazon_sku' => 'AMZ-JKT-001',
        'sync_status' => 'synced',
        'quantity' => 15,
        'last_synced_at' => now(),
    ]);

    $response = $this->withSession(authTableSession($shop))
        ->get('/inventory?tab=mapped&shop=' . $shop->shop);

    $response->assertStatus(200);

    // Table elements are rendered
    $response->assertSee('Vintage Leather Jacket', false);
    $response->assertSee('Large / Brown', false);
    $response->assertSee('AMZ-JKT-001', false);
    $response->assertSee('East Coast Warehouse', false);
    $response->assertSee('Synced', false);
    $response->assertSee('unmap-product', false);
    $response->assertDontSee('style="" id="mappedNoDataMsg"', false);
});

test('Inventory Mapping tab: shows only empty state when 0 mappings exist and no table header/body', function () {
    $shop = createInventoryTableTestShop();

    $response = $this->withSession(authTableSession($shop))
        ->get('/inventory?tab=mapped&shop=' . $shop->shop);

    $response->assertStatus(200);

    // Empty state is rendered
    $response->assertSee('No Product Mappings Found', false);
    $response->assertSee('Map a Shopify product to an Amazon SKU to start syncing inventory.', false);

    // Table wrapper is hidden
    $response->assertSee('id="mappedTableWrapper" class="table-responsive" style="display: none;"', false);
});

test('Inventory Mapping API endpoint: returns rich enriched mappings with canonical mapping scope', function () {
    $shop = createInventoryTableTestShop();

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 11223344,
        'title' => 'Ceramic Coffee Mug',
        'variants' => [
            [
                'id' => '99880011',
                'title' => '12oz / Matte Black',
                'sku' => 'MUG-12OZ-BLK',
            ]
        ]
    ]);

    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'shopify_product_id' => '11223344',
        'shopify_variant_id' => '99880011',
        'shopify_location_id' => 'gid://shopify/Location/5002',
        'amazon_sku' => 'AMZ-MUG-12BLK',
        'sync_status' => 'pending',
        'quantity' => 20,
        'last_synced_at' => now(),
    ]);

    $response = $this->withSession(authTableSession($shop))
        ->getJson('/inventory/mappings?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);

    $mappings = $response->json('mappings');
    expect($mappings)->toHaveCount(1);
    expect($mappings[0]['shopify_product_title'])->toBe('Ceramic Coffee Mug');
    expect($mappings[0]['shopify_variant_title'])->toBe('12oz / Matte Black');
    expect($mappings[0]['shopify_location_name'])->toBe('West Coast Warehouse');
    expect($mappings[0]['amazon_sku'])->toBe('AMZ-MUG-12BLK');
    expect($mappings[0]['sync_status'])->toBe('pending');
    expect($mappings[0]['shopify_product_url'])->toContain(route('shopify.product.view', ['id' => '11223344', 'shop' => $shop->shop]));
    expect($mappings[0]['amazon_product_url'])->toContain(route('user.product.amazonView', ['sku' => 'AMZ-MUG-12BLK', 'shop' => $shop->shop]));
});

test('Dashboard mapped count remains equal to visible Mapping-tab mappings count', function () {
    $shop = createInventoryTableTestShop();

    // Cache valid Amazon inventory
    Cache::put("amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}", [
        ['sku' => 'AMZ-P1', 'title' => 'P1', 'quantity' => 5],
        ['sku' => 'AMZ-P2', 'title' => 'P2', 'quantity' => 3],
    ], 300);

    // 2 valid mappings
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '101',
        'shopify_variant_id' => '201',
        'amazon_sku' => 'AMZ-P1',
        'sync_status' => 'synced',
    ]);
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '102',
        'shopify_variant_id' => '202',
        'amazon_sku' => 'AMZ-P2',
        'sync_status' => 'synced',
    ]);
    // 1 invalid mapping (missing amazon_sku)
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '103',
        'shopify_variant_id' => '203',
        'amazon_sku' => null,
        'sync_status' => 'pending',
    ]);

    // Dashboard count check
    $dashRes = $this->withSession(authTableSession($shop))
        ->get('/dashboard?shop=' . $shop->shop);
    $dashRes->assertStatus(200);
    $dashRes->assertSee('id="mappedProductsStatValue"', false);
    $dashRes->assertSee('2', false);

    // Inventory Mappings count check
    $mappingRes = $this->withSession(authTableSession($shop))
        ->getJson('/inventory/mappings?shop=' . $shop->shop);
    $mappingRes->assertStatus(200);
    expect($mappingRes->json('mappings'))->toHaveCount(2);
});

test('Inventory Mapping tab: renders interactive DataTable controls (search, filter, page size)', function () {
    $shop = createInventoryTableTestShop();

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 99887711,
        'title' => 'Vintage Leather Jacket',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'shopify_product_id' => '99887711',
        'shopify_variant_id' => '55443322',
        'amazon_sku' => 'AMZ-JKT-001',
        'sync_status' => 'synced',
    ]);

    $response = $this->withSession(authTableSession($shop))
        ->get('/inventory?tab=mapped&shop=' . $shop->shop);

    $response->assertStatus(200);

    // Interactive DataTable controls
    $response->assertSee('id="dtSearchMapped"', false);
    $response->assertSee('id="dtStatusMapped"', false);
    $response->assertSee('id="dtLengthMapped"', false);
    $response->assertSee('value="10"', false);
    $response->assertSee('value="25"', false);
    $response->assertSee('value="50"', false);
    $response->assertSee('value="100"', false);
    $response->assertSee('id="mappedToolbar"', false);
    $response->assertSee('id="mappedLoadingMsg"', false);
    $response->assertSee('id="mappedTableWrapper"', false);
});

test('Inventory Mapping unmap action deletes mapping and returns sync usage', function () {
    $shop = createInventoryTableTestShop();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_product_id' => '1122',
        'shopify_variant_id' => '3344',
        'amazon_sku' => 'AMZ-UNMAP-1',
        'sync_status' => 'synced',
    ]);

    $response = $this->withSession(authTableSession($shop))
        ->deleteJson('/inventory/unmap/' . $mapping->id . '?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Product unmapped successfully.',
    ]);

    expect(ProductMarketplaceMapping::find($mapping->id))->toBeNull();
});

test('Inventory Mapping API enforces strict tenant isolation between Shop A and Shop B', function () {
    $shopA = createInventoryTableTestShop();
    $shopB = createInventoryTableTestShop();

    // Shop A mapping
    ProductMarketplaceMapping::create([
        'shop_id' => $shopA->id,
        'shopify_product_id' => '101',
        'shopify_variant_id' => '201',
        'amazon_sku' => 'AMZ-SHOP-A-1',
        'sync_status' => 'synced',
    ]);

    // Shop B mapping
    ProductMarketplaceMapping::create([
        'shop_id' => $shopB->id,
        'shopify_product_id' => '901',
        'shopify_variant_id' => '902',
        'amazon_sku' => 'AMZ-SHOP-B-1',
        'sync_status' => 'synced',
    ]);

    // Request as Shop A
    $responseA = $this->withSession(authTableSession($shopA))
        ->getJson('/inventory/mappings?shop=' . $shopA->shop);

    $responseA->assertStatus(200);
    $mappingsA = $responseA->json('mappings');
    expect($mappingsA)->toHaveCount(1);
    expect($mappingsA[0]['amazon_sku'])->toBe('AMZ-SHOP-A-1');

    // Request as Shop B
    $responseB = $this->withSession(authTableSession($shopB))
        ->getJson('/inventory/mappings?shop=' . $shopB->shop);

    $responseB->assertStatus(200);
    $mappingsB = $responseB->json('mappings');
    expect($mappingsB)->toHaveCount(1);
    expect($mappingsB[0]['amazon_sku'])->toBe('AMZ-SHOP-B-1');
});

test('Inventory Mapping tab: truncates product name and SKU > 20 characters with tooltip and displays <= 20 characters fully', function () {
    $shop = createInventoryTableTestShop();

    // Product 1: Name > 20 chars ("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), SKU > 20 chars ("AMZ-VERY-LONG-SKU-123456789")
    $prodLong = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 10001,
        'title' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', // 26 chars
    ]);
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'product_id' => $prodLong->id,
        'shopify_product_id' => '10001',
        'shopify_variant_id' => '10001',
        'amazon_sku' => 'AMZ-VERY-LONG-SKU-123456789', // 27 chars
        'sync_status' => 'synced',
    ]);

    // Product 2: Name <= 20 chars ("Short Product"), SKU <= 20 chars ("SHORT-SKU-123")
    $prodShort = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 10002,
        'title' => 'Short Product', // 13 chars
    ]);
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'product_id' => $prodShort->id,
        'shopify_product_id' => '10002',
        'shopify_variant_id' => '10002',
        'amazon_sku' => 'SHORT-SKU-123', // 13 chars
        'sync_status' => 'synced',
    ]);

    $response = $this->withSession(authTableSession($shop))
        ->get('/inventory?tab=mapped&shop=' . $shop->shop);

    $response->assertStatus(200);

    // 1. Long Product Name > 20 chars truncated to exactly 20 characters ("ABCDEFGHIJKLMNOPQ...") with full title in tooltip
    $response->assertSee('ABCDEFGHIJKLMNOPQ...', false);
    $response->assertSee('title="ABCDEFGHIJKLMNOPQRSTUVWXYZ"', false);
    $response->assertSee('data-bs-toggle="tooltip"', false);

    // 2. Long SKU > 20 chars truncated to exactly 20 characters ("AMZ-VERY-LONG-SKU...") with full SKU in tooltip
    $response->assertSee('AMZ-VERY-LONG-SKU...', false);
    $response->assertSee('title="AMZ-VERY-LONG-SKU-123456789"', false);

    // 3. Short Product Name <= 20 chars displayed fully without truncation or unnecessary tooltip
    $response->assertSee('Short Product', false);
    $response->assertDontSee('title="Short Product"', false);

    // 4. Short SKU <= 20 chars displayed fully without truncation or unnecessary tooltip
    $response->assertSee('SHORT-SKU-123', false);
    $response->assertDontSee('title="SHORT-SKU-123"', false);

    // 5. Links are intact and clickable
    $expectedLongProdUrl = route('shopify.product.view', ['id' => '10001', 'shop' => $shop->shop]);
    $expectedLongSkuUrl = route('user.product.amazonView', ['sku' => 'AMZ-VERY-LONG-SKU-123456789', 'shop' => $shop->shop]);
    $response->assertSee('href="' . $expectedLongProdUrl . '"', false);
    $response->assertSee('href="' . $expectedLongSkuUrl . '"', false);
});

test('Inventory Mapping tab: safely escapes HTML and special characters in truncated titles and tooltips', function () {
    $shop = createInventoryTableTestShop();

    $specialTitle = 'Special "Quotes" & <Tags> Long Title Example 12345'; // > 20 chars with HTML special chars
    $specialSku = 'SKU-"TEST"&<TAGS>-LONG-99999'; // > 20 chars with HTML special chars

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 10003,
        'title' => $specialTitle,
    ]);
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'shopify_product_id' => '10003',
        'shopify_variant_id' => '10003',
        'amazon_sku' => $specialSku,
        'sync_status' => 'synced',
    ]);

    $response = $this->withSession(authTableSession($shop))
        ->get('/inventory?tab=mapped&shop=' . $shop->shop);

    $response->assertStatus(200);

    // Escaped title in tooltip attribute
    $response->assertSee('title="' . e($specialTitle) . '"', false);
    $response->assertSee('title="' . e($specialSku) . '"', false);
    // Truncated display text
    $response->assertSee(e(mb_substr($specialTitle, 0, 17) . '...'), false);
    $response->assertSee(e(mb_substr($specialSku, 0, 17) . '...'), false);
});
