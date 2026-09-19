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

function createGalleryTestShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'gallery-test.myshopify.com',
        'shop_name' => 'Gallery Test Store',
        'email' => 'gallery@example.com',
        'access_token' => 'shpat_gallery_token_' . uniqid(),
        'shopify_locations' => [
            ['id' => 99901, 'name' => 'Main Warehouse', 'active' => true],
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

test('Test 1 - Create product with 4 gallery images sends all four into productSet.files', function () {
    $shop = createGalleryTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://gallery-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $recordedRequests[] = $body;

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/100001',
                            'legacyResourceId' => '100001',
                            'title' => 'Multi Image Gallery Product',
                            'handle' => 'multi-image-gallery-product',
                            'descriptionHtml' => '<p>Description</p>',
                            'vendor' => 'Test Vendor',
                            'productType' => 'Shoes',
                            'status' => 'ACTIVE',
                            'tags' => ['shoes', 'gallery'],
                            'options' => [
                                [
                                    'id' => 'gid://shopify/ProductOption/1',
                                    'name' => 'Title',
                                    'values' => ['Default Title'],
                                    'position' => 1,
                                ],
                            ],
                            'media' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/MediaImage/501',
                                        'alt' => 'Image A',
                                        'mediaContentType' => 'IMAGE',
                                        'image' => ['id' => 'gid://shopify/Image/501', 'url' => 'https://cdn.shopify.com/image-A.jpg'],
                                    ],
                                    [
                                        'id' => 'gid://shopify/MediaImage/502',
                                        'alt' => 'Image B',
                                        'mediaContentType' => 'IMAGE',
                                        'image' => ['id' => 'gid://shopify/Image/502', 'url' => 'https://cdn.shopify.com/image-B.jpg'],
                                    ],
                                    [
                                        'id' => 'gid://shopify/MediaImage/503',
                                        'alt' => 'Image C',
                                        'mediaContentType' => 'IMAGE',
                                        'image' => ['id' => 'gid://shopify/Image/503', 'url' => 'https://cdn.shopify.com/image-C.jpg'],
                                    ],
                                    [
                                        'id' => 'gid://shopify/MediaImage/504',
                                        'alt' => 'Image D',
                                        'mediaContentType' => 'IMAGE',
                                        'image' => ['id' => 'gid://shopify/Image/504', 'url' => 'https://cdn.shopify.com/image-D.jpg'],
                                    ],
                                ],
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/200001',
                                        'legacyResourceId' => '200001',
                                        'title' => 'Default Title',
                                        'sku' => 'SKU-GALLERY-1',
                                        'price' => '49.99',
                                        'compareAtPrice' => null,
                                        'position' => 1,
                                        'selectedOptions' => [
                                            ['name' => 'Title', 'value' => 'Default Title'],
                                        ],
                                        'media' => ['nodes' => []],
                                        'image' => null,
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => [
                                            'id' => 'gid://shopify/InventoryItem/300001',
                                            'legacyResourceId' => '300001',
                                            'inventoryLevels' => ['nodes' => []],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200);
        },
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $payload = [
        'title' => 'Multi Image Gallery Product',
        'description' => 'Description',
        'vendor' => 'Test Vendor',
        'product_type' => 'Shoes',
        'status' => 'active',
        'tags' => ['shoes', 'gallery'],
        'images' => [
            ['src' => 'https://cdn.shopify.com/image-A.jpg'],
            ['src' => 'https://cdn.shopify.com/image-B.jpg'],
            ['src' => 'https://cdn.shopify.com/image-C.jpg'],
            ['src' => 'https://cdn.shopify.com/image-D.jpg'],
        ],
        'variants' => [
            [
                'option1' => 'Default Title',
                'price' => '49.99',
                'sku' => 'SKU-GALLERY-1',
                'qty' => 10,
            ],
        ],
    ];

    $result = $service->createProduct($shop, $payload, 99901);

    expect($result['success'])->toBeTrue();
    expect($recordedRequests)->not->toBeEmpty();
    $productSetRequest = $recordedRequests[0];
    $files = data_get($productSetRequest, 'variables.input.files', []);

    // Assert that all 4 selected images are present in productSet.files
    expect(count($files))->toBe(4);
    $sources = array_column($files, 'originalSource');
    expect($sources)->toContain('https://cdn.shopify.com/image-A.jpg')
        ->toContain('https://cdn.shopify.com/image-B.jpg')
        ->toContain('https://cdn.shopify.com/image-C.jpg')
        ->toContain('https://cdn.shopify.com/image-D.jpg');
});

test('Test 2 - Update existing product adds new gallery images while preserving existing', function () {
    $shop = createGalleryTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://gallery-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $recordedRequests[] = $body;

            // 1. First request is getProductForView query
            if (str_contains($body['query'] ?? '', 'query GetProductForView')) {
                return Http::response([
                    'data' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/100002',
                            'legacyResourceId' => '100002',
                            'title' => 'Existing Gallery Product',
                            'handle' => 'existing-gallery-product',
                            'descriptionHtml' => '<p>Existing Desc</p>',
                            'vendor' => 'Test Vendor',
                            'productType' => 'Shoes',
                            'status' => 'ACTIVE',
                            'tags' => ['shoes'],
                            'options' => [
                                ['id' => 'gid://shopify/ProductOption/1', 'name' => 'Title', 'values' => ['Default Title'], 'position' => 1],
                            ],
                            'media' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/MediaImage/501',
                                        'alt' => 'Image A',
                                        'mediaContentType' => 'IMAGE',
                                        'image' => ['id' => 'gid://shopify/Image/501', 'url' => 'https://cdn.shopify.com/image-A.jpg'],
                                    ],
                                    [
                                        'id' => 'gid://shopify/MediaImage/502',
                                        'alt' => 'Image B',
                                        'mediaContentType' => 'IMAGE',
                                        'image' => ['id' => 'gid://shopify/Image/502', 'url' => 'https://cdn.shopify.com/image-B.jpg'],
                                    ],
                                ],
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/200002',
                                        'legacyResourceId' => '200002',
                                        'title' => 'Default Title',
                                        'sku' => 'SKU-GALLERY-2',
                                        'price' => '39.99',
                                        'compareAtPrice' => null,
                                        'position' => 1,
                                        'selectedOptions' => [
                                            ['name' => 'Title', 'value' => 'Default Title'],
                                        ],
                                        'media' => ['nodes' => []],
                                        'image' => null,
                                        'inventoryQuantity' => 5,
                                        'inventoryItem' => [
                                            'id' => 'gid://shopify/InventoryItem/300002',
                                            'legacyResourceId' => '300002',
                                            'inventoryLevels' => ['nodes' => []],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            // 2. productSet mutation
            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/100002',
                            'legacyResourceId' => '100002',
                            'title' => 'Updated Gallery Product',
                            'handle' => 'updated-gallery-product',
                            'descriptionHtml' => '<p>Updated Desc</p>',
                            'vendor' => 'Test Vendor',
                            'productType' => 'Shoes',
                            'status' => 'ACTIVE',
                            'tags' => ['shoes'],
                            'options' => [
                                ['id' => 'gid://shopify/ProductOption/1', 'name' => 'Title', 'values' => ['Default Title'], 'position' => 1],
                            ],
                            'media' => [
                                'nodes' => [
                                    ['id' => 'gid://shopify/MediaImage/501', 'alt' => 'Image A', 'mediaContentType' => 'IMAGE', 'image' => ['id' => 'gid://shopify/Image/501', 'url' => 'https://cdn.shopify.com/image-A.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/502', 'alt' => 'Image B', 'mediaContentType' => 'IMAGE', 'image' => ['id' => 'gid://shopify/Image/502', 'url' => 'https://cdn.shopify.com/image-B.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/503', 'alt' => 'Image C', 'mediaContentType' => 'IMAGE', 'image' => ['id' => 'gid://shopify/Image/503', 'url' => 'https://cdn.shopify.com/image-C.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/504', 'alt' => 'Image D', 'mediaContentType' => 'IMAGE', 'image' => ['id' => 'gid://shopify/Image/504', 'url' => 'https://cdn.shopify.com/image-D.jpg']],
                                ],
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/200002',
                                        'legacyResourceId' => '200002',
                                        'title' => 'Default Title',
                                        'sku' => 'SKU-GALLERY-2',
                                        'price' => '39.99',
                                        'compareAtPrice' => null,
                                        'position' => 1,
                                        'selectedOptions' => [
                                            ['name' => 'Title', 'value' => 'Default Title'],
                                        ],
                                        'media' => ['nodes' => []],
                                        'image' => null,
                                        'inventoryQuantity' => 5,
                                        'inventoryItem' => [
                                            'id' => 'gid://shopify/InventoryItem/300002',
                                            'legacyResourceId' => '300002',
                                            'inventoryLevels' => ['nodes' => []],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200);
        },
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $payload = [
        'title' => 'Updated Gallery Product',
        'description' => 'Updated Desc',
        'vendor' => 'Test Vendor',
        'product_type' => 'Shoes',
        'status' => 'active',
        'tags' => ['shoes'],
        'images' => [
            ['src' => 'https://cdn.shopify.com/image-A.jpg'],
            ['src' => 'https://cdn.shopify.com/image-B.jpg'],
            ['src' => 'https://cdn.shopify.com/image-C.jpg'],
            ['src' => 'https://cdn.shopify.com/image-D.jpg'],
        ],
        'variants' => [
            [
                'id' => '200002',
                'price' => '39.99',
                'sku' => 'SKU-GALLERY-2',
                'qty' => 5,
            ],
        ],
    ];

    $result = $service->updateProduct($shop, '100002', $payload, 99901);

    expect($result['success'])->toBeTrue();

    $productSetReq = collect($recordedRequests)->first(function ($r) {
        return str_contains($r['query'] ?? '', 'mutation ProductSet');
    });

    expect($productSetReq)->not->toBeNull();
    $files = data_get($productSetReq, 'variables.input.files', []);
    $sources = array_column($files, 'originalSource');

    // Assert that new files C and D are sent to files
    expect($sources)->toContain('https://cdn.shopify.com/image-C.jpg')
        ->toContain('https://cdn.shopify.com/image-D.jpg');
});

test('Test 3 - Update product removes deleted image while preserving remaining', function () {
    $shop = createGalleryTestShop();

    $recordedRequests = [];
    Http::fake([
        'https://gallery-test.myshopify.com/admin/api/2026-07/graphql.json' => function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
            $body = json_decode($request->body(), true);
            $recordedRequests[] = $body;

            if (str_contains($body['query'] ?? '', 'query GetProductForView')) {
                return Http::response([
                    'data' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/100003',
                            'legacyResourceId' => '100003',
                            'title' => 'Product With 4 Images',
                            'media' => [
                                'nodes' => [
                                    ['id' => 'gid://shopify/MediaImage/501', 'image' => ['url' => 'https://cdn.shopify.com/image-A.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/502', 'image' => ['url' => 'https://cdn.shopify.com/image-B.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/503', 'image' => ['url' => 'https://cdn.shopify.com/image-C.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/504', 'image' => ['url' => 'https://cdn.shopify.com/image-D.jpg']],
                                ],
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/200003',
                                        'legacyResourceId' => '200003',
                                        'title' => 'Default Title',
                                        'sku' => 'SKU-3',
                                        'price' => '19.99',
                                        'inventoryQuantity' => 5,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'data' => [
                    'productSet' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/100003',
                            'legacyResourceId' => '100003',
                            'title' => 'Product With 3 Images',
                            'media' => [
                                'nodes' => [
                                    ['id' => 'gid://shopify/MediaImage/501', 'image' => ['url' => 'https://cdn.shopify.com/image-A.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/503', 'image' => ['url' => 'https://cdn.shopify.com/image-C.jpg']],
                                    ['id' => 'gid://shopify/MediaImage/504', 'image' => ['url' => 'https://cdn.shopify.com/image-D.jpg']],
                                ],
                            ],
                            'variants' => [
                                'nodes' => [
                                    [
                                        'id' => 'gid://shopify/ProductVariant/200003',
                                        'legacyResourceId' => '200003',
                                        'title' => 'Default Title',
                                        'sku' => 'SKU-3',
                                        'price' => '19.99',
                                        'inventoryQuantity' => 5,
                                    ],
                                ],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200);
        },
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    // Submitting existing [A, B, C, D] with deleted [B]
    $payload = [
        'title' => 'Product With 3 Images',
        'description' => 'Updated Desc',
        'vendor' => 'Test Vendor',
        'product_type' => 'Shoes',
        'status' => 'active',
        'images' => [
            ['src' => 'https://cdn.shopify.com/image-A.jpg'],
            ['src' => 'https://cdn.shopify.com/image-B.jpg'],
            ['src' => 'https://cdn.shopify.com/image-C.jpg'],
            ['src' => 'https://cdn.shopify.com/image-D.jpg'],
        ],
        'deleted_images' => [
            'https://cdn.shopify.com/image-B.jpg',
        ],
        'variants' => [
            [
                'id' => '200003',
                'price' => '19.99',
                'sku' => 'SKU-3',
                'qty' => 5,
            ],
        ],
    ];

    $result = $service->updateProduct($shop, '100003', $payload, 99901);

    expect($result['success'])->toBeTrue();

    $productSetReq = collect($recordedRequests)->first(function ($r) {
        return str_contains($r['query'] ?? '', 'mutation ProductSet');
    });

    expect($productSetReq)->not->toBeNull();
    $files = data_get($productSetReq, 'variables.input.files', []);
    $sources = array_column($files, 'originalSource');

    // B must not be in files
    expect($sources)->not->toContain('https://cdn.shopify.com/image-B.jpg');
});

test('Test 4 - normalizeProductNode normalizes all product media items into images array', function () {
    $service = new ShopifyService('gallery-test.myshopify.com', 'shpat_fake_token');

    $node = [
        'id' => 'gid://shopify/Product/9999',
        'legacyResourceId' => '9999',
        'title' => 'Four Media Product',
        'options' => [],
        'media' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/MediaImage/101',
                    'alt' => 'Image 1',
                    'mediaContentType' => 'IMAGE',
                    'image' => ['id' => 'gid://shopify/Image/101', 'url' => 'https://cdn.shopify.com/img1.png', 'width' => 800, 'height' => 800],
                ],
                [
                    'id' => 'gid://shopify/MediaImage/102',
                    'alt' => 'Image 2',
                    'mediaContentType' => 'IMAGE',
                    'image' => ['id' => 'gid://shopify/Image/102', 'url' => 'https://cdn.shopify.com/img2.png', 'width' => 800, 'height' => 800],
                ],
                [
                    'id' => 'gid://shopify/MediaImage/103',
                    'alt' => 'Image 3',
                    'mediaContentType' => 'IMAGE',
                    'image' => ['id' => 'gid://shopify/Image/103', 'url' => 'https://cdn.shopify.com/img3.png', 'width' => 800, 'height' => 800],
                ],
                [
                    'id' => 'gid://shopify/MediaImage/104',
                    'alt' => 'Image 4',
                    'mediaContentType' => 'IMAGE',
                    'image' => ['id' => 'gid://shopify/Image/104', 'url' => 'https://cdn.shopify.com/img4.png', 'width' => 800, 'height' => 800],
                ],
            ],
        ],
        'variants' => [
            'nodes' => [],
        ],
    ];

    $normalized = $service->normalizeProductNode($node);

    expect($normalized['images'])->toHaveCount(4);
    expect($normalized['images'][0]['src'])->toBe('https://cdn.shopify.com/img1.png');
    expect($normalized['images'][1]['src'])->toBe('https://cdn.shopify.com/img2.png');
    expect($normalized['images'][2]['src'])->toBe('https://cdn.shopify.com/img3.png');
    expect($normalized['images'][3]['src'])->toBe('https://cdn.shopify.com/img4.png');
});

test('Test 5 - EditProduct view renders all four gallery images with hidden existing_images inputs', function () {
    $shop = createGalleryTestShop();

    $productData = [
        'id' => '100004',
        'title' => 'Product with 4 Gallery Images',
        'vendor' => 'Test Vendor',
        'product_type' => 'Apparel',
        'status' => 'active',
        'tags' => 'tag1',
        'description' => 'Test Description',
        'variants' => [
            [
                'id' => '200004',
                'price' => '25.00',
                'sku' => 'SKU-4',
                'inventory_quantity' => 10,
                'option1' => 'Default Title',
            ],
        ],
        'options' => [
            ['name' => 'Title', 'values' => ['Default Title']],
        ],
        'images' => [
            ['id' => 501, 'src' => 'https://cdn.shopify.com/gallery-A.jpg'],
            ['id' => 502, 'src' => 'https://cdn.shopify.com/gallery-B.jpg'],
            ['id' => 503, 'src' => 'https://cdn.shopify.com/gallery-C.jpg'],
            ['id' => 504, 'src' => 'https://cdn.shopify.com/gallery-D.jpg'],
        ],
        'metafields' => [],
    ];

    $view = view('EditProduct', [
        'product' => $productData,
        'activeShop' => $shop->shop,
        'currency' => 'INR',
        'categories' => [],
        'subcategories' => [],
        'cspNonce' => 'test-nonce-12345',
        'errors' => new \Illuminate\Support\ViewErrorBag(),
    ])->render();

    // Assert that all 4 images are present in the HTML
    expect($view)->toContain('https://cdn.shopify.com/gallery-A.jpg')
        ->toContain('https://cdn.shopify.com/gallery-B.jpg')
        ->toContain('https://cdn.shopify.com/gallery-C.jpg')
        ->toContain('https://cdn.shopify.com/gallery-D.jpg');

    // Assert that 4 hidden existing_images[] inputs are present
    $countInputs = substr_count($view, 'name="existing_images[]"');
    expect($countInputs)->toBe(4);
});
