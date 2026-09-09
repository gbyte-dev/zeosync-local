<?php

use App\Models\AdminSetting;
use App\Models\AllProduct;
use App\Models\Plan;
use App\Models\ProductAttribute;
use App\Models\ProductSchema;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AmazonSuccessfulListingService;
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
            $table->string('amazon_marketplace_id')->nullable();
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

    if (!Schema::hasTable('product_attributes')) {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('attribute_name');
            $table->longText('attribute_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
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
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->text('prices')->nullable();
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('stripe_price_ids')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
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
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_sync_logs')) {
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('status')->nullable();
            $table->text('message')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }
});

function createIsolatedTestShop(string $domain): Shop
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
    ]);

    $plan = Plan::firstOrCreate([
        'slug' => 'test-unlimited',
    ], [
        'name' => 'Unlimited Test Plan',
        'price' => 0,
        'product_limit' => 0,
        'is_active' => true,
    ]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'price' => 0,
    ]);

    return $shop;
}

function createIsolatedTestSchema(): ProductSchema
{
    return ProductSchema::create([
        'product_type' => 'TEST_PRODUCT',
        'schema_json' => json_encode(['title' => 'Test Product']),
        'parsed_json' => [
            [
                'name' => 'item_name',
                'title' => 'Item Name',
                'type' => 'string',
                'required' => true,
            ],
            [
                'name' => 'brand',
                'title' => 'Brand',
                'type' => 'string',
                'required' => false,
            ],
        ],
        'is_active' => 1,
    ]);
}

function withShopAuth(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

// TEST 1: Shop A owns Product 101. Shop A requests /productEdit/101. -> allowed.
it('allows Shop A to edit its own product (TEST 1)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $schema = createIsolatedTestSchema();

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-A-101',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productA->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Shop A Cool Widget',
    ]);

    $response = $this->withSession(withShopAuth($shopA))
        ->get("/productEdit/{$productA->id}");

    $response->assertStatus(200);
    $response->assertSee('Shop A Cool Widget');
});

// TEST 2: Shop B requests /productEdit/101. -> 404. Shop A's data must NOT be returned.
it('rejects Shop B from accessing Shop A product on /productEdit/101 with 404 (TEST 2)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $shopB = createIsolatedTestShop('shop-b.myshopify.com');
    $schema = createIsolatedTestSchema();

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-A-101',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productA->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Secret Merchant A Data',
    ]);

    $response = $this->withSession(withShopAuth($shopB))
        ->get("/productEdit/{$productA->id}");

    $response->assertStatus(404);
    $response->assertDontSee('Secret Merchant A Data');
});

// TEST 3: Shop B POSTs /addproduct/101. -> rejected (404). Shop A's ProductAttribute records remain unchanged.
it('rejects Shop B POSTing to /addproduct/101 and preserves Shop A attributes (TEST 3)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $shopB = createIsolatedTestShop('shop-b.myshopify.com');
    $schema = createIsolatedTestSchema();

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-A-101',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productA->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Original Shop A Name',
    ]);

    $response = $this->withSession(withShopAuth($shopB))
        ->post("/addproduct/{$productA->id}", [
            'schema_id' => $schema->id,
            'attributes' => [
                'item_name' => 'Hacked by Shop B',
            ],
        ]);

    $response->assertStatus(404);

    $attribute = ProductAttribute::where('product_id', $productA->id)
        ->where('attribute_name', 'item_name')
        ->first();

    expect($attribute->attribute_value)->toBe('Original Shop A Name');
});

// TEST 4: Shop B sends parent_id = Shop A's product ID. -> rejected (404). No cross-tenant child is created.
it('rejects Shop B creating a child with parent_id belonging to Shop A (TEST 4)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $shopB = createIsolatedTestShop('shop-b.myshopify.com');
    $schema = createIsolatedTestSchema();

    $parentA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'PARENT-SKU-SHOP-A',
        'status' => 'draft',
    ]);

    $initialCount = AllProduct::count();

    $response = $this->withSession(withShopAuth($shopB))
        ->post("/addproduct", [
            'parent_id' => $parentA->id,
            'schema_id' => $schema->id,
            'attributes' => [
                'item_name' => 'Malicious Child',
            ],
        ]);

    $response->assertStatus(404);
    expect(AllProduct::count())->toBe($initialCount);
    expect(AllProduct::where('parent_id', $parentA->id)->where('user_id', $shopB->id)->exists())->toBeFalse();
});

// TEST 5: Shop B requests /generatePayload/101. -> rejected (404) BEFORE any Amazon operation or attribute deletion.
it('rejects Shop B generating payload for Shop A product with 404 (TEST 5)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $shopB = createIsolatedTestShop('shop-b.myshopify.com');
    $schema = createIsolatedTestSchema();

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-A-101',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productA->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Shop A Product Attribute',
    ]);

    $response = $this->withSession(withShopAuth($shopB))
        ->get("/generatePayload/{$productA->id}");

    $response->assertStatus(404);

    // Verify ProductAttribute was NOT deleted
    expect(ProductAttribute::where('product_id', $productA->id)->count())->toBe(1);
    // Verify product status unchanged
    expect(AllProduct::find($productA->id)->status)->toBe('draft');
});

// TEST 6: Shop B requests /remove_drafts/101. -> rejected (404). Product & attributes still exist.
it('rejects Shop B from deleting Shop A draft with 404 (TEST 6)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $shopB = createIsolatedTestShop('shop-b.myshopify.com');
    $schema = createIsolatedTestSchema();

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-A-101',
        'status' => 'draft',
    ]);

    ProductAttribute::create([
        'product_id' => $productA->id,
        'attribute_name' => 'item_name',
        'attribute_value' => 'Shop A Product Attribute',
    ]);

    $response = $this->withSession(withShopAuth($shopB))
        ->get("/remove_drafts/{$productA->id}");

    $response->assertStatus(404);

    expect(AllProduct::find($productA->id))->not->toBeNull();
    expect(ProductAttribute::where('product_id', $productA->id)->count())->toBe(1);
});

// TEST 7: Shop B cannot access Shop A's child product through /child/product/101.
it('rejects Shop B accessing Shop A child product on /child/product/101 with 404 (TEST 7)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $shopB = createIsolatedTestShop('shop-b.myshopify.com');
    $schema = createIsolatedTestSchema();

    $productA = AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-A-CHILD',
        'status' => 'draft',
    ]);

    $response = $this->withSession(withShopAuth($shopB))
        ->get("/child/product/{$productA->id}");

    $response->assertStatus(404);
});

// TEST 8: Shop B's product suggestions do not contain Shop A's accepted product data.
it('ensures product suggestions are tenant-isolated (TEST 8)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $shopB = createIsolatedTestShop('shop-b.myshopify.com');
    $schema = createIsolatedTestSchema();

    AllProduct::create([
        'user_id' => $shopA->id,
        'schema_id' => $schema->id,
        'sku' => 'ACCEPTED-A-SKU',
        'status' => 'ACCEPTED',
        'filled_json' => json_encode([
            'brand' => 'Shop A Secret Brand Suggestion',
        ]),
    ]);

    $response = $this->withSession(withShopAuth($shopB))
        ->get("/addproduct/{$schema->id}");

    $response->assertStatus(200);
    $response->assertDontSee('Shop A Secret Brand Suggestion');

    // Also test AmazonSuccessfulListingService findFor directly
    $service = new AmazonSuccessfulListingService();
    $targetProductB = new AllProduct([
        'user_id' => $shopB->id,
        'schema_id' => $schema->id,
    ]);

    $suggestionForB = $service->findFor($targetProductB);
    expect($suggestionForB)->toBeNull();
});

// TEST 9: Missing Shop context fails closed -> must NOT fall back to first Shop.
it('fails closed when active shop context is missing (TEST 9)', function () {
    $shop1 = createIsolatedTestShop('shop-1.myshopify.com');
    $schema = createIsolatedTestSchema();

    $product1 = AllProduct::create([
        'user_id' => $shop1->id,
        'schema_id' => $schema->id,
        'sku' => 'SKU-SHOP-1-101',
        'status' => 'draft',
    ]);

    // 1. Unauthenticated JSON requests fail closed with 401
    $responseJson = $this->withHeaders(['Accept' => 'application/json'])
        ->get("/productEdit/{$product1->id}");
    $responseJson->assertStatus(401);
    $responseJson->assertDontSee('SKU-SHOP-1-101');

    // 2. Unauthenticated web requests fail closed (401 or redirect away, never loads Shop 1 data)
    $responseWeb = $this->get("/productEdit/{$product1->id}");
    expect($responseWeb->status())->toBeIn([401, 302, 404]);
    $responseWeb->assertDontSee('SKU-SHOP-1-101');

    // 3. Direct controller execution without active shop context aborts with 404
    expect(function () use ($product1) {
        $controller = app(\App\Http\Controllers\ProductSchemaController::class);
        $controller->productEdit($product1->id);
    })->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

// TEST 10: Shop A can still perform all legitimate operations on its own products.
it('allows Shop A to perform legitimate operations on its own products (TEST 10)', function () {
    $shopA = createIsolatedTestShop('shop-a.myshopify.com');
    $schema = createIsolatedTestSchema();

    // 10.1: Create draft
    $responseCreate = $this->withSession(withShopAuth($shopA))
        ->post("/addproduct", [
            'schema_id' => $schema->id,
            'save_draft' => 1,
            'attributes' => [
                'item_name' => 'Shop A Legitimate Product',
                'brand' => 'Shop A Brand',
            ],
        ]);

    $responseCreate->assertRedirect();
    $createdProduct = AllProduct::where('user_id', $shopA->id)->first();
    expect($createdProduct)->not->toBeNull();
    expect($createdProduct->user_id)->toBe($shopA->id);

    // 10.2: Edit draft
    $responseEdit = $this->withSession(withShopAuth($shopA))
        ->get("/productEdit/{$createdProduct->id}");
    $responseEdit->assertStatus(200);
    $responseEdit->assertSee('Shop A Legitimate Product');

    // 10.3: Update draft
    $responseUpdate = $this->withSession(withShopAuth($shopA))
        ->post("/addproduct/{$createdProduct->id}", [
            'schema_id' => $schema->id,
            'save_draft' => 1,
            'attributes' => [
                'item_name' => 'Shop A Updated Title',
            ],
        ]);
    $responseUpdate->assertRedirect();

    $updatedAttr = ProductAttribute::where('product_id', $createdProduct->id)
        ->where('attribute_name', 'item_name')
        ->first();
    expect($updatedAttr->attribute_value)->toBe('Shop A Updated Title');

    // 10.4: Delete draft
    $responseDelete = $this->withSession(withShopAuth($shopA))
        ->get("/remove_drafts/{$createdProduct->id}");
    $responseDelete->assertRedirect();

    expect(AllProduct::find($createdProduct->id))->toBeNull();
    expect(ProductAttribute::where('product_id', $createdProduct->id)->count())->toBe(0);
});
