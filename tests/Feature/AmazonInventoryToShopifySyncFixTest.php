<?php

use App\Models\AllProduct;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

function createInventoryTestShop(array $overrides = []): Shop
{
    return Shop::create(array_merge([
        'shop' => 'test-' . uniqid() . '.myshopify.com',
        'access_token' => 'shpat_test_token_' . uniqid(),
        'amazon_seller_id' => 'SELLER_' . uniqid(),
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'shopify_locations' => [
            ['id' => '94269571325', 'name' => 'Primary Location']
        ],
        'selected_location_index' => 0,
        'is_active' => true,
    ], $overrides));
}

function mockShopifyInventoryResponses(int $liveQuantity = 13)
{
    return function (\Illuminate\Http\Client\Request $request) use ($liveQuantity) {
        $body = $request->body();

        if (str_contains($body, 'GetVariantInventoryItem')) {
            return Http::response([
                'data' => [
                    'productVariant' => [
                        'id' => 'gid://shopify/ProductVariant/44556677',
                        'legacyResourceId' => '44556677',
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/88990011',
                            'legacyResourceId' => '88990011',
                        ]
                    ]
                ]
            ], 200);
        }

        if (str_contains($body, 'GetInventoryItemLevels') || str_contains($body, 'inventoryItem(')) {
            return Http::response([
                'data' => [
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/73381590860029',
                        'legacyResourceId' => '73381590860029',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/InventoryLevel/73381590860029?location_id=94269571325',
                                    'location' => [
                                        'id' => 'gid://shopify/Location/94269571325',
                                        'legacyResourceId' => '94269571325',
                                        'name' => 'Primary Location',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => $liveQuantity],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);
        }

        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => [
                            'reason' => 'cycle_count_available',
                            'changes' => [
                                [
                                    'name' => 'available',
                                    'delta' => 7,
                                    'quantityAfterChange' => 20,
                                    'item' => ['id' => 'gid://shopify/InventoryItem/73381590860029', 'legacyResourceId' => '73381590860029'],
                                    'location' => ['id' => 'gid://shopify/Location/94269571325', 'legacyResourceId' => '94269571325'],
                                ]
                            ]
                        ],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }

        return Http::response(['data' => []], 200);
    };
}

beforeEach(function () {
    Cache::flush();
    Queue::fake();

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
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->text('amazon_refresh_token')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_parent_asin')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('fulfillment_channel_code')->nullable();
            $table->string('quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('allproducts')) {
        Schema::create('allproducts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('title')->nullable();
            $table->string('sku')->nullable();
            $table->timestamps();
        });
    }
});

it('TEST 1: Normal amazon_sku mapping resolves correctly and passes live baseline', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_1']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'VariantSofaSKU',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
        'quantity' => '13',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'FURNITURE']],
        'fulfillmentAvailability' => [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 13]],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_123']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $recordedMutations = [];
    Http::fake(function ($request) use (&$recordedMutations) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            $json = json_decode($body, true);
            $recordedMutations[] = $json['variables'] ?? [];
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => ['reason' => 'cycle_count_available'],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
        return mockShopifyInventoryResponses(13)($request);
    });

    $response = $amazonService->updateInventory($shop, 'VariantSofaSKU', 20);

    expect($response['status'])->toBe('ACCEPTED');
    expect($recordedMutations)->toHaveCount(1);
    expect($recordedMutations[0]['input']['quantities'][0]['quantity'])->toBe(20);
    expect($recordedMutations[0]['input']['quantities'][0]['changeFromQuantity'])->toBe(13);

    $mapping->refresh();
    expect($mapping->quantity)->toBe('20');
});

it('TEST 2: amazon_parent_sku fallback resolves correctly when amazon_sku does not match', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_2']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'ChildSKU1',
        'amazon_parent_sku' => 'ParentSofaSKU',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
        'quantity' => '13',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'FURNITURE']],
        'fulfillmentAvailability' => [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 13]],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_456']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    Http::fake(mockShopifyInventoryResponses(13));

    $response = $amazonService->updateInventory($shop, 'ParentSofaSKU', 20);

    expect($response['status'])->toBe('ACCEPTED');
    $mapping->refresh();
    expect($mapping->quantity)->toBe('20');
});

it('TEST 3: Existing shopify_inventory_item_id is reused without extra lookup', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_3']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_EXISTING_ITEM',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_789']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $calledUrls = [];
    Http::fake(function ($request) use (&$calledUrls) {
        $calledUrls[] = $request->body();
        return mockShopifyInventoryResponses(13)($request);
    });

    $amazonService->updateInventory($shop, 'SKU_EXISTING_ITEM', 20);

    $hasVariantQuery = collect($calledUrls)->contains(fn($b) => str_contains($b, 'GetVariantInventoryItem'));
    expect($hasVariantQuery)->toBeFalse();
});

it('TEST 4: Missing shopify_inventory_item_id is resolved using shopify_variant_id and persisted', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_4']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_MISSING_ITEM',
        'shopify_variant_id' => '44556677',
        'shopify_inventory_item_id' => null,
        'shopify_location_id' => '94269571325',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_resolved']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    Http::fake(mockShopifyInventoryResponses(13));

    $amazonService->updateInventory($shop, 'SKU_MISSING_ITEM', 20);

    $mapping->refresh();
    expect($mapping->shopify_inventory_item_id)->toBe('88990011');
});

it('TEST 5: Missing inventory item ID with missing/invalid variant fails safely without calling mutation with null ID', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_5']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_NO_VARIANT',
        'shopify_variant_id' => null,
        'shopify_inventory_item_id' => null,
        'shopify_location_id' => '94269571325',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_safe']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $mutationCalled = false;
    Http::fake(function ($request) use (&$mutationCalled) {
        $mutationCalled = true;
        return Http::response([], 200);
    });

    $response = $amazonService->updateInventory($shop, 'SKU_NO_VARIANT', 20);

    expect($response['status'])->toBe('ACCEPTED');
    expect($mutationCalled)->toBeFalse();
});

it('TEST 6: Production Scenario — Live quantity 13, desired 29 sends changeFromQuantity=13 and quantity=29', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_6']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'VariantBackpackSKU',
        'shopify_inventory_item_id' => '73381590892797',
        'shopify_location_id' => '94269571325',
        'quantity' => '13',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'BACKPACK']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => '861d2c89112c432fa64dd22f97fe7eb0']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $mutationPayload = null;
    Http::fake(function ($request) use (&$mutationPayload) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            $json = json_decode($body, true);
            $mutationPayload = $json['variables'] ?? [];
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => ['reason' => 'cycle_count_available'],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
        return mockShopifyInventoryResponses(13)($request);
    });

    $amazonService->updateInventory($shop, 'VariantBackpackSKU', 29);

    expect($mutationPayload)->not->toBeNull();
    $itemInput = $mutationPayload['input']['quantities'][0];
    expect($itemInput['quantity'])->toBe(29);
    expect($itemInput['changeFromQuantity'])->toBe(13);
    expect($itemInput['inventoryItemId'])->toBe('gid://shopify/InventoryItem/73381590892797');
    expect($itemInput['locationId'])->toBe('gid://shopify/Location/94269571325');
});

it('TEST 7: Live quantity 29, desired 29 sends changeFromQuantity=29 and quantity=29', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_7']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'VariantBackpackSKU',
        'shopify_inventory_item_id' => '73381590892797',
        'shopify_location_id' => '94269571325',
        'quantity' => '29',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'BACKPACK']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_29_29']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $mutationPayload = null;
    Http::fake(function ($request) use (&$mutationPayload) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            $json = json_decode($body, true);
            $mutationPayload = $json['variables'] ?? [];
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => ['reason' => 'cycle_count_available'],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
        return mockShopifyInventoryResponses(29)($request);
    });

    $amazonService->updateInventory($shop, 'VariantBackpackSKU', 29);

    expect($mutationPayload)->not->toBeNull();
    $itemInput = $mutationPayload['input']['quantities'][0];
    expect($itemInput['quantity'])->toBe(29);
    expect($itemInput['changeFromQuantity'])->toBe(29);
});

it('TEST 8: Local mapping quantity differs from live Shopify quantity => live Shopify value wins as baseline', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_8']);

    // Local DB mapping says 50, but live Shopify is actually 18
    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_STALE_DB',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
        'quantity' => '50', // Stale DB value!
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_stale']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $mutationPayload = null;
    Http::fake(function ($request) use (&$mutationPayload) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            $json = json_decode($body, true);
            $mutationPayload = $json['variables'] ?? [];
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => ['reason' => 'cycle_count_available'],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
        // Live Shopify available is 18
        return mockShopifyInventoryResponses(18)($request);
    });

    $amazonService->updateInventory($shop, 'SKU_STALE_DB', 20);

    // Live Shopify value (18) must be used as changeFromQuantity, NOT stale DB (50)
    expect($mutationPayload['input']['quantities'][0]['changeFromQuantity'])->toBe(18);
    expect($mutationPayload['input']['quantities'][0]['quantity'])->toBe(20);
});

it('TEST 9: Defensive Fallback — setInventoryQuantity() called without baseline auto-resolves live baseline', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_DEFENSIVE']);

    $sentVariables = null;
    Http::fake(function ($request) use (&$sentVariables) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            $json = json_decode($body, true);
            $sentVariables = $json['variables'] ?? [];
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => ['reason' => 'cycle_count_available'],
                        'userErrors' => [],
                    ]
                ]
            ], 200);
        }
        return mockShopifyInventoryResponses(7)($request);
    });

    $shopify = new ShopifyService($shop->shop, $shop->access_token);
    // Call without 5th param (changeFromQuantity)
    $response = $shopify->setInventoryQuantity($shop, '73381590860029', '94269571325', 15);

    expect($response['error'])->toBeFalse();
    expect($sentVariables)->not->toBeNull();
    expect($sentVariables['input']['quantities'][0]['changeFromQuantity'])->toBe(7);
    expect($sentVariables['input']['quantities'][0]['quantity'])->toBe(15);
});

it('TEST 10: Live inventory read failure does NOT send mutation without baseline', function () {
    $shop = createInventoryTestShop(['amazon_seller_id' => 'SELLER_ERR']);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_READ_FAIL',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
        'quantity' => '13',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_err']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $mutationAttempted = false;
    Http::fake(function ($request) use (&$mutationAttempted) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            $mutationAttempted = true;
            return Http::response([], 200);
        }
        // Simulate Shopify API error on getInventoryLevel
        return Http::response(['errors' => ['Inventory item not accessible']], 500);
    });

    $response = $amazonService->updateInventory($shop, 'SKU_READ_FAIL', 20);

    // Amazon update accepted, but Shopify mutation was NOT dispatched without a valid baseline
    expect($response['status'])->toBe('ACCEPTED');
    expect($mutationAttempted)->toBeFalse();
});

it('TEST 11: Successful Shopify update invalidates the correct inventory cache', function () {
    $shop = createInventoryTestShop([
        'amazon_seller_id' => 'SELLER_11',
        'selected_location_index' => 0,
    ]);

    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['cached_data' => true], 600);
    expect(Cache::has($cacheKey))->toBeTrue();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_CACHE_TEST',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_cache']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    Http::fake(mockShopifyInventoryResponses(13));

    $amazonService->updateInventory($shop, 'SKU_CACHE_TEST', 20);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('TEST 12: Failed Shopify update does NOT invalidate the cache', function () {
    $shop = createInventoryTestShop([
        'amazon_seller_id' => 'SELLER_12',
        'selected_location_index' => 0,
    ]);

    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['cached_data' => true], 600);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_FAIL_CACHE',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_fail']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    Http::fake(function ($request) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            return Http::response(['errors' => ['Internal server error from Shopify']], 500);
        }
        return mockShopifyInventoryResponses(13)($request);
    });

    $amazonService->updateInventory($shop, 'SKU_FAIL_CACHE', 20);

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('TEST 13: GraphQL userErrors are treated as Shopify failure even when HTTP response is 200', function () {
    $shop = createInventoryTestShop([
        'amazon_seller_id' => 'SELLER_13',
        'selected_location_index' => 0,
    ]);

    $cacheKey = "shopify_inventory_{$shop->shop}_location_0";
    Cache::put($cacheKey, ['cached_data' => true], 600);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU_USER_ERRORS',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id' => '94269571325',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_user_err']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    Http::fake(function ($request) {
        $body = $request->body();
        if (str_contains($body, 'inventorySetQuantities') || str_contains($body, 'InventorySetQuantities')) {
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => null,
                        'userErrors' => [
                            [
                                'field' => ['input', 'quantities', '0', 'inventoryItemId'],
                                'message' => 'Inventory item not found.',
                                'code' => 'INVALID_INVENTORY_ITEM_ID',
                            ]
                        ],
                    ]
                ]
            ], 200);
        }
        return mockShopifyInventoryResponses(13)($request);
    });

    $amazonService->updateInventory($shop, 'SKU_USER_ERRORS', 20);

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('TEST 14: Shop isolation is preserved (mapping of another shop is never matched)', function () {
    $shopA = createInventoryTestShop(['amazon_seller_id' => 'SELLER_A']);
    $shopB = createInventoryTestShop(['amazon_seller_id' => 'SELLER_B']);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id' => $shopB->id,
        'amazon_sku' => 'SHARED_SKU_123',
        'shopify_inventory_item_id' => '99999999999',
        'shopify_location_id' => '94269571325',
        'quantity' => '10',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries' => [['productType' => 'PRODUCT']],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn(new class {
        public function status() { return 200; }
        public function json() { return ['status' => 'ACCEPTED', 'submissionId' => 'sub_iso']; }
    });
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $amazonService->updateInventory($shopA, 'SHARED_SKU_123', 20);

    $mappingB->refresh();
    expect($mappingB->quantity)->toBe('10');
});
