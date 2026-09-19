<?php

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\View;

beforeEach(function () {
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_id')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('shopify_status')->nullable();
            $table->text('shopify_error')->nullable();
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->text('tags')->nullable();
            $table->string('category')->nullable();
            $table->text('collections')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('metafields')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->text('local_images')->nullable();
            $table->string('amazon_product_id')->nullable();
            $table->boolean('synced_to_amazon')->default(false);
            $table->boolean('needs_resync')->default(false);
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    Product::query()->forceDelete();
    Shop::query()->forceDelete();
});

function createTestListingShop(array $attributes = []): Shop
{
    return Shop::create(array_merge([
        'shop' => 'listing-test-' . uniqid() . '.myshopify.com',
        'shop_name' => 'Listing Store',
        'email' => 'store@example.com',
        'access_token' => 'shpat_test_' . uniqid(),
        'is_active' => true,
    ], $attributes));
}

function authListingSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

test('Test 1: Single-variant product renders correct image and aggregated inventory on listing page', function () {
    $shop = createTestListingShop([
        'shop' => 'single-var-shop.myshopify.com',
    ]);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 100001,
        'title' => 'Single Variant Hoodie',
        'price' => 49.99,
        'status' => 'active',
        'vendor' => 'CozyApparel',
        'product_type' => 'Apparel',
        'images' => [
            ['id' => 501, 'src' => 'https://cdn.shopify.com/hoodie-main.jpg', 'url' => 'https://cdn.shopify.com/hoodie-main.jpg', 'alt' => 'Main View']
        ],
        'variants' => [
            [
                'id' => 2001,
                'title' => 'Default Title',
                'price' => '49.99',
                'inventory_quantity' => 5,
                'inventory_item_id' => 3001,
            ]
        ],
    ]);

    $response = $this->withSession(authListingSession($shop))
        ->get('/products?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertViewIs('products');
    $content = $response->getContent();

    // Verify Title and Vendor
    expect($content)->toContain('Single Variant Hoodie')
        ->and($content)->toContain('CozyApparel');

    // Verify Thumbnail Image
    expect($content)->toContain('https://cdn.shopify.com/hoodie-main.jpg');

    // Verify Inventory = 5
    expect($content)->toContain('text-success">5</div>');
});

test('Test 2: Multi-variant product sums inventory across all variants on listing page', function () {
    $shop = createTestListingShop([
        'shop' => 'multi-var-shop.myshopify.com',
    ]);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 100002,
        'title' => 'Multi-Variant Shoes',
        'price' => 89.99,
        'status' => 'active',
        'vendor' => 'ShoeBrand',
        'product_type' => 'Footwear',
        'images' => [
            ['id' => 502, 'src' => 'https://cdn.shopify.com/shoes-black.jpg', 'url' => 'https://cdn.shopify.com/shoes-black.jpg']
        ],
        'variants' => [
            [
                'id' => 2002,
                'title' => 'Black / Size 9',
                'price' => '89.99',
                'inventory_quantity' => 5,
            ],
            [
                'id' => 2003,
                'title' => 'Black / Size 10',
                'price' => '89.99',
                'inventory_quantity' => 10,
            ]
        ],
    ]);

    $response = $this->withSession(authListingSession($shop))
        ->get('/products?shop=' . $shop->shop);

    $response->assertStatus(200);
    $content = $response->getContent();

    // Verify Total Inventory: 5 + 10 = 15
    expect($content)->toContain('text-success">15</div>');
});

test('Test 3: Product with multiple images renders the first valid product gallery image', function () {
    $shop = createTestListingShop([
        'shop' => 'gallery-shop.myshopify.com',
    ]);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 100003,
        'title' => 'Camera Lens',
        'price' => 299.00,
        'status' => 'active',
        'images' => [
            ['id' => 601, 'src' => 'https://cdn.shopify.com/lens-angle1.jpg', 'url' => 'https://cdn.shopify.com/lens-angle1.jpg'],
            ['id' => 602, 'src' => 'https://cdn.shopify.com/lens-angle2.jpg', 'url' => 'https://cdn.shopify.com/lens-angle2.jpg'],
            ['id' => 603, 'src' => 'https://cdn.shopify.com/lens-angle3.jpg', 'url' => 'https://cdn.shopify.com/lens-angle3.jpg'],
        ],
        'variants' => [
            [
                'id' => 2004,
                'title' => 'Standard',
                'price' => '299.00',
                'inventory_quantity' => 8,
            ]
        ],
    ]);

    $response = $this->withSession(authListingSession($shop))
        ->get('/products?shop=' . $shop->shop);

    $response->assertStatus(200);
    $content = $response->getContent();

    // Verify Thumbnail is the first valid gallery image
    expect($content)->toContain('src="https://cdn.shopify.com/lens-angle1.jpg"');
});

test('Test 4: Product with string array images and variant fallback images displays correctly', function () {
    $shop = createTestListingShop([
        'shop' => 'string-img-shop.myshopify.com',
    ]);

    // Product with plain string URLs in images array
    $product1 = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 100004,
        'title' => 'String URL Watch',
        'price' => 150.00,
        'status' => 'active',
        'images' => ['https://cdn.shopify.com/watch-string-url.jpg'],
        'variants' => [
            ['id' => 2005, 'inventory_quantity' => 12, 'price' => '150.00']
        ],
    ]);

    // Product with empty images array but variant image_src
    $product2 = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 100005,
        'title' => 'Variant Image Ring',
        'price' => 75.00,
        'status' => 'active',
        'images' => [],
        'variants' => [
            ['id' => 2006, 'image_src' => 'https://cdn.shopify.com/ring-variant.jpg', 'inventory_quantity' => 4, 'price' => '75.00']
        ],
    ]);

    $response = $this->withSession(authListingSession($shop))
        ->get('/products?shop=' . $shop->shop);

    $response->assertStatus(200);
    $content = $response->getContent();

    expect($content)->toContain('https://cdn.shopify.com/watch-string-url.jpg')
        ->and($content)->toContain('text-success">12</div>')
        ->and($content)->toContain('https://cdn.shopify.com/ring-variant.jpg')
        ->and($content)->toContain('text-success">4</div>');
});

test('Test 5: Out of stock product reflects 0 inventory with danger styling on listing page', function () {
    $shop = createTestListingShop([
        'shop' => 'oos-shop.myshopify.com',
    ]);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => 100006,
        'title' => 'Sold Out Backpack',
        'price' => 59.99,
        'status' => 'active',
        'images' => [
            ['id' => 701, 'src' => 'https://cdn.shopify.com/backpack.jpg']
        ],
        'variants' => [
            [
                'id' => 2007,
                'title' => 'Default',
                'price' => '59.99',
                'inventory_quantity' => 0,
            ]
        ],
    ]);

    $response = $this->withSession(authListingSession($shop))
        ->get('/products?shop=' . $shop->shop);

    $response->assertStatus(200);
    $content = $response->getContent();

    expect($content)->toContain('Sold Out Backpack')
        ->and($content)->toContain('text-danger">0</div>')
        ->and($content)->toContain('val-danger">');
});
