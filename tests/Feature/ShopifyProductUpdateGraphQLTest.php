<?php

use App\Models\AdminSetting;
use App\Models\AmazonProduct;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductSyncLog;
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
    ProductSyncLog::query()->forceDelete();
});

function createUpdateTestShop(array $attributes = []): Shop
{
    $shop = Shop::create(array_merge([
        'shop' => 'update-test-store.myshopify.com',
        'shop_name' => 'Update Test Store',
        'email' => 'update@example.com',
        'access_token' => 'shpat_update_token_' . uniqid(),
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

function authUpdateSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at' => time(),
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ];
}

function mockExistingProductQueryResponse(array $overrides = []): array
{
    $default = [
        'id' => 'gid://shopify/Product/990011',
        'legacyResourceId' => '990011',
        'title' => 'Original Product Title',
        'handle' => 'original-product-title',
        'descriptionHtml' => '<p>Original description</p>',
        'vendor' => 'OriginalVendor',
        'productType' => 'Apparel',
        'status' => 'ACTIVE',
        'tags' => ['summer', 'apparel'],
        'createdAt' => '2026-03-01T10:00:00Z',
        'updatedAt' => '2026-03-01T10:00:00Z',
        'featuredImage' => [
            'id' => 'gid://shopify/ProductImage/2001',
            'url' => 'https://example.com/image-a.jpg',
            'altText' => 'Image A',
        ],
        'options' => [
            [
                'id' => 'gid://shopify/ProductOption/1001',
                'name' => 'Size',
                'position' => 1,
                'values' => ['Small', 'Large'],
            ],
        ],
        'images' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductImage/2001',
                    'url' => 'https://example.com/image-a.jpg',
                    'altText' => 'Image A',
                    'width' => 800,
                    'height' => 800,
                ],
                [
                    'id' => 'gid://shopify/ProductImage/2002',
                    'url' => 'https://example.com/image-b.jpg',
                    'altText' => 'Image B',
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
                    'title' => 'Small',
                    'sku' => 'PROD-SM',
                    'barcode' => '1111111111',
                    'price' => '29.99',
                    'compareAtPrice' => '39.99',
                    'position' => 1,
                    'selectedOptions' => [
                        ['name' => 'Size', 'value' => 'Small'],
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
                                        ['name' => 'available', 'quantity' => 10],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'gid://shopify/ProductVariant/3002',
                    'legacyResourceId' => '3002',
                    'title' => 'Large',
                    'sku' => 'PROD-LG',
                    'barcode' => '2222222222',
                    'price' => '34.99',
                    'compareAtPrice' => '44.99',
                    'position' => 2,
                    'selectedOptions' => [
                        ['name' => 'Size', 'value' => 'Large'],
                    ],
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/4002',
                        'legacyResourceId' => '4002',
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

    return [
        'data' => [
            'product' => array_merge($default, $overrides),
        ],
    ];
}

function mockUpdatedProductSetResponse(array $productOverrides = [], array $userErrors = []): array
{
    $default = [
        'id' => 'gid://shopify/Product/990011',
        'legacyResourceId' => '990011',
        'title' => 'Updated Product Title',
        'handle' => 'updated-product-title',
        'descriptionHtml' => '<p>Updated description</p>',
        'vendor' => 'UpdatedVendor',
        'productType' => 'UpdatedApparel',
        'status' => 'ACTIVE',
        'tags' => ['winter', 'apparel'],
        'options' => [
            [
                'id' => 'gid://shopify/ProductOption/1001',
                'name' => 'Size',
                'position' => 1,
                'values' => ['Small', 'Large'],
            ],
        ],
        'images' => [
            'nodes' => [
                [
                    'id' => 'gid://shopify/ProductImage/2001',
                    'url' => 'https://example.com/image-a.jpg',
                    'altText' => 'Image A',
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
                    'title' => 'Small',
                    'sku' => 'PROD-SM-UPDATED',
                    'barcode' => '1111111111',
                    'price' => '39.99',
                    'compareAtPrice' => '49.99',
                    'position' => 1,
                    'selectedOptions' => [
                        ['name' => 'Size', 'value' => 'Small'],
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
                                        ['name' => 'available', 'quantity' => 20],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'gid://shopify/ProductVariant/3002',
                    'legacyResourceId' => '3002',
                    'title' => 'Large',
                    'sku' => 'PROD-LG',
                    'barcode' => '2222222222',
                    'price' => '34.99',
                    'compareAtPrice' => '44.99',
                    'position' => 2,
                    'selectedOptions' => [
                        ['name' => 'Size', 'value' => 'Large'],
                    ],
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/4002',
                        'legacyResourceId' => '4002',
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

    return [
        'data' => [
            'productSet' => [
                'product' => empty($userErrors) ? array_merge($default, $productOverrides) : null,
                'userErrors' => $userErrors,
            ],
        ],
    ];
}

it('Test 1: updates a basic product via GraphQL productSet mutation with identifier and persists to local DB', function () {
    $shop = createUpdateTestShop();

    $dbProduct = Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'description' => '<p>Original description</p>',
        'vendor' => 'OriginalVendor',
        'price' => 29.99,
        'status' => 'active',
        'synced_to_amazon' => 0,
    ]);

    $productSetCalled = false;
    $sentIdentifier = null;
    $sentInput = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$productSetCalled, &$sentIdentifier, &$sentInput) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                $productSetCalled = true;
                $sentIdentifier = $body['variables']['identifier'] ?? null;
                $sentInput = $body['variables']['input'] ?? null;
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Updated Product Title',
        'description' => '<p>Updated description</p>',
        'vendor' => 'UpdatedVendor',
        'product_type' => 'UpdatedApparel',
        'status' => 'active',
        'tags' => 'winter, apparel',
        'existing_images' => ['https://example.com/image-a.jpg'],
        'variant_ids' => ['3001'],
        'variant_price' => ['39.99'],
        'variant_sku' => ['PROD-SM-UPDATED'],
        'variant_quantity' => [20],
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);
    $response->assertSessionHas('success', 'Product updated successfully!');

    expect($productSetCalled)->toBeTrue();
    expect($sentIdentifier)->toBe(['id' => 'gid://shopify/Product/990011']);
    expect($sentInput['title'])->toBe('Updated Product Title');
    expect($sentInput['vendor'])->toBe('UpdatedVendor');

    // Verify DB was updated
    $dbProduct->refresh();
    expect($dbProduct->title)->toBe('Updated Product Title');
    expect($dbProduct->vendor)->toBe('UpdatedVendor');
});

it('Test 2: variant anti-deletion safety: omitted existing variants in update request are preserved in productSet payload', function () {
    $shop = createUpdateTestShop();

    Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
    ]);

    $variantsInPayload = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$variantsInPayload) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                // Returns 2 variants: Small (3001) and Large (3002)
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                $variantsInPayload = $body['variables']['input']['variants'] ?? [];
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    // Form ONLY submits variant 3001 (Small), omitting variant 3002 (Large)
    $postData = [
        'title' => 'Updated Product Title',
        'description' => '<p>Updated description</p>',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'variant_ids' => ['3001'],
        'variant_price' => ['49.99'],
        'variant_sku' => ['PROD-SM-NEW'],
        'variant_quantity' => [30],
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);

    // Verify BOTH variants are present in productSet input (omitted variant 3002 was preserved!)
    expect($variantsInPayload)->toHaveCount(2);

    $variant1 = collect($variantsInPayload)->firstWhere('id', 'gid://shopify/ProductVariant/3001');
    $variant2 = collect($variantsInPayload)->firstWhere('id', 'gid://shopify/ProductVariant/3002');

    expect($variant1)->not->toBeNull();
    expect($variant1['price'])->toBe('49.99');
    expect($variant1['sku'])->toBe('PROD-SM-NEW');

    expect($variant2)->not->toBeNull();
    expect($variant2['price'])->toBe('34.99');
    expect($variant2['sku'])->toBe('PROD-LG');
});

it('Test 3: creates new variant on existing product when variant without ID is submitted', function () {
    $shop = createUpdateTestShop();

    Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
    ]);

    $variantsInPayload = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$variantsInPayload) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                $variantsInPayload = $body['variables']['input']['variants'] ?? [];
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    // Form submits variant 3001 plus a NEW variant without ID
    $postData = [
        'title' => 'Updated Product Title',
        'description' => '<p>Updated description</p>',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'variant_ids' => ['3001', ''],
        'variant_price' => ['39.99', '44.99'],
        'variant_sku' => ['PROD-SM-UPDATED', 'PROD-XL-NEW'],
        'variant_quantity' => [20, 15],
        'variant_combo' => [
            json_encode([['name' => 'Size', 'value' => 'Small']]),
            json_encode([['name' => 'Size', 'value' => 'Extra Large']]),
        ],
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);

    // Total variants in payload: 3 (3001 updated, 3002 preserved, new XL variant added)
    expect($variantsInPayload)->toHaveCount(3);

    $newVariant = collect($variantsInPayload)->firstWhere('sku', 'PROD-XL-NEW');
    expect($newVariant)->not->toBeNull();
    expect($newVariant)->not->toHaveKey('id'); // Brand new variant has no Shopify GID
    expect($newVariant['price'])->toBe('44.99');
});

it('Test 4: updates variant inventory quantity at active Shopify location during product authoring', function () {
    $shop = createUpdateTestShop();

    Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
    ]);

    $variantsInPayload = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$variantsInPayload) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                $variantsInPayload = $body['variables']['input']['variants'] ?? [];
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Updated Product Title',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'variant_ids' => ['3001'],
        'variant_price' => ['39.99'],
        'variant_sku' => ['PROD-SM-UPDATED'],
        'variant_quantity' => [50],
    ];

    $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $variant1 = collect($variantsInPayload)->firstWhere('id', 'gid://shopify/ProductVariant/3001');
    expect($variant1['inventoryQuantities'][0]['locationId'])->toBe('gid://shopify/Location/88801');
    expect($variant1['inventoryQuantities'][0]['quantity'])->toBe(50);
});

it('Test 5: explicit image deletion test: existing image A, existing image B, request retains A, request deletes B -> verify A remains, verify B is deleted via fileDelete GraphQL mutation', function () {
    $shop = createUpdateTestShop();

    Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
    ]);

    $fileDeleteCalled = false;
    $deletedFileIds = null;
    $productSetFiles = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$fileDeleteCalled, &$deletedFileIds, &$productSetFiles) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'FileDelete')) {
                $fileDeleteCalled = true;
                $deletedFileIds = $body['variables']['fileIds'] ?? [];
                return Http::response([
                    'data' => [
                        'fileDelete' => [
                            'deletedFileIds' => $deletedFileIds,
                            'userErrors' => [],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'productSet')) {
                $productSetFiles = $body['variables']['input']['files'] ?? [];
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    // Existing: Image A (2001 / url A) and Image B (2002 / url B)
    // Request retains Image A, deletes Image B
    $postData = [
        'title' => 'Updated Product Title',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'existing_images' => ['https://example.com/image-a.jpg', 'https://example.com/image-b.jpg'],
        'deleted_images' => ['2002'],
        'variant_ids' => ['3001'],
        'variant_price' => ['39.99'],
        'variant_sku' => ['PROD-SM'],
        'variant_quantity' => [10],
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);
    $response->assertSessionHas('success', 'Product updated successfully!');

    // Verify Image B was explicitly deleted via fileDelete mutation
    expect($fileDeleteCalled)->toBeTrue();
    expect($deletedFileIds)->toBe(['gid://shopify/ProductImage/2002']);

    // Verify Image A was retained in productSet files
    expect($productSetFiles)->toHaveCount(1);
    expect($productSetFiles[0]['originalSource'])->toBe('https://example.com/image-a.jpg');
});

it('Test 6: syncs product metafields via GraphQL metafieldsSet', function () {
    $shop = createUpdateTestShop();

    $dbProduct = Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
    ]);

    $metafieldsSetCalled = false;
    $sentMetafields = null;

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) use (&$metafieldsSetCalled, &$sentMetafields) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            if (str_contains($query, 'metafieldsSet')) {
                $metafieldsSetCalled = true;
                $sentMetafields = $body['variables']['metafields'] ?? [];
                return Http::response([
                    'data' => [
                        'metafieldsSet' => [
                            'metafields' => [
                                ['id' => 'gid://shopify/Metafield/101', 'namespace' => 'custom', 'key' => 'care_instructions', 'value' => 'Hand wash cold'],
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
        'title' => 'Updated Product Title',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'variant_ids' => ['3001'],
        'variant_price' => ['39.99'],
        'variant_sku' => ['PROD-SM'],
        'variant_quantity' => [10],
        'meta_name' => ['Care Instructions'],
        'meta_value' => ['Hand wash cold'],
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);
    $response->assertSessionHas('success', 'Product updated successfully!');

    expect($metafieldsSetCalled)->toBeTrue();
    expect($sentMetafields[0]['key'])->toBe('care_instructions');
    expect($sentMetafields[0]['value'])->toBe('Hand wash cold');

    $dbProduct->refresh();
    $metafields = json_decode($dbProduct->metafields, true);
    expect($metafields)->toBe(['care_instructions' => 'Hand wash cold']);
});

it('Test 7: metafield failure partial success: productSet succeeds but metafieldsSet fails -> does not claim atomic rollback, does not overwrite local DB metafields, logs warning, returns warning redirect', function () {
    $shop = createUpdateTestShop();

    $dbProduct = Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
        'metafields' => json_encode(['initial_key' => 'initial_value']),
    ]);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                // Product update SUCCEEDS
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            if (str_contains($query, 'metafieldsSet')) {
                // Metafield update FAILS with userErrors
                return Http::response([
                    'data' => [
                        'metafieldsSet' => [
                            'metafields' => [],
                            'userErrors' => [
                                ['field' => ['metafields', '0', 'value'], 'message' => 'Invalid metafield value type'],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Updated Product Title',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'variant_ids' => ['3001'],
        'variant_price' => ['39.99'],
        'variant_sku' => ['PROD-SM'],
        'variant_quantity' => [10],
        'meta_name' => ['Invalid Attribute'],
        'meta_value' => ['Broken Value'],
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    // Returns warning redirect (not complete failure, but notifying user)
    $response->assertRedirect('/products?shop=' . $shop->shop);
    $response->assertSessionHas('warning');

    // Verify core product attributes were updated in DB
    $dbProduct->refresh();
    expect($dbProduct->title)->toBe('Updated Product Title');

    // Verify DB metafields were NOT corrupted with the failed new values
    $metafields = json_decode($dbProduct->metafields, true);
    expect($metafields)->toBe(['initial_key' => 'initial_value']);

    // Verify warning status in ProductSyncLog
    $syncLog = ProductSyncLog::where('product_id', $dbProduct->id)->first();
    expect($syncLog)->not->toBeNull();
    expect($syncLog->status)->toBe('warning');
});

it('Test 8: handles GraphQL top-level errors and userErrors gracefully (redirects back with error, does not corrupt DB)', function () {
    $shop = createUpdateTestShop();

    $dbProduct = Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
    ]);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                return Http::response([
                    'data' => [
                        'productSet' => [
                            'product' => null,
                            'userErrors' => [
                                ['field' => ['input', 'title'], 'message' => 'Title cannot be blank.', 'code' => 'BLANK'],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => '',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'variant_ids' => ['3001'],
        'variant_price' => ['39.99'],
        'variant_sku' => ['PROD-SM'],
        'variant_quantity' => [10],
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $response->assertSessionHas('error');

    // DB remains unchanged
    $dbProduct->refresh();
    expect($dbProduct->title)->toBe('Original Product Title');
});

it('Test 9: tenant isolation: Shop A cannot update Shop B product or access Shop B resources', function () {
    $shopA = createUpdateTestShop(['shop' => 'shop-a.myshopify.com']);
    $shopB = createUpdateTestShop(['shop' => 'shop-b.myshopify.com']);

    $productShopB = Product::create([
        'shopify_id' => '990022',
        'shop_id' => $shopB->id,
        'title' => 'Shop B Product',
        'price' => 50.00,
        'status' => 'active',
    ]);

    Http::fake([
        "https://{$shopA->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(['id' => 'gid://shopify/Product/990022', 'legacyResourceId' => '990022']), 200);
            }

            if (str_contains($query, 'productSet')) {
                return Http::response(mockUpdatedProductSetResponse(['id' => 'gid://shopify/Product/990022', 'legacyResourceId' => '990022']), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Malicious Update by Shop A',
        'vendor' => 'HackerVendor',
        'status' => 'active',
        'variant_ids' => ['3001'],
        'variant_price' => ['1.00'],
        'variant_sku' => ['HACK-SKU'],
        'variant_quantity' => [0],
    ];

    // Shop A attempts to update Shop B's product
    $this->withSession(authUpdateSession($shopA))
        ->post('/updateProduct/990022', $postData);

    // Shop B's DB record must NOT be modified
    $productShopB->refresh();
    expect($productShopB->title)->toBe('Shop B Product');
    expect($productShopB->shop_id)->toBe($shopB->id);
});

it('Test 10: resets synced_to_amazon = 0 when already synced Amazon product is updated and updates AmazonProduct metadata', function () {
    $shop = createUpdateTestShop();

    $dbProduct = Product::create([
        'shopify_id' => '990011',
        'shop_id' => $shop->id,
        'title' => 'Original Product Title',
        'price' => 29.99,
        'status' => 'active',
        'synced_to_amazon' => 1,
    ]);

    AmazonProduct::create([
        'product_id' => $dbProduct->id,
        'amazon_title' => 'Original Amazon Title',
        'sku' => 'AMZ-SKU-01',
    ]);

    Http::fake([
        "https://{$shop->shop}/admin/api/2026-07/graphql.json" => function ($request) {
            $body = json_decode($request->body(), true);
            $query = $body['query'] ?? '';

            if (str_contains($query, 'GetProductForView')) {
                return Http::response(mockExistingProductQueryResponse(), 200);
            }

            if (str_contains($query, 'productSet')) {
                return Http::response(mockUpdatedProductSetResponse(), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $postData = [
        'title' => 'Updated Product Title',
        'vendor' => 'UpdatedVendor',
        'status' => 'active',
        'variant_ids' => ['3001'],
        'variant_price' => ['39.99'],
        'variant_sku' => ['PROD-SM-UPDATED'],
        'variant_quantity' => [20],
        'amazon_title' => 'Updated Amazon Title',
        'sku' => 'AMZ-SKU-01-UPDATED',
    ];

    $response = $this->withSession(authUpdateSession($shop))
        ->post('/updateProduct/990011', $postData);

    $response->assertRedirect('/products?shop=' . $shop->shop);

    $dbProduct->refresh();
    expect((int)$dbProduct->synced_to_amazon)->toBe(0);

    $amazonProduct = AmazonProduct::where('product_id', $dbProduct->id)->first();
    expect($amazonProduct->amazon_title)->toBe('Updated Amazon Title');
    expect($amazonProduct->sku)->toBe('AMZ-SKU-01-UPDATED');
});
