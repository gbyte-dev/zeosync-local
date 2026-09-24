<?php

use App\Models\ProductSchema;
use App\Models\Shop;
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

    if (!Schema::hasTable('product_schemas')) {
        Schema::create('product_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('product_type')->unique();
            $table->json('schema_json')->nullable();
            $table->json('parsed_json')->nullable();
            $table->string('schema_version')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    Shop::query()->forceDelete();
    ProductSchema::query()->delete();
    Cache::flush();
});

function createCategoryTestShop(array $attributes = []): Shop
{
    $random = uniqid() . '-' . mt_rand(1000, 9999);
    return Shop::create(array_merge([
        'shop' => 'cat-test-' . $random . '.myshopify.com',
        'shop_name' => 'Category Test Store ' . $random,
        'email' => 'cat' . $random . '@example.com',
        'access_token' => 'shpat_test_' . $random,
        'is_active' => true,
    ], $attributes));
}

function authCategorySession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

test('selectCategory page renders ZeoSync sidebar with active Products menu and Amazon Products sublink', function () {
    $shop = createCategoryTestShop([
        'amazon_seller_id' => 'AMZ_CAT_TEST',
        'amazon_refresh_token' => 'amz_refresh_cat',
    ]);

    $schema = ProductSchema::create([
        'product_type' => 'LUGGAGE_' . uniqid(),
        'is_active' => 1,
        'schema_json' => ['title' => 'Luggage'],
        'parsed_json' => ['fields' => []],
    ]);

    $response = $this->withSession(authCategorySession($shop))
        ->get('/selectCategory?shop=' . $shop->shop);

    $response->assertStatus(200);

    // Verify ZeoSync sidebar is present in the rendered HTML
    $response->assertSee('id="sidebar"', false);
    $response->assertSee('class="sidebar"', false);
    $response->assertSee('Dashboard', false);
    $response->assertSee('Products', false);
    $response->assertSee('Inventory', false);
    $response->assertSee('Settings', false);

    // Verify Products menu is active and expanded
    $response->assertSee('id="productsMenu"', false);
    $response->assertSee('Amazon Products', false);
    $response->assertSee('Shopify Products', false);

    // Verify page content
    $response->assertSee('Select Product Category', false);
    $response->assertSee('Choose the Amazon product category', false);
});

test('submitting category selection redirects to admin.product.store with active shop', function () {
    $shop = createCategoryTestShop([
        'amazon_seller_id' => 'AMZ_CAT_TEST2',
        'amazon_refresh_token' => 'amz_refresh_cat2',
    ]);

    $schema = ProductSchema::create([
        'product_type' => 'SHOES_' . uniqid(),
        'is_active' => 1,
        'schema_json' => ['title' => 'Shoes'],
        'parsed_json' => ['fields' => []],
    ]);

    $response = $this->withSession(authCategorySession($shop))
        ->post('/selectCategory', [
            'category_id' => $schema->id,
            'shop' => $shop->shop,
        ]);

    $response->assertRedirect(route('admin.product.store', [
        'schemaId' => $schema->id,
        'shop' => $shop->shop,
    ]));
});
