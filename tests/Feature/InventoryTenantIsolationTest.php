<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AmazonService;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*inventory_levels/set.json*' => Http::response(['inventory_level' => ['available' => 10]], 200),
        '*products.json*'             => Http::response(['products' => []], 200),
        '*'                           => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
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
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('shopify_status')->nullable();
            $table->text('shopify_error')->nullable();
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->string('tags')->nullable();
            $table->string('category')->nullable();
            $table->string('collections')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('metafields')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->boolean('synced_to_amazon')->default(0);
            $table->boolean('needs_resync')->default(0);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->string('local_images')->nullable();
            $table->string('amazon_product_id')->nullable();
            $table->softDeletes();
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

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
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

    ProductMarketplaceMapping::truncate();
    Product::truncate();
    ShopSubscription::truncate();
    Plan::truncate();
    Shop::truncate();

    Plan::create([
        'id'         => 1,
        'name'       => 'Pro Plan',
        'sync_limit' => 100,
    ]);
});

function createTestShop(int $id, string $domain, string $name = 'Store'): Shop
{
    $shop = new Shop();
    $shop->id = $id;
    $shop->shop = $domain;
    $shop->shop_name = $name;
    $shop->email = "{$domain}@example.com";
    $shop->access_token = "token-{$id}";
    $shop->is_active = 1;
    $shop->shopify_locations = [
        ['id' => 'loc_123', 'name' => 'Main Warehouse']
    ];
    $shop->selected_location_index = 0;
    $shop->amazon_marketplace_id = 'ATVPDKIKX0DER';
    $shop->amazon_mws_region = 'us-east-1';
    $shop->save();

    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => 1,
        'status'             => 'active',
        'started_at'         => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    return $shop;
}

function mockShopAuth(Shop $shop): void
{
    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => $shop->shop,
        'shop_model' => $shop,
        'payload'    => ['dest' => "https://{$shop->shop}"],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);
}

// -------------------------------------------------------------------------
// 1. POST /inventory/shopify/update Tests
// -------------------------------------------------------------------------

it('Test 1: Shop A can update its own Shopify inventory', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    mockShopAuth($shopA);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'inv_item_111',
        'quantity'          => 15,
    ]);

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
});

it('Test 2: Shop A cannot update Shop B inventory by supplying shop=ShopB', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    // Create a mapping under Shop B
    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'                   => $shopB->id,
        'shopify_inventory_item_id' => 'inv_item_b',
        'amazon_sku'                => 'AMZ-SKU-B',
        'quantity'                  => 5,
    ]);

    // Shop A sends request with shop=store-b.myshopify.com
    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'shop'              => 'store-b.myshopify.com',
        'inventory_item_id' => 'inv_item_b',
        'quantity'          => 0,
    ]);

    // Request is processed under Shop A context, NOT Shop B
    // Mapping for Shop B must NOT be altered
    $mappingB->refresh();
    expect((int) $mappingB->quantity)->toBe(5);
    expect($mappingB->sync_status)->not->toBe('success');
});

// -------------------------------------------------------------------------
// 2. POST /inventory/save-product-mapping Tests
// -------------------------------------------------------------------------

it('Test 3: Shop A cannot create a mapping using Shop B product', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    $productB = Product::create([
        'shop_id'    => $shopB->id,
        'shopify_id' => 'shop_prod_b',
        'title'      => 'Shop B Product',
        'variants'   => [
            ['id' => 'var_b_1', 'inventory_item_id' => 'inv_b_1', 'inventory_quantity' => 10]
        ],
    ]);

    // Shop A tries to map Shop B's product
    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/save-product-mapping', [
        'amazon_sku'                => 'AMZ-ATTACK-SKU',
        'product_id'                => $productB->id,
        'variant_id'                => 'var_b_1',
        'shopify_product_id'        => 'shop_prod_b',
        'shopify_variant_id'        => 'var_b_1',
        'shopify_inventory_item_id' => 'inv_b_1',
    ]);

    $response->assertStatus(404);
    expect(ProductMarketplaceMapping::where('amazon_sku', 'AMZ-ATTACK-SKU')->exists())->toBeFalse();
});

it('Test 4: Shop A cannot save a mapping under Shop B by passing shop=ShopB', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    $productA = Product::create([
        'shop_id'    => $shopA->id,
        'shopify_id' => 'shop_prod_a',
        'title'      => 'Shop A Product',
        'variants'   => [
            ['id' => 'var_a_1', 'inventory_item_id' => 'inv_a_1', 'inventory_quantity' => 10]
        ],
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/save-product-mapping', [
        'shop'                      => 'store-b.myshopify.com',
        'amazon_sku'                => 'AMZ-SKU-A',
        'product_id'                => $productA->id,
        'variant_id'                => 'var_a_1',
        'shopify_product_id'        => 'shop_prod_a',
        'shopify_variant_id'        => 'var_a_1',
        'shopify_inventory_item_id' => 'inv_a_1',
    ]);

    $response->assertStatus(200);

    // Verified: Mapping was created for Shop A, NOT Shop B
    $mapping = ProductMarketplaceMapping::where('amazon_sku', 'AMZ-SKU-A')->first();
    expect($mapping)->not->toBeNull();
    expect((int) $mapping->shop_id)->toBe($shopA->id);
    expect((int) $mapping->shop_id)->not->toBe($shopB->id);
});

// -------------------------------------------------------------------------
// 3. POST /inventory/save-amazon-mapping Tests
// -------------------------------------------------------------------------

it('Test 5: Shop A cannot update Shop B Amazon mapping or reference Shop B product', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    $productB = Product::create([
        'shop_id'    => $shopB->id,
        'shopify_id' => 'shop_prod_b_99',
        'title'      => 'Shop B Product',
        'variants'   => [
            ['id' => 999, 'inventory_item_id' => 'inv_999', 'inventory_quantity' => 10]
        ],
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/save-amazon-mapping', [
        'shop'               => 'store-b.myshopify.com',
        'product_id'         => 'shop_prod_b_99',
        'shopify_variant_id' => 999,
        'amazon_sku'         => 'AMZ-HIJACK-SKU',
    ]);

    $response->assertStatus(404);
    expect(ProductMarketplaceMapping::where('amazon_sku', 'AMZ-HIJACK-SKU')->exists())->toBeFalse();
});

// -------------------------------------------------------------------------
// 4. DELETE /inventory/unmap/{mapping} Tests
// -------------------------------------------------------------------------

it('Test 6: Shop A cannot delete Shop B mapping even with shop=ShopB', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'                   => $shopB->id,
        'shopify_variant_id'        => 'var_b_500',
        'shopify_inventory_item_id' => 'inv_b_500',
        'amazon_sku'                => 'AMZ-B-500',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->deleteJson("/inventory/unmap/{$mappingB->id}", [
        'shop' => 'store-b.myshopify.com',
    ]);

    $response->assertStatus(403);
    expect(ProductMarketplaceMapping::where('id', $mappingB->id)->exists())->toBeTrue();
});

// -------------------------------------------------------------------------
// 5. Enumeration Prevention Tests (shopifyProducts, variants, mappings)
// -------------------------------------------------------------------------

it('Test 7: Shop A cannot enumerate Shop B Shopify products', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    Product::create([
        'shop_id'    => $shopA->id,
        'shopify_id' => 'prod_a',
        'title'      => 'Store A Item',
    ]);
    Product::create([
        'shop_id'    => $shopB->id,
        'shopify_id' => 'prod_b',
        'title'      => 'Store B Secret Item',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->getJson('/inventory/shopify-products?shop=store-b.myshopify.com');

    $response->assertStatus(200);
    $products = $response->json('products');

    expect(count($products))->toBe(1);
    expect($products[0]['title'])->toBe('Store A Item');
    expect(collect($products)->pluck('title'))->not->toContain('Store B Secret Item');
});

it('Test 8: Shop A cannot enumerate Shop B product variants', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    $productB = Product::create([
        'shop_id'    => $shopB->id,
        'shopify_id' => 'prod_b_secret',
        'title'      => 'Store B Product',
        'variants'   => [
            ['id' => 'var_b_secret', 'title' => 'Secret Variant', 'inventory_item_id' => 'inv_b_secret']
        ],
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->getJson("/inventory/shopify-product-variants/{$productB->id}?shop=store-b.myshopify.com");

    $response->assertStatus(404);
});

it('Test 9: Shop A cannot enumerate Shop B mappings', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shopA->id,
        'shopify_variant_id' => 'var_a_map',
        'amazon_sku'         => 'AMZ-A-MAP',
    ]);
    ProductMarketplaceMapping::create([
        'shop_id'            => $shopB->id,
        'shopify_variant_id' => 'var_b_map',
        'amazon_sku'         => 'AMZ-B-SECRET-MAP',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->getJson('/inventory/mappings?shop=store-b.myshopify.com');

    $response->assertStatus(200);
    $mappings = $response->json('mappings');

    expect(count($mappings))->toBe(1);
    expect($mappings[0]['amazon_sku'])->toBe('AMZ-A-MAP');
    expect(collect($mappings)->pluck('amazon_sku'))->not->toContain('AMZ-B-SECRET-MAP');
});

// -------------------------------------------------------------------------
// 6. InventoryController Method Tests (refresh, updateAmazonQuantity)
// -------------------------------------------------------------------------

it('Test 10: Shop A cannot refresh/reset Shop B inventory state', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->getJson('/inventory/refresh?shop=store-b.myshopify.com&type=shopify');

    $response->assertStatus(200);
});

it('Test 11: Shop A cannot update Shop B Amazon quantity', function () {
    $shopA = createTestShop(1, 'store-a.myshopify.com', 'Store A');
    $shopB = createTestShop(2, 'store-b.myshopify.com', 'Store B');
    mockShopAuth($shopA);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($shop) => $shop->id === $shopA->id), 'CHILD-SKU-1', 10)
        ->once()
        ->andReturn(['success' => true]);
    app()->instance(AmazonService::class, $mockAmazon);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer valid-token-a',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/amazon/CHILD-SKU-1/update-quantity?shop=store-b.myshopify.com', [
        'quantity' => 10,
    ]);

    $response->assertStatus(200);
});

// -------------------------------------------------------------------------
// 7. Unauthenticated & Tampered Requests Fail Closed
// -------------------------------------------------------------------------

it('Test 12: Unauthenticated inventory requests are rejected with 401', function () {
    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_1',
        'quantity'          => 5,
    ]);

    $response->assertStatus(401);
});

it('Test 13: Tampered Bearer token is rejected with 401', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer invalid.tampered.token',
        'Accept'        => 'application/json',
    ])->postJson('/inventory/shopify/update', [
        'inventory_item_id' => 'item_1',
        'quantity'          => 5,
    ]);

    $response->assertStatus(401);
});
