<?php

use App\Http\Controllers\ProductSchemaController;
use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\AmazonProduct;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key' => 'test_api_key',
        'services.shopify.api_secret' => 'test_api_secret',
        'services.shopify.api_version' => '2026-07',
        'app.disable_subscription' => true,
    ]);

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

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
            $table->string('shopify_connection_status')->nullable();
            $table->string('store_status')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->string('hmac')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('product_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_id')->unique()->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('title')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (!Schema::hasTable('amazon_products')) {
        Schema::create('amazon_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('asin')->nullable();
            $table->timestamps();
        });
    }

    Shop::query()->forceDelete();
    ShopSubscription::query()->delete();
    Product::query()->forceDelete();
    AmazonProduct::query()->delete();
});

function createAddAmazonTestShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'add-amazon-test-store.myshopify.com',
        'shop_name' => 'Add Amazon Test Store',
        'email' => 'add-amazon@example.com',
        'access_token' => 'shpat_test_token_12345',
        'shopify_locations' => [
            ['id' => 99901, 'name' => 'Primary Location', 'active' => true],
        ],
        'selected_location_index' => 0,
        'is_active' => true,
    ], $attributes));

    $plan = Plan::firstOrCreate(
        ['name' => 'Unlimited Plan'],
        ['slug' => 'unlimited-plan-' . uniqid(), 'price' => 0, 'product_limit' => 0, 'is_active' => true]
    );

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'price' => 0,
        'current_period_end' => now()->addYear(),
    ]);

    return $shop;
}

test('Test 1: Standalone Amazon product with Color = Black maps to Color = Black and Size = M', function () {
    $controller = new ProductSchemaController();

    $attributes = [
        'item_name' => [['value' => 'Black Backpack']],
        'color' => [['value' => 'Black']],
        'list_price' => [['value' => '49.99']],
        'main_offer_image_locator' => [['media_location' => 'https://example.com/black-backpack.jpg']],
    ];

    $mapped = $controller->map($attributes, 'SKU-BLACK-001', []);

    expect($mapped['options'])->toHaveCount(2);
    expect($mapped['options'][0]['name'])->toBe('Color');
    expect($mapped['options'][0]['values'])->toBe(['Black']);
    expect($mapped['options'][1]['name'])->toBe('Size');
    expect($mapped['options'][1]['values'])->toBe(['M']);

    expect($mapped['variants'])->toHaveCount(1);
    expect($mapped['variants'][0]['option1'])->toBe('Black');
    expect($mapped['variants'][0]['option2'])->toBe('M');
    expect($mapped['variants'][0]['sku'])->toBe('SKU-BLACK-001');
    expect($mapped['variants'][0]['price'])->toBe('49.99');
    expect($mapped['variants'][0]['image'])->toBe('https://example.com/black-backpack.jpg');
});

test('Test 2: Standalone Amazon product with Color missing/null/empty maps to Color = Default and Size = M', function () {
    $controller = new ProductSchemaController();

    $attributes = [
        'item_name' => [['value' => 'Generic Mug']],
        'list_price' => [['value' => '12.50']],
        'main_offer_image_locator' => [['media_location' => 'https://example.com/mug.jpg']],
    ];

    $mapped = $controller->map($attributes, 'SKU-MUG-001', []);

    expect($mapped['options'])->toHaveCount(2);
    expect($mapped['options'][0]['name'])->toBe('Color');
    expect($mapped['options'][0]['values'])->toBe(['Default']);
    expect($mapped['options'][1]['name'])->toBe('Size');
    expect($mapped['options'][1]['values'])->toBe(['M']);

    expect($mapped['variants'])->toHaveCount(1);
    expect($mapped['variants'][0]['option1'])->toBe('Default');
    expect($mapped['variants'][0]['option2'])->toBe('M');
    expect($mapped['variants'][0]['sku'])->toBe('SKU-MUG-001');
    expect($mapped['variants'][0]['price'])->toBe('12.50');
    expect($mapped['variants'][0]['image'])->toBe('https://example.com/mug.jpg');
});

test('Test 3 & 4: ProductSetInput productOptions contains Color and Size, variants[0].optionValues contains non-null Color and Size', function () {
    $shop = createAddAmazonTestShop();
    $shopifyService = new ShopifyService($shop->shop, $shop->access_token);

    $capturedVariables = null;

    Http::fake([
        'https://add-amazon-test-store.myshopify.com/admin/api/2026-07/graphql.json' => function ($request) use (&$capturedVariables) {
            $data = json_decode($request->body(), true);
            $capturedVariables = $data['variables'] ?? [];

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/888111222',
                            'legacyResourceId' => '888111222',
                            'title' => 'Black Backpack',
                            'handle' => 'black-backpack',
                            'status' => 'ACTIVE',
                            'options' => [
                                ['id' => 'gid://shopify/ProductOption/1', 'name' => 'Color', 'values' => ['Black']],
                                ['id' => 'gid://shopify/ProductOption/2', 'name' => 'Size', 'values' => ['M']],
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/999111',
                                        'legacyResourceId' => '999111',
                                        'title' => 'Black / M',
                                        'price' => '49.99',
                                        'sku' => 'SKU-BLACK-001',
                                        'selectedOptions' => [
                                            ['name' => 'Color', 'value' => 'Black'],
                                            ['name' => 'Size', 'value' => 'M'],
                                        ],
                                    ]
                                ]
                            ],
                        ],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
    ]);

    $payload = [
        'title' => 'Black Backpack',
        'body_html' => '<p>Black Backpack Description</p>',
        'vendor' => 'Test Vendor',
        'product_type' => 'Apparel',
        'status' => 'active',
        'images' => [
            ['src' => 'https://example.com/black-backpack.jpg']
        ],
        'options' => [
            ['name' => 'Color', 'values' => ['Black']],
            ['name' => 'Size', 'values' => ['M']],
        ],
        'variants' => [
            [
                'sku' => 'SKU-BLACK-001',
                'price' => '49.99',
                'option1' => 'Black',
                'option2' => 'M',
                'inventory_quantity' => 10,
            ]
        ]
    ];

    $result = $shopifyService->createProduct($shop, $payload);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($capturedVariables)->not->toBeNull();

    $input = $capturedVariables['input'];
    expect($input['productOptions'])->toBeArray();
    expect($input['productOptions'])->toHaveCount(2);
    expect($input['productOptions'][0]['name'])->toBe('Color');
    expect($input['productOptions'][1]['name'])->toBe('Size');

    expect($input['variants'])->toBeArray();
    expect($input['variants'])->toHaveCount(1);
    $variant0 = $input['variants'][0];

    expect($variant0['optionValues'])->toBeArray();
    expect($variant0['optionValues'])->toHaveCount(2);
    expect($variant0['optionValues'][0]['optionName'])->toBe('Color');
    expect($variant0['optionValues'][0]['name'])->toBe('Black');
    expect($variant0['optionValues'][1]['optionName'])->toBe('Size');
    expect($variant0['optionValues'][1]['name'])->toBe('M');

    // Verify no null values in optionValues
    foreach ($variant0['optionValues'] as $ov) {
        expect($ov['optionName'])->not->toBeNull();
        expect($ov['name'])->not->toBeNull();
    }
});

test('Test 5: First variant file/image equals primary product image', function () {
    $shop = createAddAmazonTestShop();
    $shopifyService = new ShopifyService($shop->shop, $shop->access_token);

    $capturedVariables = null;

    Http::fake([
        'https://add-amazon-test-store.myshopify.com/admin/api/2026-07/graphql.json' => function ($request) use (&$capturedVariables) {
            $data = json_decode($request->body(), true);
            $capturedVariables = $data['variables'] ?? [];

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/888111223',
                            'legacyResourceId' => '888111223',
                            'title' => 'Image Test Product',
                            'status' => 'ACTIVE',
                        ],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
    ]);

    $primaryImg = 'https://example.com/primary-product-image.jpg';

    $payload = [
        'title' => 'Image Test Product',
        'status' => 'active',
        'images' => [
            ['src' => $primaryImg],
            ['src' => 'https://example.com/secondary-product-image.jpg'],
        ],
        'variants' => [
            [
                'sku' => 'IMG-SKU-001',
                'price' => '25.00',
                'option1' => 'Default',
                'option2' => 'M',
                // Notice no explicit 'image' or 'file' provided on variant
            ]
        ]
    ];

    $result = $shopifyService->createProduct($shop, $payload);
    expect($result)->toBeArray();

    $input = $capturedVariables['input'];
    $variant0 = $input['variants'][0];

    // Variant 0 should have file pointing to primaryImg
    expect($variant0['file'])->toBeArray();
    expect($variant0['file']['originalSource'])->toBe($primaryImg);
    expect($variant0['file']['contentType'])->toBe('IMAGE');

    // Files list should have deduplicated primaryImg
    $primaryFilesInInput = array_filter($input['files'], fn($f) => ($f['originalSource'] ?? '') === $primaryImg);
    expect(count($primaryFilesInInput))->toBe(1);
});

test('Test 6 & 7: Product type, SKU, and price mapping remain unchanged', function () {
    $controller = new ProductSchemaController();

    $attributes = [
        'item_name' => [['value' => 'Office Chair Ergonomic']],
        'item_type_keyword' => [['value' => 'Office Furniture']],
        'brand' => [['value' => 'ComfortCorp']],
        'list_price' => [['value' => '199.99']],
        'main_offer_image_locator' => [['media_location' => 'https://example.com/chair.jpg']],
        'color' => [['value' => 'Grey']],
    ];

    $mapped = $controller->map($attributes, 'CHAIR-ERG-999', []);

    // Verify product_type, vendor, status untouched
    expect($mapped['product_type'])->toBe('Office Furniture');
    expect($mapped['vendor'])->toBe('ComfortCorp');
    expect($mapped['status'])->toBe('active');

    // Verify SKU and price untouched
    expect($mapped['sku'])->toBe('CHAIR-ERG-999');
    expect($mapped['price'])->toBe('199.99');
    expect($mapped['variants'][0]['sku'])->toBe('CHAIR-ERG-999');
    expect($mapped['variants'][0]['price'])->toBe('199.99');
});

test('Test 8: Standalone Amazon product ProductSet mutation succeeds without optionValues Expected value to not be null error', function () {
    $shop = createAddAmazonTestShop();
    $shopifyService = new ShopifyService($shop->shop, $shop->access_token);

    $capturedVariables = null;

    Http::fake([
        'https://add-amazon-test-store.myshopify.com/admin/api/2026-07/graphql.json' => function ($request) use (&$capturedVariables) {
            $data = json_decode($request->body(), true);
            $capturedVariables = $data['variables'] ?? [];

            // Verify the mutation payload meets Shopify ProductSetInput requirements
            $input = $capturedVariables['input'] ?? [];
            if (empty($input['variants'])) {
                return Http::response([
                    'errors' => [['message' => 'variants cannot be empty']]
                ], 400);
            }

            foreach ($input['variants'] as $v) {
                if (!isset($v['optionValues']) || !is_array($v['optionValues']) || empty($v['optionValues'])) {
                    return Http::response([
                        'errors' => [['message' => 'Variable $input of type ProductSetInput! was provided invalid value for variants.0.optionValues (Expected value to not be null)']]
                    ], 400);
                }
            }

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/999888',
                            'legacyResourceId' => '999888',
                            'title' => 'Standalone Amazon Product',
                            'status' => 'ACTIVE',
                        ],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
    ]);

    // Simulating standalone Amazon product submission without pre-set options in raw payload
    $payload = [
        'title' => 'Standalone Amazon Product',
        'body_html' => 'Description',
        'vendor' => 'Vendor',
        'status' => 'active',
        'images' => [
            ['src' => 'https://example.com/standalone.jpg']
        ],
        'variants' => [
            [
                'sku' => 'STANDALONE-01',
                'price' => '30.00',
            ]
        ]
    ];

    $result = $shopifyService->createProduct($shop, $payload);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();

    $variant0 = $capturedVariables['input']['variants'][0];
    expect($variant0['optionValues'])->toBeArray();
    expect($variant0['optionValues'][0]['name'])->toBe('Default');
    expect($variant0['optionValues'][1]['name'])->toBe('M');
});

test('Test 9: Existing multi-variant Amazon product behavior remains unchanged and does not get overwritten by Color/Size defaults', function () {
    $controller = new ProductSchemaController();

    $attributes = [
        'item_name' => [['value' => 'Multi-Size T-Shirt']],
        'list_price' => [['value' => '19.99']],
        'color' => [['value' => 'Navy']],
        'style' => [['value' => 'Casual']],
    ];

    $childSkus = ['TSHIRT-S', 'TSHIRT-L'];

    $mapped = $controller->map($attributes, 'TSHIRT-PARENT', $childSkus);

    // Multi-variant should use its own variations / childSkus options
    expect($mapped['variants'])->toHaveCount(2);
    expect($mapped['variants'][0]['sku'])->toBe('TSHIRT-S');
    expect($mapped['variants'][1]['sku'])->toBe('TSHIRT-L');
    expect($mapped['variants'][0]['option1'])->toBe('Navy');
    expect($mapped['variants'][0]['option2'])->toBe('Casual');
});
