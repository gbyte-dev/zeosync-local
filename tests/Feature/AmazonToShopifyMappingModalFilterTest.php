<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Queue::fake();

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
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('amazon_seller_id')->nullable();
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
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_parent_asin')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('quantity')->nullable();
            $table->integer('amazon_quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->softDeletes();
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

    ProductMarketplaceMapping::query()->delete();
    Product::query()->delete();
    ShopSubscription::query()->delete();
    Plan::query()->delete();
    Shop::query()->delete();

    Plan::create([
        'id'         => 1,
        'name'       => 'Pro Plan',
        'sync_limit' => 100,
    ]);
});

function createModalFilterShop(int $id, string $domain = 'modal-test.myshopify.com'): Shop
{
    $shop = Shop::create([
        'id'                     => $id,
        'shop'                   => $domain,
        'shop_name'              => 'Modal Test Store',
        'email'                  => 'test@example.com',
        'access_token'           => 'shpat_test_token_123',
        'is_active'              => 1,
        'shopify_locations'      => [
            ['id' => 'gid://shopify/Location/101', 'name' => 'Main Warehouse'],
        ],
        'selected_location_index'=> 0,
        'amazon_seller_id'       => 'SELLER123',
        'amazon_marketplace_id'  => 'ATVPDKIKX0DER',
        'amazon_mws_region'      => 'na',
    ]);

    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => 1,
        'status'             => 'active',
        'started_at'         => now()->subDays(1),
        'current_period_end' => now()->addDays(30),
    ]);

    return $shop;
}

test('1. Product with all variants mapped is excluded from shopifyProducts dropdown', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_100',
        'title'      => 'DEELMO Mens Shirt',
        'variants'   => [
            ['id' => 'var_s', 'title' => 'Red / S', 'inventory_item_id' => 'inv_s'],
            ['id' => 'var_m', 'title' => 'Red / M', 'inventory_item_id' => 'inv_m'],
            ['id' => 'var_l', 'title' => 'Red / L', 'inventory_item_id' => 'inv_l'],
        ],
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'product_id'         => $product->id,
        'shopify_product_id' => 'prod_100',
        'shopify_variant_id' => 'var_s',
        'amazon_sku'         => 'SKU-S',
        'sync_status'        => 'pending',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'product_id'         => $product->id,
        'shopify_product_id' => 'prod_100',
        'shopify_variant_id' => 'var_m',
        'amazon_sku'         => 'SKU-M',
        'sync_status'        => 'success',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'product_id'         => $product->id,
        'shopify_product_id' => 'prod_100',
        'shopify_variant_id' => 'var_l',
        'amazon_sku'         => 'SKU-L',
        'sync_status'        => 'active',
    ]);

    $response = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));

    $response->assertOk();
    $response->assertJson(['success' => true]);
    expect($response->json('products'))->toBeEmpty();
});

test('2. Product with pending and success variants only is excluded', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_200',
        'title'      => 'Product Pending and Success',
        'variants'   => [
            ['id' => 'v1', 'title' => 'V1', 'inventory_item_id' => 'inv_1'],
            ['id' => 'v2', 'title' => 'V2', 'inventory_item_id' => 'inv_2'],
        ],
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_200',
        'shopify_variant_id' => 'v1',
        'amazon_sku'         => 'AMZ-V1',
        'sync_status'        => 'pending',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_200',
        'shopify_variant_id' => 'v2',
        'amazon_sku'         => 'AMZ-V2',
        'sync_status'        => 'success',
    ]);

    $response = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));

    $response->assertOk();
    expect($response->json('products'))->toBeEmpty();
});

test('3. Product with success and active variants only is excluded', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_300',
        'title'      => 'Product Success and Active',
        'variants'   => [
            ['id' => 'v1', 'title' => 'V1', 'inventory_item_id' => 'inv_1'],
            ['id' => 'v2', 'title' => 'V2', 'inventory_item_id' => 'inv_2'],
        ],
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_300',
        'shopify_variant_id' => 'v1',
        'amazon_sku'         => 'AMZ-V1',
        'sync_status'        => 'success',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_300',
        'shopify_variant_id' => 'v2',
        'amazon_sku'         => 'AMZ-V2',
        'sync_status'        => 'active',
    ]);

    $response = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));

    $response->assertOk();
    expect($response->json('products'))->toBeEmpty();
});

test('4. Product with failed and success variants only is excluded (failed mappings count as occupied)', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_400',
        'title'      => 'Product Failed and Success',
        'variants'   => [
            ['id' => 'v1', 'title' => 'V1', 'inventory_item_id' => 'inv_1'],
            ['id' => 'v2', 'title' => 'V2', 'inventory_item_id' => 'inv_2'],
        ],
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_400',
        'shopify_variant_id' => 'v1',
        'amazon_sku'         => 'AMZ-V1',
        'sync_status'        => 'failed',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_400',
        'shopify_variant_id' => 'v2',
        'amazon_sku'         => 'AMZ-V2',
        'sync_status'        => 'success',
    ]);

    $response = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));

    $response->assertOk();
    expect($response->json('products'))->toBeEmpty();
});

test('5. Product with at least one available variant is included in shopifyProducts dropdown', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_500',
        'title'      => 'DEELMO Mens Shirt',
        'variants'   => [
            ['id' => 'var_s', 'title' => 'Red / S', 'inventory_item_id' => 'inv_s'],
            ['id' => 'var_m', 'title' => 'Red / M', 'inventory_item_id' => 'inv_m'],
            ['id' => 'var_l', 'title' => 'Red / L', 'inventory_item_id' => 'inv_l'],
        ],
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_500',
        'shopify_variant_id' => 'var_s',
        'amazon_sku'         => 'SKU-S',
        'sync_status'        => 'pending',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_500',
        'shopify_variant_id' => 'var_m',
        'amazon_sku'         => 'SKU-M',
        'sync_status'        => 'success',
    ]);

    $response = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));

    $response->assertOk();
    $products = $response->json('products');
    expect($products)->toHaveCount(1);
    expect($products[0]['id'])->toBe($product->id);
    expect($products[0]['title'])->toBe('DEELMO Mens Shirt');
    expect($products[0]['shopify_id'])->toBe('prod_500');
});

test('6. Partially mapped product still appears alongside unmapped products', function () {
    $shop = createModalFilterShop(1);

    $product1 = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_601',
        'title'      => 'Product Partially Mapped',
        'variants'   => [
            ['id' => 'v1', 'title' => 'V1', 'inventory_item_id' => 'inv_1'],
            ['id' => 'v2', 'title' => 'V2', 'inventory_item_id' => 'inv_2'],
        ],
    ]);

    $product2 = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_602',
        'title'      => 'Product Fully Unmapped',
        'variants'   => [
            ['id' => 'v3', 'title' => 'V3', 'inventory_item_id' => 'inv_3'],
        ],
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_601',
        'shopify_variant_id' => 'v1',
        'amazon_sku'         => 'AMZ-V1',
        'sync_status'        => 'pending',
    ]);

    $response = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));

    $response->assertOk();
    $products = $response->json('products');
    expect($products)->toHaveCount(2);
    $titles = collect($products)->pluck('title')->toArray();
    expect($titles)->toContain('Product Partially Mapped', 'Product Fully Unmapped');
});

test('7. Existing variant endpoint still returns only available variants', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_700',
        'title'      => 'DEELMO Mens Shirt',
        'variants'   => [
            ['id' => 'var_s', 'title' => 'Red / S', 'inventory_item_id' => 'inv_s'],
            ['id' => 'var_m', 'title' => 'Red / M', 'inventory_item_id' => 'inv_m'],
            ['id' => 'var_l', 'title' => 'Red / L', 'inventory_item_id' => 'inv_l'],
        ],
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_700',
        'shopify_variant_id' => 'var_s',
        'amazon_sku'         => 'SKU-S',
        'sync_status'        => 'pending',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_product_id' => 'prod_700',
        'shopify_variant_id' => 'var_m',
        'amazon_sku'         => 'SKU-M',
        'sync_status'        => 'success',
    ]);

    $response = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.variants', [
            'product' => $product->id,
            'shop'    => $shop->shop,
        ]));

    $response->assertOk();
    expect($response->json('has_variants'))->toBeTrue();
    expect($response->json('total_variants_count'))->toBe(3);
    expect($response->json('available_variants_count'))->toBe(1);
    expect($response->json('variants'))->toHaveCount(1);
    expect($response->json('variants.0.id'))->toBe('var_l');
    expect($response->json('variants.0.title'))->toBe('Red / L');
});

test('8. Unmapping a variant makes the product available again in shopifyProducts', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_800',
        'title'      => 'DEELMO Single Variant Product',
        'variants'   => [
            ['id' => 'var_single', 'title' => 'Default Title', 'inventory_item_id' => 'inv_single'],
        ],
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'product_id'         => $product->id,
        'shopify_product_id' => 'prod_800',
        'shopify_variant_id' => 'var_single',
        'amazon_sku'         => 'SKU-SINGLE',
        'sync_status'        => 'success',
    ]);

    // Product is fully mapped -> should be excluded
    $response1 = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));
    $response1->assertOk();
    expect($response1->json('products'))->toBeEmpty();

    // User unmaps the product
    $unmapResponse = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->deleteJson(route('inventory.unmap', [
            'mapping' => $mapping->id,
            'shop'    => $shop->shop,
        ]));
    $unmapResponse->assertOk();
    $unmapResponse->assertJson(['success' => true]);

    // Product should now be available again in shopifyProducts
    $response2 = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->getJson(route('inventory.shopify.products', ['shop' => $shop->shop]));
    $response2->assertOk();
    $products = $response2->json('products');
    expect($products)->toHaveCount(1);
    expect($products[0]['id'])->toBe($product->id);
});

test('9. Existing Amazon to Shopify mapping save works and isolates shop mapping', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_900',
        'title'      => 'Test Product for Save',
        'variants'   => [
            ['id' => 'var_avail', 'title' => 'Available Variant', 'inventory_item_id' => 'inv_avail', 'inventory_quantity' => 15],
        ],
    ]);

    $saveResponse = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->postJson(route('inventory.save.mapping'), [
            'shop'                      => $shop->shop,
            'amazon_sku'                => 'AMZ-SKU-900',
            'product_id'                => $product->id,
            'variant_id'                => 'var_avail',
            'shopify_product_id'        => 'prod_900',
            'shopify_variant_id'        => 'var_avail',
            'shopify_inventory_item_id' => 'inv_avail',
        ]);

    $saveResponse->assertOk();
    $saveResponse->assertJson(['success' => true, 'message' => 'Product mapped successfully.']);

    expect(ProductMarketplaceMapping::where('shop_id', $shop->id)->where('shopify_variant_id', 'var_avail')->exists())->toBeTrue();
});

test('10. Existing Shopify to Amazon mapping save works as expected', function () {
    $shop = createModalFilterShop(1);

    $product = Product::create([
        'shop_id'    => $shop->id,
        'shopify_id' => 'prod_1000',
        'title'      => 'Shopify to Amazon Product',
        'variants'   => [
            ['id' => 'var_s2a', 'title' => 'Variant S2A', 'inventory_item_id' => 'inv_s2a', 'inventory_quantity' => 10],
        ],
    ]);

    $saveResponse = $this->withSession(['_shopify_verified_shop' => $shop->shop, 'active_shop' => $shop->shop])
        ->postJson(route('inventory.save.amazon.mapping'), [
            'shop'               => $shop->shop,
            'product_id'         => $product->shopify_id,
            'shopify_variant_id' => 'var_s2a',
            'amazon_sku'         => 'AMZ-S2A-SKU',
        ]);

    $saveResponse->assertOk();
    $saveResponse->assertJson(['success' => true, 'message' => 'Amazon product mapped successfully.']);

    expect(ProductMarketplaceMapping::where('shop_id', $shop->id)->where('amazon_sku', 'AMZ-S2A-SKU')->exists())->toBeTrue();
});
