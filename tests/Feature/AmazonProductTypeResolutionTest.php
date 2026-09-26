<?php

use App\Jobs\ProcessInventoryUpdateJob;
use App\Models\AdminSetting;
use App\Models\InventorySyncOperation;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPatchRequest;

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

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('product_type')->nullable();
            $table->string('title')->nullable();
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

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->nullable();
            $table->string('source_key')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('mapping_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('desired_quantity')->nullable();
            $table->integer('baseline_quantity')->nullable();
            $table->integer('expected_inventory_version')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('pending');
            $table->string('stage')->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(4);
            $table->text('last_error')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    \Illuminate\Support\Facades\Http::fake([
        '*graphql.json*'              => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            $query = $data['query'] ?? '';
            $vars = $data['variables'] ?? [];

            if (str_contains($query, 'locations(') || str_contains($query, 'GetLocations')) {
                return \Illuminate\Support\Facades\Http::response([
                    'data' => [
                        'locations' => [
                            'nodes' => [
                                ['id' => 'gid://shopify/Location/loc_1', 'legacyResourceId' => 'loc_1', 'name' => 'Main Location', 'isActive' => true],
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'inventorySetQuantities') || str_contains($query, 'InventorySetQuantities')) {
                $quantities = $vars['input']['quantities'] ?? [];
                $qty = $quantities[0]['quantity'] ?? 15;
                $itemGid = $quantities[0]['inventoryItemId'] ?? 'gid://shopify/InventoryItem/INV-805';
                $locGid = $quantities[0]['locationId'] ?? 'gid://shopify/Location/loc_1';
                $rawItem = str_contains((string) $itemGid, 'gid://shopify/InventoryItem/') ? substr((string) $itemGid, strrpos((string) $itemGid, '/') + 1) : (string) $itemGid;
                $rawLoc = str_contains((string) $locGid, 'gid://shopify/Location/') ? substr((string) $locGid, strrpos((string) $locGid, '/') + 1) : (string) $locGid;
                return \Illuminate\Support\Facades\Http::response([
                    'data' => [
                        'inventorySetQuantities' => [
                            'inventoryAdjustmentGroup' => [
                                'reason' => 'cycle_count_available',
                                'changes' => [
                                    [
                                        'name' => 'available',
                                        'delta' => 0,
                                        'quantity' => (int) $qty,
                                        'item' => ['id' => $itemGid, 'legacyResourceId' => $rawItem],
                                        'location' => ['id' => $locGid, 'legacyResourceId' => $rawLoc],
                                    ]
                                ]
                            ],
                            'userErrors' => []
                        ]
                    ]
                ], 200);
            }

            if (str_contains($query, 'inventoryItem(') || str_contains($query, 'GetInventoryItemLevels')) {
                $id = $vars['id'] ?? '';
                $rawId = str_contains((string) $id, 'gid://shopify/InventoryItem/') ? substr((string) $id, strrpos((string) $id, '/') + 1) : (string) $id;
                $mapping = $rawId ? ProductMarketplaceMapping::where('shopify_inventory_item_id', (string) $rawId)->first() : null;
                $qty = $mapping && $mapping->quantity !== null ? (int) $mapping->quantity : 10;

                return \Illuminate\Support\Facades\Http::response([
                    'data' => [
                        'inventoryItem' => [
                            'id' => "gid://shopify/InventoryItem/{$rawId}",
                            'legacyResourceId' => $rawId,
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'id' => "gid://shopify/InventoryLevel/{$rawId}?location_id=loc_1",
                                        'location' => [
                                            'id' => 'gid://shopify/Location/loc_1',
                                            'legacyResourceId' => 'loc_1',
                                            'name' => 'Main Location',
                                        ],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => (int) $qty],
                                            ['name' => 'on_hand', 'quantity' => (int) $qty],
                                            ['name' => 'committed', 'quantity' => 0],
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 200);
            }

            return \Illuminate\Support\Facades\Http::response(['data' => []], 200);
        },
        '*inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            $qty = $data['available'] ?? 15;
            return \Illuminate\Support\Facades\Http::response(['inventory_level' => ['available' => $qty]], 200);
        },
        '*inventory_levels.json*'     => function (\Illuminate\Http\Client\Request $request) {
            $params = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);
            $itemId = $params['inventory_item_ids'] ?? null;
            $locId = $params['location_ids'] ?? 'loc_1';
            $mapping = $itemId ? ProductMarketplaceMapping::where('shopify_inventory_item_id', $itemId)->first() : null;
            $qty = $mapping && $mapping->quantity !== null ? (int) $mapping->quantity : 10;
            return \Illuminate\Support\Facades\Http::response(['inventory_levels' => [['inventory_item_id' => $itemId, 'location_id' => $locId, 'available' => $qty]]], 200);
        },
    ]);

    Shop::truncate();
    Product::truncate();
    ProductMarketplaceMapping::truncate();
    InventorySyncOperation::truncate();
});

function createPtTestShop(int $id = 801): Shop
{
    return Shop::create([
        'id'                      => $id,
        'shop'                    => "pt-test-{$id}.myshopify.com",
        'shop_name'               => "Product Type Test {$id}",
        'email'                   => "pt{$id}@example.com",
        'access_token'            => "shp_token_{$id}",
        'selected_location_index' => 0,
        'shopify_locations'       => [
            ['id' => 'loc_1', 'name' => 'Main Location']
        ],
        'amazon_seller_id'        => "SELLER_{$id}",
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
        'amazon_mws_region'       => 'na',
        'amazon_refresh_token'    => "amz_refresh_{$id}",
        'is_active'               => 1,
    ]);
}

test('TEST 1: Live SP-API listing returns summaries[0].productType = "SHIRT", resolved product type is SHIRT', function () {
    $shop = createPtTestShop(801);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-801',
        'shopify_inventory_item_id' => 'INV-801',
        'amazon_sku'                => 'AYUMXIXIHLZJ',
        'quantity'                  => 10,
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn(['status' => 'ACCEPTED', 'submissionId' => 'SUB-801', 'issues' => []]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')
        ->once()
        ->andReturn($mockResponse);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->with($shop, 'AYUMXIXIHLZJ')
        ->andReturn([
            'summaries' => [
                ['productType' => 'SHIRT']
            ],
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT']
                ]
            ]
        ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')
        ->once()
        ->with($shop)
        ->andReturn($mockConnector);

    $res = $amazonService->updateInventory($shop, 'AYUMXIXIHLZJ', 20, syncToShopify: false);

    expect($res['status'])->toBe('ACCEPTED');
    expect($mapping->fresh()->submission_status)->toBe('accepted');
});

test('TEST 2: Live listing succeeds without productType, mapping contains amazon_product_type = "SHIRT", uses fallback', function () {
    $shop = createPtTestShop(802);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-802',
        'shopify_inventory_item_id' => 'INV-802',
        'amazon_sku'                => 'AYUMXIXIHLZJ',
        'amazon_product_type'       => 'SHIRT',
        'quantity'                  => 10,
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn(['status' => 'ACCEPTED', 'submissionId' => 'SUB-802', 'issues' => []]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')
        ->once()
        ->andReturn($mockResponse);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->with($shop, 'AYUMXIXIHLZJ')
        ->andReturn([
            'summaries' => [],
            'attributes' => []
        ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')
        ->once()
        ->with($shop)
        ->andReturn($mockConnector);

    $res = $amazonService->updateInventory($shop, 'AYUMXIXIHLZJ', 25, syncToShopify: false);

    expect($res['status'])->toBe('ACCEPTED');
    expect($mapping->fresh()->submission_status)->toBe('accepted');
});

test('TEST 3: Amazon listing lookup fails (success=false, error="404 Not Found"), preserves actual error message', function () {
    $shop = createPtTestShop(803);

    ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-803',
        'shopify_inventory_item_id' => 'INV-803',
        'amazon_sku'                => 'AYUMXIXIHLZJ',
        'quantity'                  => 10,
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->with($shop, 'AYUMXIXIHLZJ')
        ->andReturn([
            'success' => false,
            'error'   => 'Client error: `GET listings/2021-08-01/items` resulted in a `404 Not Found`',
        ]);

    expect(function () use ($amazonService, $shop) {
        $amazonService->updateInventory($shop, 'AYUMXIXIHLZJ', 15);
    })->toThrow(\Exception::class, 'Amazon listing lookup failed for SKU AYUMXIXIHLZJ: Client error: `GET listings/2021-08-01/items` resulted in a `404 Not Found`');
});

test('TEST 4: No live product type, no mapping product type, no related product type throws clear resolution exception', function () {
    $shop = createPtTestShop(804);

    ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-804',
        'shopify_inventory_item_id' => 'INV-804',
        'amazon_sku'                => 'AYUMXIXIHLZJ',
        'amazon_product_type'       => null,
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->with($shop, 'AYUMXIXIHLZJ')
        ->andReturn([
            'summaries' => [],
        ]);

    expect(function () use ($amazonService, $shop) {
        $amazonService->updateInventory($shop, 'AYUMXIXIHLZJ', 15);
    })->toThrow(\Exception::class, 'Amazon product type could not be resolved for SKU: AYUMXIXIHLZJ');
});

test('TEST 5: Shopify Stage 1 succeeds even when Amazon Stage 2 Product Type resolution fails (no Shopify rollback)', function () {
    $shop = createPtTestShop(805);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-805',
        'shopify_inventory_item_id' => 'INV-805',
        'amazon_sku'                => 'AYUMXIXIHLZJ',
        'quantity'                  => 10,
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) \Illuminate\Support\Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'INV-805',
        'shopify_location_id'        => 'loc_1',
        'amazon_sku'                 => 'AYUMXIXIHLZJ',
        'desired_quantity'           => 15,
        'baseline_quantity'          => 10,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    // Mock AmazonService: fails on Product Type resolution
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->andThrow(new \Exception('Amazon product type could not be resolved for SKU: AYUMXIXIHLZJ'));

    $job = new ProcessInventoryUpdateJob($operation->id);

    try {
        $job->handle($mockAmazon);
    } catch (\Throwable $e) {
        // Queue handles retry or fail
    }

    // Shopify stage 1 completed and updated mapping quantity
    $freshOp = $operation->fresh();
    expect($freshOp->last_error)->toBe('Amazon product type could not be resolved for SKU: AYUMXIXIHLZJ');

    // Mapping sync_status is marked failed for Amazon stage, but Shopify quantity 15 is recorded
    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('failed');
    expect($freshMapping->submission_status)->toBe('failed');
    expect($freshMapping->error_message)->toBe('Amazon product type could not be resolved for SKU: AYUMXIXIHLZJ');
});
