<?php

use App\Models\AdminSetting;
use App\Models\AllProduct;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductSchema;
use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
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
            $table->string('amazon_marketplace_id')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_schemas')) {
        Schema::create('product_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('product_type')->unique();
            $table->longText('schema_json')->nullable();
            $table->json('parsed_json')->nullable();
            $table->string('schema_version')->default('1.0');
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('allproducts')) {
        Schema::create('allproducts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('submission_status')->nullable();
            $table->timestamp('submitted_on')->nullable();
            $table->string('producttype')->nullable();
            $table->longText('final_json')->nullable();
            $table->longText('filled_json')->nullable();
            $table->unsignedBigInteger('schema_id')->nullable();
            $table->string('sku');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['user_id', 'sku'], 'allproducts_user_id_sku_unique');
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('product_type')->nullable();
            $table->string('category')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->string('vendor')->nullable();
            $table->string('tags')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('metafields')->nullable();
            $table->boolean('synced_to_amazon')->default(0);
            $table->json('local_images')->nullable();
            $table->boolean('needs_resync')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_attributes')) {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('attribute_name');
            $table->longText('attribute_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('categories')) {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('slug');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('amazon_products')) {
        Schema::create('amazon_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('amazon_title')->nullable();
            $table->json('search_terms')->nullable();
            $table->json('platinum_keywords')->nullable();
            $table->json('bullet_points')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('subject_matter')->nullable();
            $table->string('sku')->nullable();
            $table->json('intended_use')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_sync_logs')) {
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('status')->nullable();
            $table->text('message')->nullable();
            $table->text('error_message')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('product_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->unique();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }
});

function createProductCrossAccessTestShop(string $domain): Shop
{
    $shop = Shop::create([
        'shop' => $domain,
        'shop_name' => "Shop " . uniqid(),
        'email' => "merchant_" . uniqid() . "@example.com",
        'is_active' => 1,
        'access_token' => 'token_' . uniqid(),
        'access_token_expires_at' => now()->addDays(30),
        'refresh_token' => 'ref_token_' . uniqid(),
        'refresh_token_expires_at' => now()->addDays(90),
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'shopify_locations' => [['id' => 111, 'name' => 'Main Location']],
        'selected_location_index' => 0,
    ]);

    $plan = Plan::firstOrCreate([
        'slug' => 'test-plan',
    ], [
        'name' => 'Test Plan',
        'price' => 0,
        'product_limit' => 0,
        'is_active' => true,
    ]);

    ShopSubscription::firstOrCreate([
        'shop_id' => $shop->id,
    ], [
        'plan_id' => $plan->id,
        'status' => 'active',
        'price' => 0,
    ]);

    return $shop;
}

function productCrossAccessAuthSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

// 1. Current shop can access its own product
it('1. Current shop can access its own product on editProduct and productEdit', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');

    Http::fake([
        'https://shop-a.myshopify.com/admin/api/*/products/1001.json' => Http::response([
            'product' => [
                'id' => 1001,
                'title' => 'Shop A Valid Product',
                'variants' => [
                    ['id' => 2001, 'price' => '19.99', 'sku' => 'SKU-A-1', 'inventory_item_id' => 3001]
                ],
                'images' => [],
                'options' => [],
            ]
        ], 200),
        'https://shop-a.myshopify.com/admin/api/*/inventory_levels.json*' => Http::response([
            'inventory_levels' => [
                ['inventory_item_id' => 3001, 'available' => 10]
            ]
        ], 200),
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->get('/editProduct/1001');
    $response->assertStatus(200);
    $response->assertSee('Shop A Valid Product');
});

// 2. Current shop can edit its own product
it('2. Current shop can edit its own product', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');

    $schema = ProductSchema::create([
        'product_type' => 'WIDGET',
        'schema_json' => json_encode(['title' => 'Widget']),
        'parsed_json' => [['name' => 'item_name', 'title' => 'Item Name', 'type' => 'string', 'required' => true]],
        'is_active' => 1,
    ]);

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-A-OWNED',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productA->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Original Title A',
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->get("/productEdit/{$productA->id}");
    $response->assertStatus(200);
    $response->assertSee('Original Title A');
});

// 3. Non-existent product ID -> Product not found behavior (redirect to Dashboard with toast)
it('3. Non-existent product ID redirects to Dashboard with Product not found toast', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');

    Http::fake([
        'https://shop-a.myshopify.com/admin/api/*/products/999999.json' => Http::response([
            'errors' => 'Not Found'
        ], 404),
    ]);

    // Shopify route
    $responseShopify = $this->withSession(productCrossAccessAuthSession($shopA))->get('/editProduct/999999');
    $responseShopify->assertRedirect();
    $responseShopify->assertSessionHas('error', 'Product not found');
    expect($responseShopify->headers->get('Location'))->toContain('/dashboard');

    // Schema route
    $responseSchema = $this->withSession(productCrossAccessAuthSession($shopA))->get('/productEdit/999999');
    $responseSchema->assertRedirect();
    $responseSchema->assertSessionHas('error', 'Product not found');
    expect($responseSchema->headers->get('Location'))->toContain('/dashboard');
});

// 4. Product ID belonging to another shop -> Product not found behavior
it('4. Product ID belonging to another shop redirects to Dashboard with Product not found toast', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');
    $shopB = createProductCrossAccessTestShop('shop-b.myshopify.com');

    $schema = ProductSchema::create([
        'product_type' => 'GADGET',
        'schema_json' => json_encode(['title' => 'Gadget']),
        'parsed_json' => [['name' => 'item_name', 'title' => 'Item Name', 'type' => 'string', 'required' => true]],
        'is_active' => 1,
    ]);

    $productB = AllProduct::create([
        'user_id' => $shopB->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-B-SECRET',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productB->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Confidential Shop B Title',
    ]);

    Http::fake([
        'https://shop-a.myshopify.com/admin/api/*/products/8888.json' => Http::response([
            'errors' => 'Not Found'
        ], 404),
    ]);

    // Shop A tries to open Shop B product via /productEdit/{id}
    $responseSchema = $this->withSession(productCrossAccessAuthSession($shopA))->get("/productEdit/{$productB->id}");
    $responseSchema->assertRedirect();
    $responseSchema->assertSessionHas('error', 'Product not found');
    expect($responseSchema->headers->get('Location'))->toContain('/dashboard');

    // Shop A tries to open Shop B product via /editProduct/{id}
    $responseShopify = $this->withSession(productCrossAccessAuthSession($shopA))->get('/editProduct/8888');
    $responseShopify->assertRedirect();
    $responseShopify->assertSessionHas('error', 'Product not found');
    expect($responseShopify->headers->get('Location'))->toContain('/dashboard');
});

// 5. Cross-shop product data is never returned
it('5. Cross-shop product data is never returned in response', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');
    $shopB = createProductCrossAccessTestShop('shop-b.myshopify.com');

    $schema = ProductSchema::create([
        'product_type' => 'SECRET_TYPE',
        'schema_json' => json_encode(['title' => 'Secret']),
        'parsed_json' => [['name' => 'item_name', 'title' => 'Item Name', 'type' => 'string', 'required' => true]],
        'is_active' => 1,
    ]);

    $productB = AllProduct::create([
        'user_id' => $shopB->id,
        'schema_id' => $schema->id,
        'sku' => 'SUPER-SECRET-SKU-B',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productB->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Top Secret Shop B Product Data 12345',
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->get("/productEdit/{$productB->id}");
    $response->assertDontSee('Top Secret Shop B Product Data 12345');
    $response->assertDontSee('SUPER-SECRET-SKU-B');
});

// 6. Cross-shop product cannot be edited
it('6. Cross-shop product cannot be edited by unauthorized shop', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');
    $shopB = createProductCrossAccessTestShop('shop-b.myshopify.com');

    $schema = ProductSchema::create([
        'product_type' => 'DEVICE',
        'schema_json' => json_encode(['title' => 'Device']),
        'parsed_json' => [['name' => 'item_name', 'title' => 'Item Name', 'type' => 'string', 'required' => true]],
        'is_active' => 1,
    ]);

    $productB = AllProduct::create([
        'user_id' => $shopB->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-B-DEVICE',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productB->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Untouched Shop B Name',
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->post("/addproduct/{$productB->id}", [
        'schema_id' => $schema->id,
        'attributes' => [
            'item_name' => 'Attempted Overwrite by Shop A',
        ],
    ]);

    $attribute = ProductAttribute::where('product_id', $productB->id)->where('attribute_name', 'item_name')->first();
    expect($attribute->attribute_value)->toBe('Untouched Shop B Name');
});

// 7. Cross-shop product cannot be deleted
it('7. Cross-shop product cannot be deleted by unauthorized shop', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');
    $shopB = createProductCrossAccessTestShop('shop-b.myshopify.com');

    $schema = ProductSchema::create([
        'product_type' => 'ASSET',
        'schema_json' => json_encode(['title' => 'Asset']),
        'parsed_json' => [['name' => 'item_name', 'title' => 'Item Name', 'type' => 'string', 'required' => true]],
        'is_active' => 1,
    ]);

    $productB = AllProduct::create([
        'user_id' => $shopB->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-B-ASSET',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productB->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Shop B Asset Attribute',
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->get("/remove_drafts/{$productB->id}");

    expect(AllProduct::find($productB->id))->not->toBeNull();
    expect(ProductAttribute::where('product_id', $productB->id)->count())->toBe(1);
});

// 8. Toast message is exactly: "Product not found"
it('8. Toast message in session error is exactly "Product not found"', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');

    Http::fake([
        'https://shop-a.myshopify.com/admin/api/*/products/55555.json' => Http::response([
            'errors' => 'Not Found'
        ], 404),
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->get('/editProduct/55555');
    $response->assertSessionHas('error', 'Product not found');
    expect(session('error'))->toBe('Product not found');
});

// 9. Redirect destination is Dashboard
it('9. Redirect destination is Dashboard', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');

    Http::fake([
        'https://shop-a.myshopify.com/admin/api/*/products/77777.json' => Http::response([
            'errors' => 'Not Found'
        ], 404),
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->get('/editProduct/77777');
    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('/dashboard');
});

// 10. Existing valid product behavior remains unchanged
it('10. Existing valid product behavior remains unchanged for authorized store', function () {
    $shopA = createProductCrossAccessTestShop('shop-a.myshopify.com');

    $schema = ProductSchema::create([
        'product_type' => 'BOOK',
        'schema_json' => json_encode(['title' => 'Book']),
        'parsed_json' => [['name' => 'item_name', 'title' => 'Item Name', 'type' => 'string', 'required' => true]],
        'is_active' => 1,
    ]);

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-BOOK-A',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productA->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'My Great Novel',
    ]);

    $response = $this->withSession(productCrossAccessAuthSession($shopA))->get("/productEdit/{$productA->id}");
    $response->assertStatus(200);
    $response->assertSee('My Great Novel');
});
