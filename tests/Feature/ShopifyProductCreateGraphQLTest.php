<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\AmazonProduct;
use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key' => 'test_api_key',
        'services.shopify.api_secret' => 'test_api_secret',
        'services.shopify.api_version' => '2026-07',
        'app.disable_subscription' => false,
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

    Shop::query()->forceDelete();
    ShopSubscription::query()->forceDelete();
    Product::query()->forceDelete();
    AmazonProduct::query()->forceDelete();
});

function createCreateTestShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'create-test-store.myshopify.com',
        'shop_name' => 'Create Test Store',
        'email' => 'create@example.com',
        'access_token' => 'shpat_create_token_' . uniqid(),
        'shopify_locations' => [
            ['id' => 88801, 'name' => 'Main Warehouse', 'active' => true],
            ['id' => 88802, 'name' => 'East Coast Hub', 'active' => true],
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

function authCreateSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at' => time(),
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ];
}

function mockShopifyProductSetResponse(array $productOverrides = [], array $userErrors = []): array
{
    $defaultProduct = [
        'id' => 'gid://shopify/Product/990011',
        'legacyResourceId' => '990011',
        'title' => 'Sample Running Shoes',
        'handle' => 'sample-running-shoes',
        'descriptionHtml' => '<p>High performance running shoes</p>',
        'vendor' => 'SpeedyBrand',
        'productType' => 'Footwear',
        'status' => 'ACTIVE',
        'tags' => ['shoes', 'running', 'sport'],
        'createdAt' => '2026-03-01T10:00:00Z',
        'updatedAt' => '2026-03-01T10:00:00Z',
        'options' => [
            [
                'id' => 'gid://shopify/ProductOption/1001',
                'name' => 'Title',
                'position' => 1,
                'values' => ['Default Title'],
            ],
        ],
        'images' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductImage/2001',
                    'url' => 'https://example.com/shoes.jpg',
                    'altText' => 'Running shoes front view',
                    'width' => 800,
                    'height' => 800,
                ],
            ],
        ],
        'variants' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductVariant/3001',
                    'legacyResourceId' => '3001',
                    'title' => 'Default Title',
                    'sku' => 'SHOES-RUN-01',
                    'barcode' => '123456789012',
                    'price' => '89.99',
                    'compareAtPrice' => '119.99',
                    'position' => 1,
                    'selectedOptions' => [
                        ['name' => 'Title', 'value' => 'Default Title'],
                    ],
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/4001',
                        'legacyResourceId' => '4001',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/88801',
                                        'legacyResourceId' => '88801',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 25],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    return [
        'data' => [
            'productSet' => [
                'product' => empty($productOverrides) && empty($userErrors) ? $defaultProduct : (empty($userErrors) ? array_merge($defaultProduct, $productOverrides) : null),
                'userErrors' => $userErrors,
            ],
        ],
    ];
}

it('Test 1: creates a basic product via GraphQL productSet mutation and persists to local DB', function () {
    $shop = createCreateTestShop();

    $graphqlSent = false;
    $graphqlInput = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$graphqlSent, &$graphqlInput) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productSet')) {
                $graphqlSent = true;
                $graphqlInput = $body['variables']['input'] ?? [];
                return Http::response(mockShopifyProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Sample Running Shoes',
        'description' => '<p>High performance running shoes</p>',
        'vendor' => 'SpeedyBrand',
        'product_type' => 'Footwear',
        'status' => 'active',
        'tags' => 'shoes, running, sport',
        'price' => '89.99',
        'sku' => 'SHOES-RUN-01',
        'barcode' => '123456789012',
        'qty' => 25,
        'existing_images' => ['https://example.com/shoes.jpg'],
        'variants' => [
            [
                'price' => '89.99',
                'sku' => 'SHOES-RUN-01',
                'barcode' => '123456789012',
                'qty' => 25,
                'compare_at_price' => '119.99',
            ],
        ],
    ];

    $response = $this->withSession(authCreateSession($shop))
        ->post('/createProduct', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);
    $response->assertSessionHas('success', 'Product created successfully!');

    expect($graphqlSent)->toBeTrue();
    expect($graphqlInput['title'])->toBe('Sample Running Shoes');
    expect($graphqlInput['vendor'])->toBe('SpeedyBrand');
    expect($graphqlInput['productType'])->toBe('Footwear');
    expect($graphqlInput['status'])->toBe('ACTIVE');
    expect($graphqlInput['tags'])->toBe(['shoes', 'running', 'sport']);
    expect($graphqlInput['variants'][0]['price'])->toBe('89.99');
    expect($graphqlInput['variants'][0]['sku'])->toBe('SHOES-RUN-01');
    expect($graphqlInput['variants'][0]['inventoryQuantities'][0]['locationId'])->toBe('gid://shopify/Location/88801');
    expect($graphqlInput['variants'][0]['inventoryQuantities'][0]['quantity'])->toBe(25);

    // Verify local DB persistence
    $dbProduct = Product::where('shopify_id', '990011')
        ->where('shop_id', $shop->id)
        ->first();

    expect($dbProduct)->not->toBeNull();
    expect($dbProduct->title)->toBe('Sample Running Shoes');
    expect((float)$dbProduct->price)->toEqual(89.99);

    $variants = json_decode($dbProduct->variants, true);
    expect($variants)->toHaveCount(1);
    expect($variants[0]['id'])->toBe(3001);
    expect($variants[0]['sku'])->toBe('SHOES-RUN-01');
    expect($variants[0]['inventory_quantity'])->toBe(25);

    // Verify AmazonProduct persistence
    $amazonProduct = AmazonProduct::where('product_id', $dbProduct->id)->first();
    expect($amazonProduct)->not->toBeNull();
});

it('Test 2: creates multi-variant product with custom options via GraphQL productSet', function () {
    $shop = createCreateTestShop();

    $multiVariantGraphQLResponse = [
        'id' => 'gid://shopify/Product/990022',
        'legacyResourceId' => '990022',
        'title' => 'Classic Cotton T-Shirt',
        'handle' => 'classic-cotton-t-shirt',
        'descriptionHtml' => '<p>100% pure organic cotton</p>',
        'vendor' => 'EcoApparel',
        'productType' => 'Shirts',
        'status' => 'ACTIVE',
        'tags' => ['apparel', 'cotton', 'summer'],
        'createdAt' => '2026-03-01T10:00:00Z',
        'updatedAt' => '2026-03-01T10:00:00Z',
        'options' => [
            [
                'id' => 'gid://shopify/ProductOption/201',
                'name' => 'Size',
                'position' => 1,
                'values' => ['Small', 'Medium'],
            ],
            [
                'id' => 'gid://shopify/ProductOption/202',
                'name' => 'Color',
                'position' => 2,
                'values' => ['Red', 'Blue'],
            ],
        ],
        'images' => ['nodes' => []],
        'variants' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductVariant/5001',
                    'legacyResourceId' => '5001',
                    'title' => 'Small / Red',
                    'sku' => 'TSHIRT-SM-RED',
                    'price' => '24.99',
                    'compareAtPrice' => '29.99',
                    'position' => 1,
                    'selectedOptions' => [
                        ['name' => 'Size', 'value' => 'Small'],
                        ['name' => 'Color', 'value' => 'Red'],
                    ],
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/6001',
                        'legacyResourceId' => '6001',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/88801',
                                        'legacyResourceId' => '88801',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 10],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'gid://shopify/ProductVariant/5002',
                    'legacyResourceId' => '5002',
                    'title' => 'Medium / Blue',
                    'sku' => 'TSHIRT-MED-BLU',
                    'price' => '27.99',
                    'compareAtPrice' => '32.99',
                    'position' => 2,
                    'selectedOptions' => [
                        ['name' => 'Size', 'value' => 'Medium'],
                        ['name' => 'Color', 'value' => 'Blue'],
                    ],
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/6002',
                        'legacyResourceId' => '6002',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => [
                                        'id' => 'gid://shopify/Location/88801',
                                        'legacyResourceId' => '88801',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 15],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $capturedVariables = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$capturedVariables, $multiVariantGraphQLResponse) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productSet')) {
                $capturedVariables = $body['variables'] ?? [];
                return Http::response(mockShopifyProductSetResponse($multiVariantGraphQLResponse), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Classic Cotton T-Shirt',
        'description' => '<p>100% pure organic cotton</p>',
        'vendor' => 'EcoApparel',
        'product_type' => 'Shirts',
        'status' => 'active',
        'tags' => 'apparel, cotton, summer',
        'options' => [
            ['name' => 'Size', 'values' => ['Small', 'Medium']],
            ['name' => 'Color', 'values' => ['Red', 'Blue']],
        ],
        'variants' => [
            [
                'option1' => 'Small',
                'option2' => 'Red',
                'price' => '24.99',
                'compare_at_price' => '29.99',
                'sku' => 'TSHIRT-SM-RED',
                'qty' => 10,
            ],
            [
                'option1' => 'Medium',
                'option2' => 'Blue',
                'price' => '27.99',
                'compare_at_price' => '32.99',
                'sku' => 'TSHIRT-MED-BLU',
                'qty' => 15,
            ],
        ],
    ];

    $response = $this->withSession(authCreateSession($shop))
        ->post('/createProduct', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);
    $response->assertSessionHas('success', 'Product created successfully!');

    expect($capturedVariables['synchronous'])->toBeTrue();
    $input = $capturedVariables['input'];
    expect($input['productOptions'])->toHaveCount(2);
    expect($input['productOptions'][0]['name'])->toBe('Size');
    expect($input['productOptions'][0]['values'])->toBe([['name' => 'Small'], ['name' => 'Medium']]);
    expect($input['variants'])->toHaveCount(2);
    expect($input['variants'][0]['optionValues'])->toBe([
        ['optionName' => 'Size', 'name' => 'Small'],
        ['optionName' => 'Color', 'name' => 'Red'],
    ]);
    expect($input['variants'][1]['optionValues'])->toBe([
        ['optionName' => 'Size', 'name' => 'Medium'],
        ['optionName' => 'Color', 'name' => 'Blue'],
    ]);

    $dbProduct = Product::where('shopify_id', '990022')->first();
    expect($dbProduct)->not->toBeNull();
    $variants = json_decode($dbProduct->variants, true);
    expect($variants)->toHaveCount(2);
    expect($variants[0]['id'])->toBe(5001);
    expect($variants[0]['option1'])->toBe('Small');
    expect($variants[0]['option2'])->toBe('Red');
    expect($variants[1]['id'])->toBe(5002);
    expect($variants[1]['option1'])->toBe('Medium');
    expect($variants[1]['option2'])->toBe('Blue');
});

it('Test 3: handles Shopify GraphQL userErrors gracefully with rollback/redirect', function () {
    $shop = createCreateTestShop();

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productSet')) {
                return Http::response(mockShopifyProductSetResponse([], [
                    [
                        'field' => ['input', 'title'],
                        'message' => 'Title cannot be blank.',
                        'code' => 'BLANK',
                    ],
                ]), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Invalid Product',
        'price' => '10.00',
    ];

    $response = $this->withSession(authCreateSession($shop))
        ->post('/createProduct', $postData);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Title cannot be blank.');

    // Ensure no product was created in local DB
    expect(Product::count())->toBe(0);
});

it('Test 4: handles Shopify GraphQL top-level errors without corrupting DB', function () {
    $shop = createCreateTestShop();

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            return Http::response([
                'errors' => [
                    ['message' => 'Throttled by Shopify API'],
                ],
            ], 200);
        },
    ]);

    $postData = [
        'title' => 'Throttled Product',
        'price' => '19.99',
    ];

    $response = $this->withSession(authCreateSession($shop))
        ->post('/createProduct', $postData);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Throttled by Shopify API');

    expect(Product::count())->toBe(0);
});

it('Test 5: handles HTTP/network failure during product creation', function () {
    $shop = createCreateTestShop();

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            return Http::response('Server Error', 500);
        },
    ]);

    $postData = [
        'title' => 'Failed Product',
        'price' => '29.99',
    ];

    $response = $this->withSession(authCreateSession($shop))
        ->post('/createProduct', $postData);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    expect(Product::count())->toBe(0);
});

it('Test 6: enforces active subscription check via CheckSubscription middleware on createProduct view', function () {
    $shop = createCreateTestShop();

    // Mark subscription as cancelled
    ShopSubscription::query()->where('shop_id', $shop->id)->update([
        'status' => 'cancelled',
    ]);

    $response = $this->withSession(authCreateSession($shop))
        ->get('/createProduct?shop=' . $shop->shop);

    $response->assertRedirect(route('plans.index', ['shop' => $shop->shop]));
    $response->assertSessionHas('error', 'Please activate a subscription plan.');
});

it('Test 7: preserves shop/tenant isolation on product creation', function () {
    $shopA = createCreateTestShop(['shop' => 'shop-a.myshopify.com']);
    $shopB = createCreateTestShop(['shop' => 'shop-b.myshopify.com']);

    Http::fake([
        "https://{$shopA->shop}/admin/api/2026-07/graphql.json" => function () {
            return Http::response(mockShopifyProductSetResponse([
                'id' => 'gid://shopify/Product/777001',
                'legacyResourceId' => '777001',
                'title' => 'Shop A Product',
            ]), 200);
        },
    ]);

    $postData = [
        'title' => 'Shop A Product',
        'price' => '49.99',
    ];

    $response = $this->withSession(authCreateSession($shopA))
        ->post('/createProduct', $postData);

    $response->assertRedirect('/products?shop=' . $shopA->shop);

    // Product should only belong to Shop A
    $productA = Product::where('shopify_id', '777001')->first();
    expect($productA)->not->toBeNull();
    expect($productA->shop_id)->toBe($shopA->id);

    $productB = Product::where('shopify_id', '777001')->where('shop_id', $shopB->id)->first();
    expect($productB)->toBeNull();
});

it('Test 8: attaches image URLs as GraphQL files input', function () {
    $shop = createCreateTestShop();

    $capturedFiles = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$capturedFiles) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productSet')) {
                $capturedFiles = $body['variables']['input']['files'] ?? [];
                return Http::response(mockShopifyProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Product With Images',
        'price' => '30.00',
        'existing_images' => [
            'https://cdn.example.com/image1.jpg',
            'https://cdn.example.com/image2.png',
        ],
    ];

    $response = $this->withSession(authCreateSession($shop))
        ->post('/createProduct', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);

    expect($capturedFiles)->toHaveCount(2);
    expect($capturedFiles[0])->toBe([
        'originalSource' => 'https://cdn.example.com/image1.jpg',
        'contentType' => 'IMAGE',
    ]);
    expect($capturedFiles[1])->toBe([
        'originalSource' => 'https://cdn.example.com/image2.png',
        'contentType' => 'IMAGE',
    ]);
});

it('Test 9: syncs product metafields via GraphQL metafieldsSet mutation', function () {
    $shop = createCreateTestShop();

    $metafieldsSetCalled = false;
    $capturedMetafields = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$metafieldsSetCalled, &$capturedMetafields) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'productSet')) {
                return Http::response(mockShopifyProductSetResponse([
                    'id' => 'gid://shopify/Product/990033',
                    'legacyResourceId' => '990033',
                ]), 200);
            }

            if (str_contains($query, 'metafieldsSet')) {
                $metafieldsSetCalled = true;
                $capturedMetafields = $body['variables']['metafields'] ?? [];
                return Http::response([
                    'data' => [
                        'metafieldsSet' => [
                            'metafields' => [
                                ['id' => 'gid://shopify/Metafield/11', 'namespace' => 'custom', 'key' => 'material', 'value' => 'Cotton'],
                            ],
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Product With Metafields',
        'price' => '45.00',
        'meta_name' => ['Material', 'Care Instructions'],
        'meta_value' => ['Cotton', 'Machine wash cold'],
    ];

    $response = $this->withSession(authCreateSession($shop))
        ->post('/createProduct', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);

    expect($metafieldsSetCalled)->toBeTrue();
    expect($capturedMetafields)->toHaveCount(2);
    expect($capturedMetafields[0]['ownerId'])->toBe('gid://shopify/Product/990033');
    expect($capturedMetafields[0]['key'])->toBe('material');
    expect($capturedMetafields[0]['value'])->toBe('Cotton');
    expect($capturedMetafields[1]['key'])->toBe('care_instructions');
    expect($capturedMetafields[1]['value'])->toBe('Machine wash cold');

    $dbProduct = Product::where('shopify_id', '990033')->first();
    expect($dbProduct)->not->toBeNull();
    $meta = json_decode($dbProduct->metafields, true);
    expect($meta)->toBe([
        'material' => 'Cotton',
        'care_instructions' => 'Machine wash cold',
    ]);
});

it('Test 10: does not duplicate local database product records on identical Shopify ID', function () {
    $shop = createCreateTestShop();

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function () {
            return Http::response(mockShopifyProductSetResponse([
                'id' => 'gid://shopify/Product/990044',
                'legacyResourceId' => '990044',
                'title' => 'Idempotent Test Product',
            ]), 200);
        },
    ]);

    $postData = [
        'title' => 'Idempotent Test Product',
        'price' => '50.00',
    ];

    // First creation
    $this->withSession(authCreateSession($shop))->post('/createProduct', $postData);
    expect(Product::where('shopify_id', '990044')->where('shop_id', $shop->id)->count())->toBe(1);

    // Second creation with same returned shopify_id
    $this->withSession(authCreateSession($shop))->post('/createProduct', $postData);
    expect(Product::where('shopify_id', '990044')->where('shop_id', $shop->id)->count())->toBe(1);
});

