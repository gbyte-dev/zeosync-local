<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\InventoryCacheService;
use App\Services\ShopifyInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

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
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->text('amazon_refresh_token')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sync_limit')->default(100);
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
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_id')->nullable();
            $table->string('title')->nullable();
            $table->json('variants')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('settings')) {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->unique();
            $table->boolean('auto_sku_mapping')->default(false);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_parent_asin')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('quantity')->nullable();
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }
});

function createMappingTestShop(string $domain = 'test-mapping.myshopify.com'): Shop
{
    $shop = Shop::create([
        'shop'                     => $domain,
        'shop_name'                => 'Test Store',
        'email'                    => 'merchant@example.com',
        'access_token'             => 'shp_valid_token_123',
        'access_token_expires_at'  => now()->addDays(30),
        'is_active'                => 1,
        'amazon_marketplace_id'    => 'ATVPDKIKX0DER',
        'amazon_mws_region'        => 'us-east-1',
        'selected_location_index'  => 0,
        'shopify_locations'        => [
            ['id' => 'gid://shopify/Location/111', 'name' => 'Main Warehouse']
        ]
    ]);

    $plan = Plan::firstOrCreate(
        ['name' => 'Basic Plan'],
        ['sync_limit' => 100]
    );

    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => $plan->id,
        'status'             => 'active',
        'started_at'         => now()->subDays(1),
        'current_period_end' => now()->addDays(30),
    ]);

    return $shop;
}

test('Test 1: Cached Amazon product with is_mapped=false overlays DB mapping dynamically on GET /inventory/amazon', function () {
    $shop = createMappingTestShop('amazon-overlay.myshopify.com');

    // 1. Put cached Amazon products with is_mapped=false
    $marketplaceId = 'ATVPDKIKX0DER';
    $cachedProducts = [
        [
            'listing_id'                => 'LST-1',
            'sku'                       => 'AMZ-SKU-100',
            'title'                     => 'Test Coffee Mug',
            'asin'                      => 'B00112233',
            'price'                     => '19.99',
            'quantity'                  => 50,
            'status'                    => 'Active',
            'is_mapped'                 => false,
            'mapped_shopify_product_id' => null,
            'mapped_shopify_variant_id' => null,
            'mapping_id'                => null,
        ],
        [
            'listing_id'                => 'LST-2',
            'sku'                       => 'AMZ-SKU-UNMAPPED',
            'title'                     => 'Unmapped Coaster',
            'asin'                      => 'B00998877',
            'price'                     => '9.99',
            'quantity'                  => 20,
            'status'                    => 'Active',
            'is_mapped'                 => false,
            'mapped_shopify_product_id' => null,
            'mapped_shopify_variant_id' => null,
            'mapping_id'                => null,
        ]
    ];

    Cache::forever("amazon_inventory_{$shop->id}_{$marketplaceId}", $cachedProducts);
    Cache::forever("amazon_inventory_status_{$shop->id}_{$marketplaceId}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'cache_version'  => 1,
        'last_synced_at' => now()->toDateTimeString(),
    ]);

    // 2. Create DB mapping for AMZ-SKU-100
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_product_id'        => 'SP-100',
        'shopify_variant_id'        => 'SV-100',
        'shopify_inventory_item_id' => 'INV-100',
        'amazon_sku'                => 'AMZ-SKU-100',
        'quantity'                  => 50,
    ]);

    // 3. Request GET /inventory/amazon
    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->getJson('/inventory/amazon');

    $response->assertStatus(200);
    $products = $response->json('products');

    expect($products)->toBeArray()->toHaveCount(2);

    // Mapped product assertions
    $mappedItem = collect($products)->firstWhere('sku', 'AMZ-SKU-100');
    expect($mappedItem['is_mapped'])->toBeTrue();
    expect($mappedItem['mapping_id'])->toBe($mapping->id);
    expect($mappedItem['mapped_shopify_variant_id'])->toBe('SV-100');
    expect($mappedItem['mapped_shopify_product_id'])->toBe('SP-100');
    // Verify product data itself remained unchanged
    expect($mappedItem['title'])->toBe('Test Coffee Mug');
    expect($mappedItem['asin'])->toBe('B00112233');
    expect($mappedItem['price'])->toBe('19.99');
    expect($mappedItem['quantity'])->toBe(50);

    // Unmapped product assertions
    $unmappedItem = collect($products)->firstWhere('sku', 'AMZ-SKU-UNMAPPED');
    expect($unmappedItem['is_mapped'])->toBeFalse();
    expect($unmappedItem['mapping_id'])->toBeNull();
    expect($unmappedItem['mapped_shopify_variant_id'])->toBeNull();
    expect($unmappedItem['mapped_shopify_product_id'])->toBeNull();
});

test('Test 2: Cached Shopify product with is_mapped=false overlays DB mapping dynamically on GET /inventory/shopify', function () {
    $shop = createMappingTestShop('shopify-overlay.myshopify.com');

    // 1. Put cached Shopify products with is_mapped=false
    $cacheKey = "shopify_inventory_{$shop->shop}_location_{$shop->selected_location_index}";
    $cachedShopify = [
        [
            'pid'               => '991',
            'vid'               => '8821',
            'inventory_item_id' => '7731',
            'product'           => 'Cotton T-Shirt',
            'variant'           => 'Small / White',
            'sku'               => 'TSHIRT-WHT-S',
            'available'         => 25,
            'committed'         => 0,
            'on_hand'           => 25,
            'unavailable'       => 0,
            'qty'               => 25,
            'status'            => 'synced',
            'image'             => null,
            'is_mapped'         => false,
            'mapped_sku'        => null,
            'mapping_id'        => null,
        ],
        [
            'pid'               => '992',
            'vid'               => '8822',
            'inventory_item_id' => '7732',
            'product'           => 'Denim Jeans',
            'variant'           => '32 / Blue',
            'sku'               => 'JEANS-BLU-32',
            'available'         => 15,
            'committed'         => 0,
            'on_hand'           => 15,
            'unavailable'       => 0,
            'qty'               => 15,
            'status'            => 'synced',
            'image'             => null,
            'is_mapped'         => false,
            'mapped_sku'        => null,
            'mapping_id'        => null,
        ],
    ];

    Cache::put($cacheKey, $cachedShopify, 600);

    // 2. Create DB mapping for variant 8821
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_product_id'        => '991',
        'shopify_variant_id'        => '8821',
        'shopify_inventory_item_id' => '7731',
        'amazon_sku'                => 'AMZ-TSHIRT-WHT-S',
        'quantity'                  => 25,
    ]);

    // 3. Request GET /inventory/shopify
    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->getJson('/inventory/shopify');

    $response->assertStatus(200);
    $items = $response->json();

    expect($items)->toBeArray()->toHaveCount(2);

    // Mapped variant assertions
    $mappedVariant = collect($items)->firstWhere('vid', '8821');
    expect($mappedVariant['is_mapped'])->toBeTrue();
    expect($mappedVariant['mapping_id'])->toBe($mapping->id);
    expect($mappedVariant['mapped_sku'])->toBe('AMZ-TSHIRT-WHT-S');
    // Verify product fields remained intact
    expect($mappedVariant['product'])->toBe('Cotton T-Shirt');
    expect($mappedVariant['sku'])->toBe('TSHIRT-WHT-S');
    expect($mappedVariant['available'])->toBe(25);

    // Unmapped variant assertions
    $unmappedVariant = collect($items)->firstWhere('vid', '8822');
    expect($unmappedVariant['is_mapped'])->toBeFalse();
    expect($unmappedVariant['mapping_id'])->toBeNull();
    expect($unmappedVariant['mapped_sku'])->toBeNull();
});

test('Test 3: Unmapped products return clean unmapped structure', function () {
    $shop = createMappingTestShop('unmapped-clean.myshopify.com');

    Cache::forever("amazon_inventory_{$shop->id}_ATVPDKIKX0DER", [
        [
            'sku'                       => 'AMZ-RAW-SKU',
            'title'                     => 'Raw Product',
            'is_mapped'                 => false,
            'mapped_shopify_product_id' => null,
            'mapped_shopify_variant_id' => null,
            'mapping_id'                => null,
        ]
    ]);
    Cache::forever("amazon_inventory_status_{$shop->id}_ATVPDKIKX0DER", [
        'refreshing'     => false,
        'sync_completed' => true,
        'cache_version'  => 1,
        'last_synced_at' => now()->toDateTimeString(),
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->getJson('/inventory/amazon');

    $products = $response->json('products');
    expect($products[0]['is_mapped'])->toBeFalse();
    expect($products[0]['mapping_id'])->toBeNull();
    expect($products[0]['mapped_shopify_variant_id'])->toBeNull();
    expect($products[0]['mapped_shopify_product_id'])->toBeNull();
});

test('Test 4: Mapping creation preserves Shopify cache and syncs DB', function () {
    $shop = createMappingTestShop('map-creation-cache.myshopify.com');

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'SP-999',
        'title'      => 'Test Hat',
        'variants'   => [
            [
                'id'                 => 'VAR-1',
                'title'              => 'One Size',
                'inventory_item_id'  => 'INV-1',
                'inventory_quantity' => 10,
            ]
        ]
    ]);

    $cacheKey = "shopify_inventory_{$shop->shop}_location_{$shop->selected_location_index}";
    Cache::put($cacheKey, [['vid' => 'VAR-1', 'product' => 'Test Hat']], 600);

    // Save product mapping
    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->postJson('/inventory/save-product-mapping', [
        'amazon_sku'                => 'AMZ-HAT-1',
        'product_id'                => $product->id,
        'variant_id'                => 'VAR-1',
        'shopify_product_id'        => 'SP-999',
        'shopify_variant_id'        => 'VAR-1',
        'shopify_inventory_item_id' => 'INV-1',
    ]);

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();

    // Verify mapping exists in DB
    expect(ProductMarketplaceMapping::where('shop_id', $shop->id)->where('amazon_sku', 'AMZ-HAT-1')->exists())->toBeTrue();

    // Verify Shopify inventory cache was NOT deleted/invalidated
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('Test 5: Unmapping removes DB record, subsequent requests reflect unmapped state, and cache is preserved', function () {
    $shop = createMappingTestShop('unmap-preserve-cache.myshopify.com');

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_product_id'        => 'SP-888',
        'shopify_variant_id'        => 'VAR-888',
        'shopify_inventory_item_id' => 'INV-888',
        'amazon_sku'                => 'AMZ-BAG-1',
        'quantity'                  => 5,
    ]);

    $cacheKey = "shopify_inventory_{$shop->shop}_location_{$shop->selected_location_index}";
    Cache::put($cacheKey, [['vid' => 'VAR-888', 'product' => 'Travel Bag']], 600);

    // Unmap
    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->deleteJson("/inventory/unmap/{$mapping->id}");

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();

    // Verify removed from DB
    expect(ProductMarketplaceMapping::find($mapping->id))->toBeNull();

    // Verify Shopify inventory cache was NOT deleted
    expect(Cache::has($cacheKey))->toBeTrue();

    // Subsequent GET /inventory/shopify reflects unmapped state
    $shopifyRes = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->getJson('/inventory/shopify');

    $items = $shopifyRes->json();
    expect($items[0]['is_mapped'])->toBeFalse();
    expect($items[0]['mapping_id'])->toBeNull();
    expect($items[0]['mapped_sku'])->toBeNull();
});

test('Test 6: Existing Amazon and Shopify product/inventory retrieval contracts and structures remain intact', function () {
    $shop = createMappingTestShop('contracts-intact.myshopify.com');

    // 1. Amazon Contract Validation
    $marketplaceId = 'ATVPDKIKX0DER';
    Cache::forever("amazon_inventory_{$shop->id}_{$marketplaceId}", [
        [
            'listing_id'          => 'LST-CONTRACT',
            'sku'                 => 'AMZ-CONTRACT-SKU',
            'title'               => 'Contract Item',
            'description'         => 'Item description',
            'asin'                => 'B0CONTRACT',
            'price'               => '49.99',
            'quantity'            => 100,
            'status'              => 'Active',
            'fulfillment_channel' => 'DEFAULT',
            'shipping_group'      => 'Standard',
            'is_mapped'           => false,
            'mapped_shopify_product_id' => null,
            'mapped_shopify_variant_id' => null,
            'mapping_id'          => null,
        ]
    ]);
    Cache::forever("amazon_inventory_status_{$shop->id}_{$marketplaceId}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'cache_version'  => 1,
        'last_synced_at' => now()->toDateTimeString(),
    ]);

    $amzRes = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->getJson('/inventory/amazon');

    $amzRes->assertStatus(200);
    $amzData = $amzRes->json();
    expect($amzData)->toHaveKeys(['products', 'status']);
    expect($amzData['products'][0])->toHaveKeys([
        'listing_id',
        'sku',
        'title',
        'asin',
        'price',
        'quantity',
        'status',
        'fulfillment_channel',
        'shipping_group',
        'is_mapped',
        'mapping_id',
        'mapped_shopify_variant_id',
        'mapped_shopify_product_id',
    ]);

    // 2. Shopify Contract Validation
    $cacheKey = "shopify_inventory_{$shop->shop}_location_{$shop->selected_location_index}";
    Cache::put($cacheKey, [
        [
            'pid'               => '999',
            'vid'               => '888',
            'inventory_item_id' => '777',
            'product'           => 'Shopify Contract Item',
            'variant'           => 'Default',
            'sku'               => 'SHP-CONTRACT-SKU',
            'available'         => 30,
            'committed'         => 5,
            'on_hand'           => 35,
            'unavailable'       => 5,
            'qty'               => 30,
            'status'            => 'synced',
            'image'             => 'https://example.com/img.png',
            'is_mapped'         => false,
            'mapped_sku'        => null,
            'mapping_id'        => null,
        ]
    ], 600);

    $shpRes = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
    ])->getJson('/inventory/shopify');

    $shpRes->assertStatus(200);
    $shpItems = $shpRes->json();
    expect($shpItems[0])->toHaveKeys([
        'pid',
        'vid',
        'inventory_item_id',
        'product',
        'variant',
        'sku',
        'available',
        'committed',
        'on_hand',
        'unavailable',
        'qty',
        'status',
        'image',
        'is_mapped',
        'mapped_sku',
        'mapping_id',
    ]);
});
