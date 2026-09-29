<?php

use App\Models\AdminSetting;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Setting;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    config([
        'services.shopify.api_key' => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription' => true,
    ]);

    Http::fake([
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    View::share('cspNonce', 'test-csp-nonce-12345');
    Queue::fake();

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

    if (!Schema::hasTable('settings')) {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->boolean('auto_sync')->default(false);
            $table->boolean('ai_assist')->default(false);
            $table->string('currency', 3)->default('USD');
            $table->string('tax_behavior')->default('exclude');
            $table->string('ai_client_id')->nullable();
            $table->string('ai_client_secret')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->nullable()->index();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    AdminSetting::updateOrCreate(['option_key' => 'production_client_id'], ['option_value' => 'test_client_id']);
    AdminSetting::updateOrCreate(['option_key' => 'production_client_secret'], ['option_value' => 'test_client_secret']);

    Shop::query()->forceDelete();
    Product::query()->forceDelete();
    ProductMarketplaceMapping::query()->delete();
    Cache::flush();
});

if (!function_exists('createExistingMappingTestShop')) {
    function createExistingMappingTestShop(array $attributes = []): Shop
    {
        $random = uniqid() . '-' . mt_rand(1000, 9999);
        $shop = Shop::create(array_merge([
            'shop' => 'test-' . $random . '.myshopify.com',
            'shop_name' => 'Store ' . $random,
            'email' => 'store' . $random . '@example.com',
            'access_token' => 'shpat_test_' . $random,
            'is_active' => true,
            'amazon_seller_id' => 'SELLER_' . $random,
            'amazon_refresh_token' => 'amz_refresh_' . $random,
            'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        ], $attributes));

        Setting::updateOrCreate(['shop_id' => $shop->id], [
            'auto_sync' => false,
            'currency' => 'USD',
        ]);

        return $shop;
    }
}

if (!function_exists('authExistingMappingSession')) {
    function authExistingMappingSession(Shop $shop): array
    {
        return [
            '_shopify_verified_shop' => $shop->shop,
            '_shopify_verified_at'   => time(),
            'active_shop'            => $shop->shop,
            'active_shop_id'         => $shop->id,
        ];
    }
}

test('1. GET /inventory/amazon returns unmapped products when cache exists', function () {
    $shop = createExistingMappingTestShop();

    $cachedProducts = [
        [
            'sku' => 'AMZ-SKU-1',
            'title' => 'Amazon Product One',
            'quantity' => 10,
        ],
        [
            'sku' => 'AMZ-SKU-2',
            'title' => 'Amazon Product Two',
            'quantity' => 20,
        ],
    ];

    Cache::forever("amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}", $cachedProducts);
    Cache::forever("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing' => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toDateTimeString(),
        'cache_version' => 1,
    ]);

    $response = $this->withSession(authExistingMappingSession($shop))
        ->getJson(route('shopify.inventory.amazon', ['shop' => $shop->shop]));

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('products');
    expect($data['products'])->toHaveCount(2);
    expect($data['products'][0]['sku'])->toBe('AMZ-SKU-1');
    expect($data['products'][0]['is_mapped'])->toBeFalse();
    expect($data['products'][1]['sku'])->toBe('AMZ-SKU-2');
    expect($data['products'][1]['is_mapped'])->toBeFalse();
    expect($data['status']['refreshing'])->toBeFalse();
    expect($data['status']['sync_completed'])->toBeTrue();
});

test('2. GET /inventory/amazon correctly flags mapped products when mappings exist in DB', function () {
    $shop = createExistingMappingTestShop();

    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'AMZ-SKU-1',
        'shopify_variant_id' => '99887766',
        'shopify_product_id' => '112233',
        'quantity' => 10,
    ]);

    $cachedProducts = [
        [
            'sku' => 'AMZ-SKU-1',
            'title' => 'Amazon Product One',
            'quantity' => 10,
        ],
        [
            'sku' => 'AMZ-SKU-2',
            'title' => 'Amazon Product Two',
            'quantity' => 20,
        ],
    ];

    Cache::forever("amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}", $cachedProducts);
    Cache::forever("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing' => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toDateTimeString(),
        'cache_version' => 1,
    ]);

    $response = $this->withSession(authExistingMappingSession($shop))
        ->getJson(route('shopify.inventory.amazon', ['shop' => $shop->shop]));

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['products'])->toHaveCount(2);
    expect($data['products'][0]['sku'])->toBe('AMZ-SKU-1');
    expect($data['products'][0]['is_mapped'])->toBeTrue();
    expect($data['products'][1]['sku'])->toBe('AMZ-SKU-2');
    expect($data['products'][1]['is_mapped'])->toBeFalse();
});

test('3. GET /inventory/amazon returns refreshing state on cold cache without crashing', function () {
    $shop = createExistingMappingTestShop();

    Cache::forget("amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}");
    Cache::forget("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}");

    $response = $this->withSession(authExistingMappingSession($shop))
        ->getJson(route('shopify.inventory.amazon', ['shop' => $shop->shop]));

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['products'])->toBeArray()->toBeEmpty();
    expect($data['status']['refreshing'])->toBeTrue();
    expect($data['status']['sync_completed'])->toBeFalse();
});

test('4. GET /inventory/amazon returns connected false when Amazon refresh token is missing', function () {
    $shop = createExistingMappingTestShop([
        'amazon_refresh_token' => null, // Disconnected
    ]);

    $response = $this->withSession(authExistingMappingSession($shop))
        ->getJson(route('shopify.inventory.amazon', ['shop' => $shop->shop]));

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['connected'])->toBeFalse();
    expect($data['status']['error'])->toBe('amazon_not_connected');
    expect($data['products'])->toBeEmpty();
});

test('5. GET /inventory/amazon isolates cache strictly by shop_id and seller_id', function () {
    $shopA = createExistingMappingTestShop(['amazon_seller_id' => 'SELLER_A']);
    $shopB = createExistingMappingTestShop(['amazon_seller_id' => 'SELLER_B']);

    Cache::forever("amazon_inventory_{$shopA->id}_{$shopA->amazon_seller_id}", [
        ['sku' => 'SKU-A-ONLY', 'title' => 'Product A', 'quantity' => 5],
    ]);
    Cache::forever("amazon_inventory_status_{$shopA->id}_{$shopA->amazon_seller_id}", [
        'refreshing' => false,
        'sync_completed' => true,
    ]);

    Cache::forever("amazon_inventory_{$shopB->id}_{$shopB->amazon_seller_id}", [
        ['sku' => 'SKU-B-ONLY', 'title' => 'Product B', 'quantity' => 8],
    ]);
    Cache::forever("amazon_inventory_status_{$shopB->id}_{$shopB->amazon_seller_id}", [
        'refreshing' => false,
        'sync_completed' => true,
    ]);

    $responseA = $this->withSession(authExistingMappingSession($shopA))
        ->getJson(route('shopify.inventory.amazon', ['shop' => $shopA->shop]));

    $responseB = $this->withSession(authExistingMappingSession($shopB))
        ->getJson(route('shopify.inventory.amazon', ['shop' => $shopB->shop]));

    expect($responseA->json('products.0.sku'))->toBe('SKU-A-ONLY');
    expect($responseB->json('products.0.sku'))->toBe('SKU-B-ONLY');
});

test('6. map-amazon-product-modal contains status container and spinner elements', function () {
    $shop = createExistingMappingTestShop();

    $view = view('inventory.partials.map-amazon-product-modal', [
        'shop' => $shop,
    ])->render();

    expect($view)->toContain('id="amazonMappingStatusContainer"');
    expect($view)->toContain('id="amazonProductLoadingSpinner"');
    expect($view)->toContain('id="amazonProduct"');
    expect($view)->toContain('id="existingAmazonProductBtn"');
    expect($view)->toContain('id="newAmazonProductBtn"');
});
