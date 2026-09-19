<?php

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

function createVariantImageTestShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'variant-img-test.myshopify.com',
        'shop_name' => 'Variant Image Test Store',
        'email' => 'variant@example.com',
        'access_token' => 'shpat_variant_token_' . uniqid(),
        'shopify_locations' => [
            ['id' => 88801, 'name' => 'Main Warehouse', 'active' => true],
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

test('Test 1 - Create product with variant images associates files to product and variants', function () {
    $shop = createVariantImageTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://variant-img-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $recordedRequests[] = $body;

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'title' => 'T-Shirt Multi Color',
                            'handle' => 't-shirt-multi-color',
                            'descriptionHtml' => '<p>Desc</p>',
                            'vendor' => 'Brand',
                            'productType' => 'Shirts',
                            'status' => 'ACTIVE',
                            'tags' => ['apparel'],
                            'options' => [
                                ['id' => 'gid://shopify/ProductOption/1', 'name' => 'Color', 'values' => ['Red', 'Blue'], 'position' => 1]
                            ],
                            'media' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/MediaImage/101',
                                        'image' => ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                    ],
                                    [
                                        'id' => 'gid://shopify/MediaImage/102',
                                        'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                    ]
                                ]
                            ],
                            'images' => [
                                'nodes' => [
                                    ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg'],
                                    ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg']
                                ]
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/501',
                                        'legacyResourceId' => '501',
                                        'title' => 'Red',
                                        'sku' => 'TSHIRT-RED',
                                        'price' => '25.00',
                                        'selectedOptions' => [['name' => 'Color', 'value' => 'Red']],
                                        'media' => [
                                            'nodes' => [
                                                [
                                                    'id' => 'gid://shopify/MediaImage/101',
                                                    'image' => ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                                ]
                                            ]
                                        ],
                                        'image' => ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg'],
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/701', 'legacyResourceId' => '701', 'inventoryLevels' => ['nodes' => []]]
                                    ],
                                    [
                                        'id' => 'gid://shopify/ProductVariant/502',
                                        'legacyResourceId' => '502',
                                        'title' => 'Blue',
                                        'sku' => 'TSHIRT-BLUE',
                                        'price' => '25.00',
                                        'selectedOptions' => [['name' => 'Color', 'value' => 'Blue']],
                                        'media' => [
                                            'nodes' => [
                                                [
                                                    'id' => 'gid://shopify/MediaImage/102',
                                                    'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                                ]
                                            ]
                                        ],
                                        'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg'],
                                        'inventoryQuantity' => 15,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/702', 'legacyResourceId' => '702', 'inventoryLevels' => ['nodes' => []]]
                                    ]
                                ]
                            ]
                        ],
                        'userErrors' => []
                    ]
                ]
            ]);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $payload = [
        'title' => 'T-Shirt Multi Color',
        'description' => '<p>Desc</p>',
        'vendor' => 'Brand',
        'product_type' => 'Shirts',
        'status' => 'active',
        'options' => [
            ['name' => 'Color', 'values' => ['Red', 'Blue']]
        ],
        'variants' => [
            [
                'option1' => 'Red',
                'price' => '25.00',
                'sku' => 'TSHIRT-RED',
                'image' => 'https://cdn.example.com/red.jpg',
            ],
            [
                'option1' => 'Blue',
                'price' => '25.00',
                'sku' => 'TSHIRT-BLUE',
                'image' => 'https://cdn.example.com/blue.jpg',
            ],
        ]
    ];

    $result = $service->createProduct($shop, $payload, 88801);

    expect($result['success'])->toBeTrue();
    expect(count($recordedRequests))->toBe(1);

    $sentInput = $recordedRequests[0]['variables']['input'];

    // Assert productSet.files contains Image A and Image B
    $fileUrls = array_column($sentInput['files'], 'originalSource');
    expect($fileUrls)->toContain('https://cdn.example.com/red.jpg');
    expect($fileUrls)->toContain('https://cdn.example.com/blue.jpg');

    // Assert variant A has file and variant B has file
    expect($sentInput['variants'][0]['file'])->toEqual([
        'originalSource' => 'https://cdn.example.com/red.jpg',
        'contentType' => 'IMAGE',
    ]);
    expect($sentInput['variants'][1]['file'])->toEqual([
        'originalSource' => 'https://cdn.example.com/blue.jpg',
        'contentType' => 'IMAGE',
    ]);
});

test('Test 2 - Change existing variant image updates variant A with new image and preserves variant B existing media ID', function () {
    $shop = createVariantImageTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://variant-img-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';
            $recordedRequests[] = $body;

            // Query: GetProductForView
            if (str_contains($query, 'GetProductForView')) {
                return Http::response([
                    'data' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'title' => 'T-Shirt Multi Color',
                            'handle' => 't-shirt-multi-color',
                            'descriptionHtml' => '<p>Desc</p>',
                            'vendor' => 'Brand',
                            'productType' => 'Shirts',
                            'status' => 'ACTIVE',
                            'tags' => ['apparel'],
                            'options' => [
                                ['id' => 'gid://shopify/ProductOption/1', 'name' => 'Color', 'values' => ['Red', 'Blue'], 'position' => 1]
                            ],
                            'media' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/MediaImage/101',
                                        'image' => ['id' => '101', 'url' => 'https://cdn.example.com/old-red.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                    ],
                                    [
                                        'id' => 'gid://shopify/MediaImage/102',
                                        'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                    ]
                                ]
                            ],
                            'images' => [
                                'nodes' => [
                                    ['id' => '101', 'url' => 'https://cdn.example.com/old-red.jpg'],
                                    ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg']
                                ]
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/501',
                                        'legacyResourceId' => '501',
                                        'title' => 'Red',
                                        'sku' => 'TSHIRT-RED',
                                        'price' => '25.00',
                                        'selectedOptions' => [['name' => 'Color', 'value' => 'Red']],
                                        'media' => [
                                            'nodes' => [
                                                [
                                                    'id' => 'gid://shopify/MediaImage/101',
                                                    'image' => ['id' => '101', 'url' => 'https://cdn.example.com/old-red.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                                ]
                                            ]
                                        ],
                                        'image' => ['id' => '101', 'url' => 'https://cdn.example.com/old-red.jpg'],
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/701', 'legacyResourceId' => '701', 'inventoryLevels' => ['nodes' => []]]
                                    ],
                                    [
                                        'id' => 'gid://shopify/ProductVariant/502',
                                        'legacyResourceId' => '502',
                                        'title' => 'Blue',
                                        'sku' => 'TSHIRT-BLUE',
                                        'price' => '25.00',
                                        'selectedOptions' => [['name' => 'Color', 'value' => 'Blue']],
                                        'media' => [
                                            'nodes' => [
                                                [
                                                    'id' => 'gid://shopify/MediaImage/102',
                                                    'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg', 'altText' => null, 'width' => 800, 'height' => 800]
                                                ]
                                            ]
                                        ],
                                        'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg'],
                                        'inventoryQuantity' => 15,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/702', 'legacyResourceId' => '702', 'inventoryLevels' => ['nodes' => []]]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            }

            // Mutation: productSet
            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'title' => 'T-Shirt Multi Color',
                            'handle' => 't-shirt-multi-color',
                            'descriptionHtml' => '<p>Desc</p>',
                            'vendor' => 'Brand',
                            'productType' => 'Shirts',
                            'status' => 'ACTIVE',
                            'tags' => ['apparel'],
                            'options' => [],
                            'media' => ['nodes' => []],
                            'images' => ['nodes' => []],
                            'variants' => ['nodes' => []],
                        ],
                        'userErrors' => []
                    ]
                ]
            ]);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);

    // Update variant A to Image C, keep variant B unchanged
    $payload = [
        'variants' => [
            [
                'id' => 501,
                'price' => '25.00',
                'sku' => 'TSHIRT-RED',
                'image' => 'https://cdn.example.com/new-red-c.jpg',
            ],
            [
                'id' => 502,
                'price' => '25.00',
                'sku' => 'TSHIRT-BLUE',
                // No image provided -> should preserve existing media ID
            ],
        ]
    ];

    $result = $service->updateProduct($shop, 99001, $payload, 88801);

    expect($result['success'])->toBeTrue();

    // Check the mutation request
    $mutationRequest = null;
    foreach ($recordedRequests as $req) {
        if (str_contains($req['query'] ?? '', 'mutation ProductSet')) {
            $mutationRequest = $req;
            break;
        }
    }

    expect($mutationRequest)->not->toBeNull();
    $sentInput = $mutationRequest['variables']['input'];

    // Product files contains new image C
    $fileUrls = array_column($sentInput['files'], 'originalSource');
    expect($fileUrls)->toContain('https://cdn.example.com/new-red-c.jpg');

    // Variant A has new image
    expect($sentInput['variants'][0]['file'])->toEqual([
        'originalSource' => 'https://cdn.example.com/new-red-c.jpg',
        'contentType' => 'IMAGE',
    ]);

    // Variant B has existing media ID preserved
    expect($sentInput['variants'][1]['file'])->toEqual([
        'id' => 'gid://shopify/MediaImage/102',
    ]);
});

test('Test 3 - Add variant with new image preserves existing variant images and attaches image to new variant', function () {
    $shop = createVariantImageTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://variant-img-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';
            $recordedRequests[] = $body;

            if (str_contains($query, 'GetProductForView')) {
                return Http::response([
                    'data' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'title' => 'T-Shirt',
                            'options' => [
                                ['id' => 'gid://shopify/ProductOption/1', 'name' => 'Color', 'values' => ['Red', 'Blue'], 'position' => 1]
                            ],
                            'media' => [
                                'nodes' => [
                                    ['id' => 'gid://shopify/MediaImage/101', 'image' => ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/102', 'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg']]
                                ]
                            ],
                            'images' => ['nodes' => []],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/501',
                                        'legacyResourceId' => '501',
                                        'title' => 'Red',
                                        'sku' => 'TSHIRT-RED',
                                        'price' => '25.00',
                                        'media' => [
                                            'nodes' => [
                                                ['id' => 'gid://shopify/MediaImage/101', 'image' => ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg']]
                                            ]
                                        ],
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/701', 'legacyResourceId' => '701', 'inventoryLevels' => ['nodes' => []]]
                                    ],
                                    [
                                        'id' => 'gid://shopify/ProductVariant/502',
                                        'legacyResourceId' => '502',
                                        'title' => 'Blue',
                                        'sku' => 'TSHIRT-BLUE',
                                        'price' => '25.00',
                                        'media' => [
                                            'nodes' => [
                                                ['id' => 'gid://shopify/MediaImage/102', 'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg']]
                                            ]
                                        ],
                                        'inventoryQuantity' => 15,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/702', 'legacyResourceId' => '702', 'inventoryLevels' => ['nodes' => []]]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            }

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'options' => [],
                            'media' => ['nodes' => []],
                            'images' => ['nodes' => []],
                            'variants' => ['nodes' => []],
                        ],
                        'userErrors' => []
                    ]
                ]
            ]);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);

    // Update variant A and B (existing) and add variant C (new)
    $payload = [
        'variants' => [
            [
                'id' => 501,
                'price' => '25.00',
                'sku' => 'TSHIRT-RED',
            ],
            [
                'id' => 502,
                'price' => '25.00',
                'sku' => 'TSHIRT-BLUE',
            ],
            [
                'option1' => 'Green',
                'price' => '30.00',
                'sku' => 'TSHIRT-GREEN',
                'image' => 'https://cdn.example.com/green.jpg',
            ]
        ]
    ];

    $result = $service->updateProduct($shop, 99001, $payload, 88801);

    expect($result['success'])->toBeTrue();

    $mutationRequest = null;
    foreach ($recordedRequests as $req) {
        if (str_contains($req['query'] ?? '', 'mutation ProductSet')) {
            $mutationRequest = $req;
            break;
        }
    }

    expect($mutationRequest)->not->toBeNull();
    $sentInput = $mutationRequest['variables']['input'];

    // 3 variants sent
    expect(count($sentInput['variants']))->toBe(3);

    // Variant A preserves media ID 101
    expect($sentInput['variants'][0]['file'])->toEqual(['id' => 'gid://shopify/MediaImage/101']);
    // Variant B preserves media ID 102
    expect($sentInput['variants'][1]['file'])->toEqual(['id' => 'gid://shopify/MediaImage/102']);
    // Variant C gets new image URL
    expect($sentInput['variants'][2]['file'])->toEqual([
        'originalSource' => 'https://cdn.example.com/green.jpg',
        'contentType' => 'IMAGE',
    ]);
});

test('Test 4 - Price and SKU only update preserves existing variant image associations', function () {
    $shop = createVariantImageTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://variant-img-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';
            $recordedRequests[] = $body;

            if (str_contains($query, 'GetProductForView')) {
                return Http::response([
                    'data' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'title' => 'T-Shirt',
                            'options' => [],
                            'media' => [
                                'nodes' => [
                                    ['id' => 'gid://shopify/MediaImage/101', 'image' => ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/102', 'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg']]
                                ]
                            ],
                            'images' => ['nodes' => []],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/501',
                                        'legacyResourceId' => '501',
                                        'title' => 'Red',
                                        'sku' => 'TSHIRT-RED',
                                        'price' => '25.00',
                                        'media' => [
                                            'nodes' => [
                                                ['id' => 'gid://shopify/MediaImage/101', 'image' => ['id' => '101', 'url' => 'https://cdn.example.com/red.jpg']]
                                            ]
                                        ],
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/701', 'legacyResourceId' => '701', 'inventoryLevels' => ['nodes' => []]]
                                    ],
                                    [
                                        'id' => 'gid://shopify/ProductVariant/502',
                                        'legacyResourceId' => '502',
                                        'title' => 'Blue',
                                        'sku' => 'TSHIRT-BLUE',
                                        'price' => '25.00',
                                        'media' => [
                                            'nodes' => [
                                                ['id' => 'gid://shopify/MediaImage/102', 'image' => ['id' => '102', 'url' => 'https://cdn.example.com/blue.jpg']]
                                            ]
                                        ],
                                        'inventoryQuantity' => 15,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/702', 'legacyResourceId' => '702', 'inventoryLevels' => ['nodes' => []]]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            }

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'options' => [],
                            'media' => ['nodes' => []],
                            'images' => ['nodes' => []],
                            'variants' => ['nodes' => []],
                        ],
                        'userErrors' => []
                    ]
                ]
            ]);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);

    // Update only price and SKU, no images in payload
    $payload = [
        'variants' => [
            [
                'id' => 501,
                'price' => '35.00',
                'sku' => 'TSHIRT-RED-NEW',
            ],
            [
                'id' => 502,
                'price' => '35.00',
                'sku' => 'TSHIRT-BLUE-NEW',
            ],
        ]
    ];

    $result = $service->updateProduct($shop, 99001, $payload, 88801);

    expect($result['success'])->toBeTrue();

    $mutationRequest = null;
    foreach ($recordedRequests as $req) {
        if (str_contains($req['query'] ?? '', 'mutation ProductSet')) {
            $mutationRequest = $req;
            break;
        }
    }

    expect($mutationRequest)->not->toBeNull();
    $sentInput = $mutationRequest['variables']['input'];

    // Both variants still have their existing media IDs attached in file
    expect($sentInput['variants'][0]['file'])->toEqual(['id' => 'gid://shopify/MediaImage/101']);
    expect($sentInput['variants'][1]['file'])->toEqual(['id' => 'gid://shopify/MediaImage/102']);
});

test('Test 5 - Read and normalize GraphQL product and variant media nodes', function () {
    $service = new ShopifyService('test.myshopify.com', 'shpat_test');

    $graphqlNode = [
        'id' => 'gid://shopify/Product/12345',
        'legacyResourceId' => '12345',
        'title' => 'Test Product',
        'handle' => 'test-product',
        'descriptionHtml' => '<p>Test</p>',
        'vendor' => 'TestVendor',
        'productType' => 'Shoes',
        'status' => 'ACTIVE',
        'tags' => ['footwear'],
        'options' => [
            ['id' => 'gid://shopify/ProductOption/9', 'name' => 'Size', 'values' => ['10', '11'], 'position' => 1]
        ],
        'media' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/MediaImage/888',
                    'alt' => 'Shoe Front',
                    'mediaContentType' => 'IMAGE',
                    'image' => [
                        'id' => '888',
                        'url' => 'https://cdn.example.com/shoes-888.jpg',
                        'altText' => 'Shoe Front Alt',
                        'width' => 1000,
                        'height' => 1000,
                    ]
                ],
                [
                    'id' => 'gid://shopify/MediaImage/999',
                    'alt' => 'Shoe Back',
                    'mediaContentType' => 'IMAGE',
                    'image' => [
                        'id' => '999',
                        'url' => 'https://cdn.example.com/shoes-999.jpg',
                        'altText' => 'Shoe Back Alt',
                        'width' => 1000,
                        'height' => 1000,
                    ]
                ]
            ]
        ],
        'images' => [
            'nodes' => [
                ['id' => '888', 'url' => 'https://cdn.example.com/shoes-888.jpg', 'altText' => 'Shoe Front Alt', 'width' => 1000, 'height' => 1000],
                ['id' => '999', 'url' => 'https://cdn.example.com/shoes-999.jpg', 'altText' => 'Shoe Back Alt', 'width' => 1000, 'height' => 1000]
            ]
        ],
        'variants' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductVariant/5551',
                    'legacyResourceId' => '5551',
                    'title' => 'Size 10',
                    'sku' => 'SHOE-10',
                    'price' => '100.00',
                    'selectedOptions' => [['name' => 'Size', 'value' => '10']],
                    'media' => [
                        'nodes' => [
                            [
                                'id' => 'gid://shopify/MediaImage/888',
                                'image' => [
                                    'id' => '888',
                                    'url' => 'https://cdn.example.com/shoes-888.jpg',
                                    'altText' => 'Shoe Front Alt',
                                    'width' => 1000,
                                    'height' => 1000,
                                ]
                            ]
                        ]
                    ],
                    'image' => ['id' => '888', 'url' => 'https://cdn.example.com/shoes-888.jpg'],
                    'inventoryQuantity' => 5,
                    'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/4441', 'legacyResourceId' => '4441', 'inventoryLevels' => ['nodes' => []]]
                ],
                [
                    'id' => 'gid://shopify/ProductVariant/5552',
                    'legacyResourceId' => '5552',
                    'title' => 'Size 11',
                    'sku' => 'SHOE-11',
                    'price' => '100.00',
                    'selectedOptions' => [['name' => 'Size', 'value' => '11']],
                    'media' => [
                        'nodes' => []
                    ],
                    'image' => null,
                    'inventoryQuantity' => 8,
                    'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/4442', 'legacyResourceId' => '4442', 'inventoryLevels' => ['nodes' => []]]
                ]
            ]
        ]
    ];

    $normalized = $service->normalizeProductNode($graphqlNode);

    // Verify Product-level images
    expect(count($normalized['images']))->toBe(2);
    expect($normalized['images'][0]['id'])->toBe(888);
    expect($normalized['images'][0]['src'])->toBe('https://cdn.example.com/shoes-888.jpg');

    // Verify Variant 1 has all required image fields
    $v1 = $normalized['variants'][0];
    expect($v1['image_id'])->toBe(888);
    expect($v1['image'])->toEqual([
        'id' => 888,
        'src' => 'https://cdn.example.com/shoes-888.jpg',
        'url' => 'https://cdn.example.com/shoes-888.jpg',
    ]);
    expect($v1['image_src'])->toBe('https://cdn.example.com/shoes-888.jpg');
    expect($v1['media_id'])->toBe(888);

    // Verify Variant 2 with no image returns consistent nulls
    $v2 = $normalized['variants'][1];
    expect($v2['image_id'])->toBeNull();
    expect($v2['image'])->toBeNull();
    expect($v2['image_src'])->toBeNull();
    expect($v2['media_id'])->toBeNull();
});

test('Test 6 - Image replacement verifies old image is replaced with new image URL', function () {
    $shop = createVariantImageTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://variant-img-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';
            $recordedRequests[] = $body;

            if (str_contains($query, 'GetProductForView')) {
                return Http::response([
                    'data' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'title' => 'Single Variant Product',
                            'options' => [],
                            'media' => [
                                'nodes' => [
                                    ['id' => 'gid://shopify/MediaImage/301', 'image' => ['id' => '301', 'url' => 'https://cdn.example.com/old-version-1.jpg']]
                                ]
                            ],
                            'images' => ['nodes' => []],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/501',
                                        'legacyResourceId' => '501',
                                        'title' => 'Default Title',
                                        'sku' => 'SINGLE-1',
                                        'price' => '50.00',
                                        'media' => [
                                            'nodes' => [
                                                ['id' => 'gid://shopify/MediaImage/301', 'image' => ['id' => '301', 'url' => 'https://cdn.example.com/old-version-1.jpg']]
                                            ]
                                        ],
                                        'inventoryQuantity' => 20,
                                        'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/701', 'legacyResourceId' => '701', 'inventoryLevels' => ['nodes' => []]]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            }

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/99001',
                            'legacyResourceId' => '99001',
                            'options' => [],
                            'media' => ['nodes' => []],
                            'images' => ['nodes' => []],
                            'variants' => ['nodes' => []],
                        ],
                        'userErrors' => []
                    ]
                ]
            ]);
        }
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);

    // Replace Image A with Image B
    $payload = [
        'variants' => [
            [
                'id' => 501,
                'price' => '50.00',
                'sku' => 'SINGLE-1',
                'image' => 'https://cdn.example.com/brand-new-version-2.jpg',
            ]
        ]
    ];

    $result = $service->updateProduct($shop, 99001, $payload, 88801);

    expect($result['success'])->toBeTrue();

    $mutationRequest = null;
    foreach ($recordedRequests as $req) {
        if (str_contains($req['query'] ?? '', 'mutation ProductSet')) {
            $mutationRequest = $req;
            break;
        }
    }

    expect($mutationRequest)->not->toBeNull();
    $sentInput = $mutationRequest['variables']['input'];

    // Sent file has new image URL
    expect($sentInput['variants'][0]['file'])->toEqual([
        'originalSource' => 'https://cdn.example.com/brand-new-version-2.jpg',
        'contentType' => 'IMAGE',
    ]);
});
