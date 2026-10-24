<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CategorySearchDropdownTest extends TestCase
{
    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shopify.api_key' => 'test-api-key',
            'services.shopify.api_secret' => 'test-api-secret',
            'app.disable_subscription' => true,
        ]);

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
                $table->string('amazon_marketplace_id')->nullable();
                $table->json('shopify_locations')->nullable();
                $table->integer('selected_location_index')->nullable();
                $table->boolean('is_active')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category')->nullable();
                $table->string('slug')->nullable();
                $table->json('marketplaceIds')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->integer('level')->default(1);
                $table->string('status')->default('active');
                $table->boolean('self_added')->default(false);
                $table->timestamps();
            });
        }

        Category::truncate();
        Shop::truncate();

        $this->shop = Shop::create([
            'shop' => 'test-store.myshopify.com',
            'shop_name' => 'Test Store',
            'email' => 'test@example.com',
            'access_token' => 'shpat_test_token',
            'is_active' => true,
        ]);
    }

    protected function authSession(): array
    {
        return [
            '_shopify_verified_shop' => $this->shop->shop,
            '_shopify_verified_at' => time(),
            'active_shop' => $this->shop->shop,
            'active_shop_id' => $this->shop->id,
        ];
    }

    public function test_search_returns_all_subcategories_when_search_is_empty_for_parent_category(): void
    {
        $parent = Category::create([
            'name' => 'Clothing & Accessories',
            'category' => 'clothing',
            'slug' => 'clothing-accessories',
            'parent_id' => null,
            'level' => 1
        ]);

        Category::create([
            'name' => 'T-Shirts',
            'category' => 't-shirts',
            'slug' => 't-shirts',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        Category::create([
            'name' => 'Casual Shirts',
            'category' => 'casual-shirts',
            'slug' => 'casual-shirts',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        Category::create([
            'name' => 'Jeans',
            'category' => 'jeans',
            'slug' => 'jeans',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        // Unrelated subcategory in another parent
        $otherParent = Category::create([
            'name' => 'Electronics',
            'category' => 'electronics',
            'slug' => 'electronics',
            'parent_id' => null,
            'level' => 1
        ]);
        Category::create([
            'name' => 'Smartphones',
            'category' => 'smartphones',
            'slug' => 'smartphones',
            'parent_id' => $otherParent->id,
            'level' => 2
        ]);

        $response = $this->withSession($this->authSession())->getJson(route('shopify.categories.search', [
            'parent_id' => $parent->id,
            'search' => '',
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(3, $data);
        $names = array_column($data, 'name');
        $this->assertContains('Casual Shirts', $names);
        $this->assertContains('Jeans', $names);
        $this->assertContains('T-Shirts', $names);
        $this->assertNotContains('Smartphones', $names);
    }

    public function test_search_filters_subcategories_case_insensitively(): void
    {
        $parent = Category::create([
            'name' => 'Apparel',
            'category' => 'apparel',
            'slug' => 'apparel',
            'parent_id' => null,
            'level' => 1
        ]);

        Category::create([
            'name' => 'Men Shirt',
            'category' => 'men-shirt',
            'slug' => 'men-shirt',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        Category::create([
            'name' => 'Women T-SHIRT',
            'category' => 'women-t-shirt',
            'slug' => 'women-t-shirt',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        Category::create([
            'name' => 'Footwear',
            'category' => 'footwear',
            'slug' => 'footwear',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        $response = $this->withSession($this->authSession())->getJson(route('shopify.categories.search', [
            'parent_id' => $parent->id,
            'search' => 'shirt',
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data);
        $names = array_column($data, 'name');
        $this->assertContains('Men Shirt', $names);
        $this->assertContains('Women T-SHIRT', $names);
        $this->assertNotContains('Footwear', $names);
    }

    public function test_search_returns_empty_array_when_no_match(): void
    {
        $parent = Category::create([
            'name' => 'Furniture',
            'category' => 'furniture',
            'slug' => 'furniture',
            'parent_id' => null,
            'level' => 1
        ]);

        Category::create([
            'name' => 'Chairs',
            'category' => 'chairs',
            'slug' => 'chairs',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        $response = $this->withSession($this->authSession())->getJson(route('shopify.categories.search', [
            'parent_id' => $parent->id,
            'search' => 'nonexistentquery123',
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEmpty($data);
    }

    public function test_create_product_view_renders_searchable_dropdown_elements(): void
    {
        View::share('cspNonce', 'test-csp-nonce-create');
        View::share('errors', new ViewErrorBag());

        $html = View::make('createProduct', [
            'activeShop' => $this->shop->shop,
            'currency' => 'INR',
        ])->render();

        $this->assertStringContainsString('id="sub_category_wrapper"', $html);
        $this->assertStringContainsString('id="sub_category_search"', $html);
        $this->assertStringContainsString('id="sub_category"', $html);
        $this->assertStringContainsString('id="sub_category_results"', $html);
        $this->assertStringContainsString('class="subcategory-dropdown"', $html);
        $this->assertStringContainsString('openSubCategoryDropdown', $html);
        $this->assertStringContainsString('closeSubCategoryDropdown', $html);
        $this->assertStringContainsString('ArrowDown', $html);
        $this->assertStringContainsString('ArrowUp', $html);
        $this->assertStringContainsString('Escape', $html);
        $this->assertStringContainsString('No subcategories found', $html);
        // Authenticated fetch migration verification
        $this->assertStringContainsString('fetch(url,', $html);
        $this->assertStringNotContainsString('new XMLHttpRequest', $html);
    }

    public function test_edit_product_view_renders_searchable_dropdown_and_prefills_existing_subcategory(): void
    {
        View::share('cspNonce', 'test-csp-nonce-edit');
        View::share('errors', new ViewErrorBag());

        $parent = Category::create([
            'name' => 'Fashion',
            'category' => 'fashion',
            'slug' => 'fashion',
            'parent_id' => null,
            'level' => 1
        ]);

        $sub = Category::create([
            'name' => 'Graphic Tees',
            'category' => 'graphic-tees',
            'slug' => 'graphic-tees',
            'parent_id' => $parent->id,
            'level' => 2
        ]);

        $productData = [
            'id' => '123456789',
            'title' => 'Sample Cool Tee',
            'body_html' => '<p>Cotton tee</p>',
            'status' => 'active',
            'variants' => [
                ['id' => '987654', 'price' => '29.99', 'sku' => 'TEE-001', 'inventory_quantity' => 10]
            ],
            'options' => [
                ['name' => 'Size', 'values' => ['M', 'L']]
            ],
            'images' => []
        ];

        $dbProduct = [
            'id' => 501,
            'category_id' => $parent->id,
            'sub_category_id' => $sub->id,
            'title' => 'Sample Cool Tee',
            'description' => 'Cotton tee',
        ];

        $html = View::make('EditProduct', [
            'product' => $productData,
            'activeShop' => $this->shop->shop,
            'amazonData' => null,
            'dbProduct' => $dbProduct,
        ])->render();

        $this->assertStringContainsString('id="sub_category_wrapper"', $html);
        $this->assertStringContainsString('id="sub_category_search"', $html);
        $this->assertStringContainsString('id="sub_category"', $html);
        $this->assertStringContainsString('id="sub_category_results"', $html);
        $this->assertStringContainsString('class="subcategory-dropdown"', $html);
        // Prefilled values
        $this->assertStringContainsString('value="Graphic Tees"', $html);
        $this->assertStringContainsString('value="' . $sub->id . '"', $html);
        // Keyboard & autocomplete handlers
        $this->assertStringContainsString('openSubCategoryDropdown', $html);
        $this->assertStringContainsString('closeSubCategoryDropdown', $html);
        $this->assertStringContainsString('ArrowDown', $html);
        $this->assertStringContainsString('ArrowUp', $html);
        $this->assertStringContainsString('Escape', $html);
        $this->assertStringContainsString('No subcategories found', $html);
        // Authenticated fetch migration verification
        $this->assertStringContainsString('fetch(url,', $html);
        $this->assertStringNotContainsString('new XMLHttpRequest', $html);
    }

    public function test_amazon_products_index_view_renders_empty_state_correctly(): void
    {
        View::share('cspNonce', 'test-csp-nonce-products');
        View::share('errors', new ViewErrorBag());

        $html = View::make('schema.products.index', [
            'products' => [],
            'parent_productid' => null,
            'productLimitReached' => false,
        ])->render();

        $this->assertStringContainsString('No Products Found', $html);
        $this->assertStringContainsString('Products added through ZeoSync will appear here.', $html);
        $this->assertStringContainsString('bi bi-box-seam', $html);
        $this->assertStringNotContainsString('No products has been aded by our plateform.', $html);
    }

    public function test_category_search_rejects_unauthenticated_request_with_401_json(): void
    {
        $parent = Category::create([
            'name' => 'Fashion',
            'category' => 'fashion',
            'slug' => 'fashion',
            'parent_id' => null,
            'level' => 1,
        ]);

        $response = $this->get(route('shopify.categories.search', [
            'parent_id' => $parent->id,
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'requires_reauth' => true,
            'error' => 'Unauthorized',
        ]);
        $response->assertHeader('X-Shopify-Retry-Invalid-Session-Request', '1');
    }

    public function test_category_search_succeeds_with_bearer_token(): void
    {
        $parent = Category::create([
            'name' => 'Home & Kitchen',
            'category' => 'home-kitchen',
            'slug' => 'home-kitchen',
            'parent_id' => null,
            'level' => 1,
        ]);

        Category::create([
            'name' => 'Cookware',
            'category' => 'cookware',
            'slug' => 'cookware',
            'parent_id' => $parent->id,
            'level' => 2,
        ]);

        $validator = \Mockery::mock(\App\Services\ShopifySessionTokenValidator::class)->makePartial();
        $validator->shouldReceive('validate')->andReturn([
            'shop' => $this->shop->shop,
            'shop_model' => $this->shop,
            'payload' => ['dest' => "https://{$this->shop->shop}"],
        ]);
        app()->instance(\App\Services\ShopifySessionTokenValidator::class, $validator);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer mock-valid-app-bridge-token',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get(route('shopify.categories.search', [
            'parent_id' => $parent->id,
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('Cookware', $data[0]['name']);
    }

    public function test_category_search_without_parent_returns_main_categories_when_search_is_empty(): void
    {
        Category::create([
            'name' => 'Electronics',
            'category' => 'electronics',
            'slug' => 'electronics',
            'parent_id' => null,
            'level' => 1,
        ]);

        $clothing = Category::create([
            'name' => 'Clothing',
            'category' => 'clothing',
            'slug' => 'clothing',
            'parent_id' => null,
            'level' => 1,
        ]);

        Category::create([
            'name' => 'Shirts',
            'category' => 'shirts',
            'slug' => 'shirts',
            'parent_id' => $clothing->id,
            'level' => 2,
        ]);

        $response = $this->withSession($this->authSession())->getJson(route('shopify.categories.search', [
            'search' => '',
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        // Should return main categories only
        $this->assertCount(2, $data);
        $names = array_column($data, 'name');
        $this->assertContains('Clothing', $names);
        $this->assertContains('Electronics', $names);
        $this->assertNotContains('Shirts', $names);
    }

    public function test_category_search_returns_matching_main_categories_and_subcategories_with_parent_info(): void
    {
        $clothing = Category::create([
            'name' => 'Clothing Tops',
            'category' => 'clothing-tops',
            'slug' => 'clothing-tops',
            'parent_id' => null,
            'level' => 1,
        ]);

        $baby = Category::create([
            'name' => "Baby & Children's Tops",
            'category' => 'baby-tops',
            'slug' => 'baby-tops',
            'parent_id' => null,
            'level' => 1,
        ]);

        Category::create([
            'name' => 'Shirts',
            'category' => 'shirts-clothing',
            'slug' => 'shirts-clothing',
            'parent_id' => $clothing->id,
            'level' => 2,
        ]);

        Category::create([
            'name' => 'Shirts',
            'category' => 'shirts-baby',
            'slug' => 'shirts-baby',
            'parent_id' => $baby->id,
            'level' => 2,
        ]);

        Category::create([
            'name' => 'T-Shirts',
            'category' => 't-shirts-clothing',
            'slug' => 't-shirts-clothing',
            'parent_id' => $clothing->id,
            'level' => 2,
        ]);

        $response = $this->withSession($this->authSession())->getJson(route('shopify.categories.search', [
            'search' => 'shirt',
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(3, $data);

        // Check subcategory results contain valid parent_id, parent_name, and is_subcategory = true
        foreach ($data as $item) {
            $this->assertTrue($item['is_subcategory']);
            $this->assertNotNull($item['parent_id']);
            $this->assertNotNull($item['parent_name']);
        }

        $parentNames = array_column($data, 'parent_name');
        $this->assertContains('Clothing Tops', $parentNames);
        $this->assertContains("Baby & Children's Tops", $parentNames);
    }

    public function test_category_search_finds_subcategories_when_searching_by_parent_name(): void
    {
        $parent = Category::create([
            'name' => 'Activewear Tops',
            'category' => 'activewear-tops',
            'slug' => 'activewear-tops',
            'parent_id' => null,
            'level' => 1,
        ]);

        Category::create([
            'name' => 'Gym Tanks',
            'category' => 'gym-tanks',
            'slug' => 'gym-tanks',
            'parent_id' => $parent->id,
            'level' => 2,
        ]);

        $response = $this->withSession($this->authSession())->getJson(route('shopify.categories.search', [
            'search' => 'activewear',
            'shop' => $this->shop->shop,
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $names = array_column($data, 'name');
        $this->assertContains('Activewear Tops', $names);
        $this->assertContains('Gym Tanks', $names);

        $gymTank = collect($data)->firstWhere('name', 'Gym Tanks');
        $this->assertNotNull($gymTank);
        $this->assertTrue($gymTank['is_subcategory']);
        $this->assertEquals($parent->id, $gymTank['parent_id']);
        $this->assertEquals('Activewear Tops', $gymTank['parent_name']);
    }

    public function test_create_product_view_renders_category_search_dropdown_elements(): void
    {
        View::share('cspNonce', 'test-csp-nonce-create-cat');
        View::share('errors', new ViewErrorBag());

        $html = View::make('createProduct', [
            'activeShop' => $this->shop->shop,
            'currency' => 'INR',
        ])->render();

        $this->assertStringContainsString('id="category_wrapper"', $html);
        $this->assertStringContainsString('id="category_search"', $html);
        $this->assertStringContainsString('id="category"', $html);
        $this->assertStringContainsString('id="category_results"', $html);
        $this->assertStringContainsString('category-dropdown', $html);
        $this->assertStringContainsString('openCategoryDropdown', $html);
        $this->assertStringContainsString('closeCategoryDropdown', $html);
        $this->assertStringContainsString('selectCategoryItem', $html);
    }

    public function test_edit_product_view_renders_category_search_dropdown_and_prefills_both(): void
    {
        View::share('cspNonce', 'test-csp-nonce-edit-cat');
        View::share('errors', new ViewErrorBag());

        $parent = Category::create([
            'name' => 'Men Fashion',
            'category' => 'men-fashion',
            'slug' => 'men-fashion',
            'parent_id' => null,
            'level' => 1,
        ]);

        $sub = Category::create([
            'name' => 'Hoodies',
            'category' => 'hoodies',
            'slug' => 'hoodies',
            'parent_id' => $parent->id,
            'level' => 2,
        ]);

        $productData = [
            'id' => '99887766',
            'title' => 'Comfort Hoodie',
            'body_html' => '<p>Fleece hoodie</p>',
            'status' => 'active',
            'variants' => [],
            'options' => [],
            'images' => [],
        ];

        $dbProduct = [
            'id' => 901,
            'category_id' => $parent->id,
            'sub_category_id' => $sub->id,
            'title' => 'Comfort Hoodie',
        ];

        $html = View::make('EditProduct', [
            'product' => $productData,
            'activeShop' => $this->shop->shop,
            'amazonData' => null,
            'dbProduct' => $dbProduct,
        ])->render();

        $this->assertStringContainsString('id="category_wrapper"', $html);
        $this->assertStringContainsString('id="category_search"', $html);
        $this->assertStringContainsString('value="Men Fashion"', $html);
        $this->assertStringContainsString('value="' . $parent->id . '"', $html);

        $this->assertStringContainsString('id="sub_category_wrapper"', $html);
        $this->assertStringContainsString('id="sub_category_search"', $html);
        $this->assertStringContainsString('value="Hoodies"', $html);
        $this->assertStringContainsString('value="' . $sub->id . '"', $html);

        $this->assertStringContainsString('category-dropdown', $html);
        $this->assertStringContainsString('openCategoryDropdown', $html);
        $this->assertStringContainsString('closeCategoryDropdown', $html);
        $this->assertStringContainsString('selectCategoryItem', $html);
    }
}


