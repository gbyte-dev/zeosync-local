<?php

use App\Jobs\VerifyAmazonInventoryQuantityJob;
use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonFulfillmentChannelResolver;
use App\Services\AmazonService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('access_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('fulfillment_channel_code')->nullable();
            $table->integer('quantity')->default(0);
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('pending');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->unique();
            $table->unsignedBigInteger('shop_id')->index();
            $table->unsignedBigInteger('mapping_id')->nullable()->index();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('desired_quantity')->default(0);
            $table->integer('baseline_quantity')->nullable();
            $table->unsignedBigInteger('expected_inventory_version')->default(1);
            $table->string('source')->default('manual_ui');
            $table->string('status')->default('pending');
            $table->string('stage')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(4);
            $table->text('last_error')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    if (Schema::hasTable('product_marketplace_mappings') && !Schema::hasColumn('product_marketplace_mappings', 'fulfillment_channel_code')) {
        Schema::table('product_marketplace_mappings', function (Blueprint $table) {
            $table->string('fulfillment_channel_code')->nullable();
        });
    }
});

it('TEST 1: Persisted DEFAULT confirms DEFAULT 35 even if AMAZON_NA 18 is first in listing', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-1.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_1',
        'amazon_sku'               => 'SKU-PERSISTED-DEFAULT',
        'fulfillment_channel_code' => 'DEFAULT',
        'quantity'                 => 35,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-1',
        'last_synced_at'           => now(),
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'           => 'uuid-channel-1',
        'shop_id'                  => $shop->id,
        'mapping_id'               => $mapping->id,
        'shopify_inventory_item_id'=> 'inv_item_1',
        'amazon_sku'               => 'SKU-PERSISTED-DEFAULT',
        'desired_quantity'         => 35,
        'status'                   => 'awaiting_verification',
        'stage'                    => 'amazon_accepted',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->with(Mockery::any(), 'SKU-PERSISTED-DEFAULT')
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 18],
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 35],
                ]
            ]
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-PERSISTED-DEFAULT',
        35,
        'SUB-1',
        $mapping->last_synced_at->toDateTimeString(),
        1
    );

    $job->handle($mockAmazon);

    expect($mapping->fresh()->submission_status)->toBe('confirmed')
        ->and($operation->fresh()->status)->toBe('completed');
});

it('TEST 2: Persisted AMAZON_NA confirms AMAZON_NA 35 even if DEFAULT 21 is also present', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-2.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_2',
        'amazon_sku'               => 'SKU-PERSISTED-AMAZON-NA',
        'fulfillment_channel_code' => 'AMAZON_NA',
        'quantity'                 => 35,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-2',
        'last_synced_at'           => now(),
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'           => 'uuid-channel-2',
        'shop_id'                  => $shop->id,
        'mapping_id'               => $mapping->id,
        'shopify_inventory_item_id'=> 'inv_item_2',
        'amazon_sku'               => 'SKU-PERSISTED-AMAZON-NA',
        'desired_quantity'         => 35,
        'status'                   => 'awaiting_verification',
        'stage'                    => 'amazon_accepted',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->with(Mockery::any(), 'SKU-PERSISTED-AMAZON-NA')
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 21],
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 35],
                ]
            ]
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-PERSISTED-AMAZON-NA',
        35,
        'SUB-2',
        $mapping->last_synced_at->toDateTimeString(),
        1
    );

    $job->handle($mockAmazon);

    expect($mapping->fresh()->submission_status)->toBe('confirmed')
        ->and($operation->fresh()->status)->toBe('completed');
});

it('TEST 3: Persisted channel overrides quantity-match fallback (does NOT switch to AMAZON_NA)', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-3.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_3',
        'amazon_sku'               => 'SKU-PERSISTED-STRICT',
        'fulfillment_channel_code' => 'DEFAULT',
        'quantity'                 => 35,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-3',
        'last_synced_at'           => now(),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 21],
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 35],
                ]
            ]
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-PERSISTED-STRICT',
        35,
        'SUB-3',
        $mapping->last_synced_at->toDateTimeString(),
        1
    );

    $job->handle($mockAmazon);

    // Because persisted channel is DEFAULT (quantity 21 != 35), it must NOT confirm via AMAZON_NA!
    expect($mapping->fresh()->submission_status)->toBe('accepted');
    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($pushed) {
        return $pushed->attempt === 2;
    });
});

it('TEST 4: No persisted channel, exactly one quantity match resolves DEFAULT', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-4.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_4',
        'amazon_sku'               => 'SKU-LEGACY-DEFAULT',
        'fulfillment_channel_code' => null,
        'quantity'                 => 35,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-4',
        'last_synced_at'           => now(),
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'           => 'uuid-channel-4',
        'shop_id'                  => $shop->id,
        'mapping_id'               => $mapping->id,
        'shopify_inventory_item_id'=> 'inv_item_4',
        'amazon_sku'               => 'SKU-LEGACY-DEFAULT',
        'desired_quantity'         => 35,
        'status'                   => 'awaiting_verification',
        'stage'                    => 'amazon_accepted',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 18],
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 35],
                ]
            ]
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-LEGACY-DEFAULT',
        35,
        'SUB-4',
        $mapping->last_synced_at->toDateTimeString(),
        1
    );

    $job->handle($mockAmazon);

    expect($mapping->fresh()->submission_status)->toBe('confirmed')
        ->and($mapping->fresh()->fulfillment_channel_code)->toBe('DEFAULT')
        ->and($operation->fresh()->status)->toBe('completed');
});

it('TEST 5: No persisted channel, AMAZON_NA matches resolves AMAZON_NA', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-5.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_5',
        'amazon_sku'               => 'SKU-LEGACY-FBA',
        'fulfillment_channel_code' => null,
        'quantity'                 => 35,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-5',
        'last_synced_at'           => now(),
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'           => 'uuid-channel-5',
        'shop_id'                  => $shop->id,
        'mapping_id'               => $mapping->id,
        'shopify_inventory_item_id'=> 'inv_item_5',
        'amazon_sku'               => 'SKU-LEGACY-FBA',
        'desired_quantity'         => 35,
        'status'                   => 'awaiting_verification',
        'stage'                    => 'amazon_accepted',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 35],
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 21],
                ]
            ]
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-LEGACY-FBA',
        35,
        'SUB-5',
        $mapping->last_synced_at->toDateTimeString(),
        1
    );

    $job->handle($mockAmazon);

    expect($mapping->fresh()->submission_status)->toBe('confirmed')
        ->and($mapping->fresh()->fulfillment_channel_code)->toBe('AMAZON_NA')
        ->and($operation->fresh()->status)->toBe('completed');
});

it('TEST 6: Multiple channels match with NULL persisted channel is AMBIGUOUS and does not confirm', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-6.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_6',
        'amazon_sku'               => 'SKU-AMBIGUOUS',
        'fulfillment_channel_code' => null,
        'quantity'                 => 35,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-6',
        'last_synced_at'           => now(),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 35],
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 35],
                ]
            ]
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-AMBIGUOUS',
        35,
        'SUB-6',
        $mapping->last_synced_at->toDateTimeString(),
        1
    );

    $job->handle($mockAmazon);

    expect($mapping->fresh()->submission_status)->toBe('accepted')
        ->and($mapping->fresh()->fulfillment_channel_code)->toBeNull();
    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class);
});

it('TEST 7: No channel matches continues retry flow', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-7.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_7',
        'amazon_sku'               => 'SKU-NO-MATCH',
        'fulfillment_channel_code' => null,
        'quantity'                 => 35,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-7',
        'last_synced_at'           => now(),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 18],
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 21],
                ]
            ]
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-NO-MATCH',
        35,
        'SUB-7',
        $mapping->last_synced_at->toDateTimeString(),
        1
    );

    $job->handle($mockAmazon);

    expect($mapping->fresh()->submission_status)->toBe('accepted')
        ->and($mapping->fresh()->fulfillment_channel_code)->toBeNull();
    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($pushed) {
        return $pushed->attempt === 2;
    });
});

it('TEST 8: Array order independence between [DEFAULT, AMAZON_NA] and [AMAZON_NA, DEFAULT]', function () {
    $resolver = new AmazonFulfillmentChannelResolver();
    $mapping = new ProductMarketplaceMapping(['fulfillment_channel_code' => 'DEFAULT']);

    $listingA = [
        'fulfillmentAvailability' => [
            ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 35],
            ['fulfillmentChannelCode' => 'AMAZON_NA', 'quantity' => 18],
        ]
    ];

    $listingB = [
        'fulfillmentAvailability' => [
            ['fulfillmentChannelCode' => 'AMAZON_NA', 'quantity' => 18],
            ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 35],
        ]
    ];

    $resA = $resolver->resolve($mapping, 35, $listingA);
    $resB = $resolver->resolve($mapping, 35, $listingB);

    expect($resA['channel'])->toBe('DEFAULT')
        ->and($resB['channel'])->toBe('DEFAULT')
        ->and($resA['source'])->toBe('persisted_mapping')
        ->and($resB['source'])->toBe('persisted_mapping');
});

it('TEST 9: Multiple SKUs with different channels remain strictly isolated', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop'                  => 'channel-test-9.myshopify.com',
        'is_active'             => true,
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
    ]);

    $mappingA = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_A',
        'amazon_sku'               => 'SKU-A-FBM',
        'fulfillment_channel_code' => 'DEFAULT',
        'quantity'                 => 50,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-A',
        'last_synced_at'           => now(),
    ]);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'                  => $shop->id,
        'shopify_inventory_item_id'=> 'inv_item_B',
        'amazon_sku'               => 'SKU-B-FBA',
        'fulfillment_channel_code' => 'AMAZON_NA',
        'quantity'                 => 10,
        'submission_status'        => 'accepted',
        'submission_id'            => 'SUB-B',
        'last_synced_at'           => now(),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->with(Mockery::any(), 'SKU-A-FBM')
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 10],
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 50],
                ]
            ]
        ]);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->with(Mockery::any(), 'SKU-B-FBA')
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 50],
                    ['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 10],
                ]
            ]
        ]);

    $jobA = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-A-FBM', 50, 'SUB-A', $mappingA->last_synced_at->toDateTimeString(), 1);
    $jobB = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-B-FBA', 10, 'SUB-B', $mappingB->last_synced_at->toDateTimeString(), 1);

    $jobA->handle($mockAmazon);
    $jobB->handle($mockAmazon);

    expect($mappingA->fresh()->submission_status)->toBe('confirmed')
        ->and($mappingB->fresh()->submission_status)->toBe('confirmed');
});
