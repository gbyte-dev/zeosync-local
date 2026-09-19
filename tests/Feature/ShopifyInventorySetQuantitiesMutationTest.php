<?php

namespace Tests\Feature;

use App\Jobs\ProcessInventoryUpdateJob;
use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('domain')->nullable();
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('selected_location_id')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('synced');
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->unique()->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('mapping_id')->nullable();
            $table->string('source_key')->nullable();
            $table->string('sku')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('source')->default('shopify');
            $table->string('source_state')->nullable();
            $table->string('inventory_item_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('location_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('marketplace_id')->nullable();
            $table->integer('desired_quantity')->nullable();
            $table->integer('requested_quantity')->nullable();
            $table->integer('observed_quantity')->nullable();
            $table->integer('baseline_quantity')->nullable();
            $table->integer('delta')->nullable();
            $table->unsignedBigInteger('expected_inventory_version')->nullable();
            $table->string('status')->default('pending');
            $table->string('stage')->default('queued');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->text('error')->nullable();
            $table->text('last_error')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }
});

it('Test A: executes successful OCC mutation with changeFromQuantity, @idempotent directive, and no ignoreCompareQuantity', function () {
    $shop = Shop::create([
        'shop' => 'test-occ-shop.myshopify.com',
        'access_token' => 'shpat_test_token_123',
        'selected_location_index' => 0,
        'shopify_locations' => [['id' => '113329439022', 'name' => 'Primary Location']],
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'sku' => 'SKU-OCC-TEST-1',
        'shopify_inventory_item_id' => '54304187613486',
        'shopify_location_id' => '113329439022',
        'quantity' => 5,
        'inventory_version' => 1,
    ]);

    $opUuid = 'uuid-occ-success-test-12345';
    $operation = InventorySyncOperation::create([
        'operation_uuid' => $opUuid,
        'shop_id' => $shop->id,
        'mapping_id' => $mapping->id,
        'sku' => 'SKU-OCC-TEST-1',
        'shopify_inventory_item_id' => '54304187613486',
        'location_id' => '113329439022',
        'baseline_quantity' => 5,
        'desired_quantity' => 15,
        'expected_inventory_version' => 1,
        'status' => 'pending',
        'stage' => 'pending',
    ]);

    $recordedRequests = [];

    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$recordedRequests) {
        $data = $request->data();
        $query = $data['query'] ?? '';
        $vars = $data['variables'] ?? [];

        if (str_contains($query, 'inventoryItem(') || str_contains($query, 'GetInventoryItemLevels')) {
            return Http::response([
                'data' => [
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/54304187613486',
                        'legacyResourceId' => '54304187613486',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/InventoryLevel/54304187613486?location_id=113329439022',
                                    'location' => [
                                        'id' => 'gid://shopify/Location/113329439022',
                                        'legacyResourceId' => '113329439022',
                                        'name' => 'Primary Location',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 5],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);
        }

        if (str_contains($query, 'inventorySetQuantities') || str_contains($query, 'InventorySetQuantities')) {
            $recordedRequests[] = [
                'query' => $query,
                'variables' => $vars,
            ];

            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => [
                            'reason' => 'cycle_count_available',
                            'changes' => [
                                [
                                    'name' => 'available',
                                    'delta' => 10,
                                    'quantityAfterChange' => 15,
                                    'item' => ['id' => 'gid://shopify/InventoryItem/54304187613486', 'legacyResourceId' => '54304187613486'],
                                    'location' => ['id' => 'gid://shopify/Location/113329439022', 'legacyResourceId' => '113329439022'],
                                ],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200);
        }

        return Http::response(['data' => []], 200);
    });

    $job = new ProcessInventoryUpdateJob($operation->id);
    app()->call([$job, 'handle']);

    expect($recordedRequests)->toHaveCount(1);
    $mutationReq = $recordedRequests[0];

    // Verify GraphQL query contains @idempotent directive
    expect($mutationReq['query'])->toContain('@idempotent(key: $idempotencyKey)');

    // Verify variables input
    $input = $mutationReq['variables']['input'];
    expect($input)->toHaveKey('name', 'available');
    expect($input)->toHaveKey('reason', 'cycle_count_available');
    expect($input)->not->toHaveKey('ignoreCompareQuantity');

    // Verify quantities item
    $quantities = $input['quantities'];
    expect($quantities)->toHaveCount(1);
    expect($quantities[0]['quantity'])->toBe(15);
    expect($quantities[0]['changeFromQuantity'])->toBe(5);
    expect($quantities[0]['inventoryItemId'])->toBe('gid://shopify/InventoryItem/54304187613486');
    expect($quantities[0]['locationId'])->toBe('gid://shopify/Location/113329439022');

    // Verify idempotencyKey
    expect($mutationReq['variables']['idempotencyKey'])->toBe($opUuid);

    // Verify operation and mapping were updated on success
    $operation->refresh();
    expect($operation->status)->toBe('completed');

    $mapping->refresh();
    expect((int) $mapping->quantity)->toBe(15);
    expect($mapping->inventory_version)->toBe(2);
});

it('Test B: handles stale Shopify quantity (CHANGE_FROM_QUANTITY_STALE) gracefully without retrying or syncing Amazon', function () {
    $shop = Shop::create([
        'shop' => 'test-stale-shop.myshopify.com',
        'access_token' => 'shpat_test_token_123',
        'selected_location_index' => 0,
        'shopify_locations' => [['id' => '113329439022', 'name' => 'Primary Location']],
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'sku' => 'SKU-STALE-TEST',
        'shopify_inventory_item_id' => '54304187613486',
        'shopify_location_id' => '113329439022',
        'quantity' => 5,
        'inventory_version' => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid' => 'uuid-stale-test-12345',
        'shop_id' => $shop->id,
        'mapping_id' => $mapping->id,
        'sku' => 'SKU-STALE-TEST',
        'shopify_inventory_item_id' => '54304187613486',
        'location_id' => '113329439022',
        'baseline_quantity' => 5,
        'desired_quantity' => 15,
        'expected_inventory_version' => 1,
        'status' => 'pending',
        'stage' => 'pending',
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $data = $request->data();
        $query = $data['query'] ?? '';

        if (str_contains($query, 'inventoryItem(') || str_contains($query, 'GetInventoryItemLevels')) {
            return Http::response([
                'data' => [
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/54304187613486',
                        'legacyResourceId' => '54304187613486',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/InventoryLevel/54304187613486?location_id=113329439022',
                                    'location' => [
                                        'id' => 'gid://shopify/Location/113329439022',
                                        'legacyResourceId' => '113329439022',
                                        'name' => 'Primary Location',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 5],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);
        }

        if (str_contains($query, 'inventorySetQuantities') || str_contains($query, 'InventorySetQuantities')) {
            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => null,
                        'userErrors' => [
                            [
                                'field' => ['input', 'quantities', '0', 'changeFromQuantity'],
                                'message' => 'The changeFromQuantity argument no longer matches the persisted quantity.',
                                'code' => 'CHANGE_FROM_QUANTITY_STALE',
                            ],
                        ],
                    ],
                ],
            ], 200);
        }

        return Http::response(['data' => []], 200);
    });

    Cache::put("shopify_inventory_{$shop->shop}_location_0", 'cached_val', 3600);

    $job = new ProcessInventoryUpdateJob($operation->id);
    // Should NOT throw an exception
    app()->call([$job, 'handle']);

    $operation->refresh();
    expect($operation->status)->toBe('stale_external_state');
    expect($operation->last_error)->toContain('matches the persisted quantity');

    // Cache should be invalidated
    expect(Cache::has("shopify_inventory_{$shop->shop}_location_0"))->toBeFalse();

    // Mapping quantity should NOT be updated to desired
    $mapping->refresh();
    expect((int) $mapping->quantity)->toBe(5);
});

it('Test C: regression assertion that ignoreCompareQuantity is NEVER included in InventorySetQuantitiesInput', function () {
    $shop = Shop::create([
        'shop' => 'test-regression-shop.myshopify.com',
        'access_token' => 'shpat_test_token_123',
    ]);

    $shopify = new ShopifyService($shop->shop, $shop->access_token);

    $sentVariables = null;
    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$sentVariables) {
        $data = $request->data();
        $sentVariables = $data['variables'] ?? [];
        return Http::response([
            'data' => [
                'inventorySetQuantities' => [
                    'inventoryAdjustmentGroup' => [
                        'reason' => 'cycle_count_available',
                        'changes' => [],
                    ],
                    'userErrors' => [],
                ],
            ],
        ], 200);
    });

    $shopify->setInventoryQuantity(
        $shop,
        '54304187613486',
        '113329439022',
        20,
        10,
        'custom-idempotency-key'
    );

    expect($sentVariables)->not->toBeNull();
    expect($sentVariables['input'])->not->toHaveKey('ignoreCompareQuantity');
    expect($sentVariables['input']['quantities'][0])->not->toHaveKey('ignoreCompareQuantity');
    expect(json_encode($sentVariables))->not->toContain('ignoreCompareQuantity');
});

it('Test D: sends deterministic idempotency key from operation_uuid and reuses it on retry', function () {
    $shop = Shop::create([
        'shop' => 'test-idempotency-shop.myshopify.com',
        'access_token' => 'shpat_test_token_123',
        'selected_location_index' => 0,
        'shopify_locations' => [['id' => '113329439022', 'name' => 'Primary Location']],
    ]);

    $opUuid = 'known-operation-uuid-abcdef-123456';
    $operation = InventorySyncOperation::create([
        'operation_uuid' => $opUuid,
        'shop_id' => $shop->id,
        'sku' => 'SKU-IDEMPOTENT',
        'shopify_inventory_item_id' => '54304187613486',
        'location_id' => '113329439022',
        'baseline_quantity' => 10,
        'desired_quantity' => 20,
        'status' => 'pending',
        'stage' => 'pending',
    ]);

    $idempotencyKeysSent = [];

    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$idempotencyKeysSent) {
        $data = $request->data();
        $query = $data['query'] ?? '';
        $vars = $data['variables'] ?? [];

        if (str_contains($query, 'inventoryItem(') || str_contains($query, 'GetInventoryItemLevels')) {
            return Http::response([
                'data' => [
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/54304187613486',
                        'legacyResourceId' => '54304187613486',
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/InventoryLevel/54304187613486?location_id=113329439022',
                                    'location' => [
                                        'id' => 'gid://shopify/Location/113329439022',
                                        'legacyResourceId' => '113329439022',
                                        'name' => 'Primary Location',
                                    ],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 10],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);
        }

        if (str_contains($query, 'inventorySetQuantities') || str_contains($query, 'InventorySetQuantities')) {
            $idempotencyKeysSent[] = $vars['idempotencyKey'] ?? null;

            // Fail on first attempt to simulate retry
            if (count($idempotencyKeysSent) === 1) {
                return Http::response(['errors' => [['message' => 'Temporary network error']]], 500);
            }

            return Http::response([
                'data' => [
                    'inventorySetQuantities' => [
                        'inventoryAdjustmentGroup' => [
                            'reason' => 'cycle_count_available',
                            'changes' => [],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200);
        }

        return Http::response(['data' => []], 200);
    });

    // First attempt -> fails with 500 error
    $job1 = new ProcessInventoryUpdateJob($operation->id);
    try {
        app()->call([$job1, 'handle']);
    } catch (\Throwable $e) {
        // expected transient error
    }

    // Second attempt (retry)
    $job2 = new ProcessInventoryUpdateJob($operation->id);
    app()->call([$job2, 'handle']);

    expect($idempotencyKeysSent)->toHaveCount(2);
    expect($idempotencyKeysSent[0])->toBe($opUuid);
    expect($idempotencyKeysSent[1])->toBe($opUuid);
});
