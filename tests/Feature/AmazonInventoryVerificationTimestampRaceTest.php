<?php

use App\Jobs\ProcessInventoryUpdateJob;
use App\Jobs\VerifyAmazonInventoryQuantityJob;
use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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
            $table->string('quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'shopify_variant_id'], 'unique_shop_shopify_variant');
            $table->unique(['shop_id', 'amazon_sku'], 'unique_shop_amazon_sku');
        });
    }

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('webhook_event_id')->nullable();
            $table->unsignedBigInteger('mapping_id')->nullable();
            $table->string('source_key')->nullable();
            $table->string('sku')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('source')->default('manual_ui');
            $table->string('source_state')->default('pending');
            $table->string('inventory_item_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('location_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('marketplace_id')->nullable();
            $table->integer('desired_quantity')->nullable();
            $table->integer('requested_quantity')->nullable();
            $table->integer('observed_quantity')->nullable();
            $table->integer('baseline_quantity')->nullable();
            $table->integer('delta')->default(0);
            $table->integer('expected_inventory_version')->nullable();
            $table->string('status')->default('pending');
            $table->string('stage')->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(4);
            $table->text('error')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    ProductMarketplaceMapping::truncate();
    InventorySyncOperation::truncate();
    Shop::truncate();
});

function createTimestampRaceTestShop(int $id = 65, string $domain = 'race-test.myshopify.com'): Shop
{
    return Shop::create([
        'id'                      => $id,
        'shop'                    => $domain,
        'access_token'            => 'token-' . $id,
        'selected_location_index' => 0,
        'shopify_locations'       => [
            ['id' => '94269571325', 'name' => 'Primary Location']
        ],
        'amazon_seller_id'        => 'SELLER_' . $id,
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
        'amazon_mws_region'       => 'na',
        'is_active'               => 1,
    ]);
}

test('TEST 1: Same submission + one-second timestamp difference does not abandon verification and completes operation', function () {
    $shop = createTimestampRaceTestShop(65);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-47',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'quantity'                  => 20,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => '7c50e6d521dd40f98de8505d4d25faa6',
        'last_synced_at'            => '2026-09-29 05:53:05',
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'            => '5c62fc72-f480-4e89-9223-815372497019',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'desired_quantity'          => 20,
        'baseline_quantity'         => 13,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
        'attempts'                  => 1,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('checkAmazonListing')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'VariantSofaSKU')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 20]
            ],
            'issues'                  => [],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'VariantSofaSKU',
        20,
        '7c50e6d521dd40f98de8505d4d25faa6',
        '2026-09-29 05:53:04',
        1
    );

    $job->handle($amazonService);

    expect($mapping->fresh()->submission_status)->toBe('confirmed');
    expect($operation->fresh()->status)->toBe('completed');
    expect($operation->fresh()->stage)->toBe('completed');
    expect($operation->fresh()->completed_at)->not->toBeNull();
});

test('TEST 2: Genuinely newer submission causes older verification to abandon safely', function () {
    $shop = createTimestampRaceTestShop(65);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-47',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'quantity'                  => 25,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'NEW_SUBMISSION_UUID_888',
        'last_synced_at'            => '2026-09-29 05:54:00',
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldNotReceive('checkAmazonListing');

    $oldJob = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'VariantSofaSKU',
        20,
        'OLD_SUBMISSION_UUID_111',
        '2026-09-29 05:53:04',
        1
    );

    $oldJob->handle($amazonService);

    // Newer mapping state remains untouched
    expect($mapping->fresh()->submission_status)->toBe('accepted');
    expect((int) $mapping->fresh()->quantity)->toBe(25);
    expect($mapping->fresh()->submission_id)->toBe('NEW_SUBMISSION_UUID_888');
});

test('TEST 3: Exact timestamp alignment in AmazonService::updateInventory', function () {
    Queue::fake();

    $shop = createTimestampRaceTestShop(65);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-47',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'quantity'                  => 13,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $fixedTimestamp = Carbon::parse('2026-09-29 05:53:04');
    Carbon::setTestNow($fixedTimestamp);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn([
        'status'       => 'ACCEPTED',
        'submissionId' => 'SUB-ALIGN-123',
        'issues'       => [],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')
        ->once()
        ->andReturn($mockResponse);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries'  => [['productType' => 'SOFA']],
        'attributes' => ['fulfillment_availability' => [['fulfillment_channel_code' => 'DEFAULT']]],
    ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $amazonService->updateInventory($shop, 'VariantSofaSKU', 20, syncToShopify: false, acquireLock: false);

    expect($mapping->fresh()->last_synced_at->toDateTimeString())->toBe('2026-09-29 05:53:04');

    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($job) {
        return $job->sku === 'VariantSofaSKU'
            && $job->expectedQuantity === 20
            && $job->submissionId === 'SUB-ALIGN-123'
            && $job->syncedAt === '2026-09-29 05:53:04';
    });

    Carbon::setTestNow();
});

test('TEST 4: ProcessInventoryUpdateJob does not overwrite last_synced_at after AmazonService returns', function () {
    Queue::fake();

    $shop = createTimestampRaceTestShop(65);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-47',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'quantity'                  => 20,
        'inventory_version'         => 2,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'            => '5c62fc72-f480-4e89-9223-815372497019',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'desired_quantity'          => 20,
        'baseline_quantity'         => 20,
        'expected_inventory_version'=> 1,
        'status'                    => 'processing',
        'stage'                     => 'shopify_completed',
        'attempts'                  => 1,
    ]);

    $initialTimestamp = '2026-09-29 05:53:04';

    Http::fake([
        '*graphql.json*' => function (\Illuminate\Http\Client\Request $request) {
            return Http::response([
                'data' => [
                    'inventoryItem' => [
                        'inventoryLevels' => [
                            'nodes' => [
                                [
                                    'location' => ['id' => 'gid://shopify/Location/94269571325', 'legacyResourceId' => '94269571325'],
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 20]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200);
        }
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('updateInventory')
        ->once()
        ->andReturnUsing(function () use ($mapping, $initialTimestamp) {
            $mapping->update([
                'quantity'          => 20,
                'sync_status'       => 'success',
                'submission_status' => 'accepted',
                'submission_id'     => 'SUB-777',
                'last_synced_at'    => $initialTimestamp,
            ]);
            return ['status' => 'ACCEPTED', 'submissionId' => 'SUB-777'];
        });

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($amazonService);

    // Assert mapping's last_synced_at remained 05:53:04 and was NOT overwritten by now() in ProcessInventoryUpdateJob
    expect($mapping->fresh()->last_synced_at->toDateTimeString())->toBe('2026-09-29 05:53:04');
    expect($operation->fresh()->status)->toBe('awaiting_verification');
    expect($operation->fresh()->stage)->toBe('amazon_accepted');
});

test('TEST 5: Existing successful verification behavior confirms live inventory', function () {
    $shop = createTimestampRaceTestShop(65);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-47',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'quantity'                  => 20,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-MATCH-100',
        'last_synced_at'            => '2026-09-29 05:53:04',
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'            => '5c62fc72-f480-4e89-9223-815372497019',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'desired_quantity'          => 20,
        'baseline_quantity'         => 13,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
        'attempts'                  => 1,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('checkAmazonListing')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'VariantSofaSKU')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 20]
            ],
            'issues'                  => [],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'VariantSofaSKU',
        20,
        'SUB-MATCH-100',
        '2026-09-29 05:53:04',
        1
    );

    $job->handle($amazonService);

    expect($mapping->fresh()->submission_status)->toBe('confirmed');
    expect($operation->fresh()->status)->toBe('completed');
});

test('TEST 6: Quantity mismatch still schedules existing reconciliation retry behavior', function () {
    Queue::fake();

    $shop = createTimestampRaceTestShop(65);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-47',
        'shopify_inventory_item_id' => '73381590860029',
        'shopify_location_id'       => '94269571325',
        'amazon_sku'                => 'VariantSofaSKU',
        'quantity'                  => 20,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-MISMATCH-100',
        'last_synced_at'            => '2026-09-29 05:53:04',
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('checkAmazonListing')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'VariantSofaSKU')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 13] // still old stock on Amazon
            ],
            'issues'                  => [],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'VariantSofaSKU',
        20,
        'SUB-MISMATCH-100',
        '2026-09-29 05:53:04',
        1
    );

    $job->handle($amazonService);

    // Should push attempt 2 to queue
    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($pushedJob) {
        return $pushedJob->attempt === 2
            && $pushedJob->sku === 'VariantSofaSKU'
            && $pushedJob->expectedQuantity === 20
            && $pushedJob->submissionId === 'SUB-MISMATCH-100'
            && $pushedJob->syncedAt === '2026-09-29 05:53:04';
    });

    // Mapping remains in accepted status waiting for retry
    expect($mapping->fresh()->submission_status)->toBe('accepted');
});
