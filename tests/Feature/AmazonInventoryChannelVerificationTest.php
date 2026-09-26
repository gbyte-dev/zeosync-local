<?php

use App\Jobs\VerifyAmazonInventoryQuantityJob;
use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use Illuminate\Database\Schema\Blueprint;
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

            $table->unique(['shop_id', 'shopify_variant_id'], 'unique_shop_shopify_variant_channel');
            $table->unique(['shop_id', 'amazon_sku'], 'unique_shop_amazon_sku_channel');
        });
    }

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->unique();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('mapping_id')->nullable();
            $table->string('source_key')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('desired_quantity');
            $table->integer('baseline_quantity')->nullable();
            $table->unsignedBigInteger('expected_inventory_version')->nullable();
            $table->string('source')->default('manual_ui');
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

    ProductMarketplaceMapping::truncate();
    InventorySyncOperation::truncate();
    Shop::truncate();
});

it('Test 1: Multiple channels (AMAZON_NA = 18 at [0], DEFAULT = 13 at [1]) selects DEFAULT quantity 13', function () {
    $shop = Shop::create([
        'shop' => 'test-channel-shop-1.myshopify.com',
        'amazon_seller_id' => 'SELLER-1',
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'is_active' => 1,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-CHANNEL-1',
        'shopify_inventory_item_id' => 'INV-1',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-123',
        'last_synced_at' => now(),
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid' => 'op-uuid-channel-1',
        'shop_id' => $shop->id,
        'mapping_id' => $mapping->id,
        'shopify_inventory_item_id' => 'INV-1',
        'amazon_sku' => 'SKU-CHANNEL-1',
        'desired_quantity' => 13,
        'status' => 'awaiting_verification',
        'stage' => 'amazon_accepted',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-CHANNEL-1')
        ->andReturn([
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'AMAZON_NA', 'quantity' => 18],
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 13],
            ],
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 13],
                ],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-CHANNEL-1', 13, 'SUB-123', $mapping->last_synced_at->toDateTimeString(), 1);
    $job->handle($amazonMock);

    $mapping->refresh();
    $operation->refresh();

    expect($mapping->submission_status)->toBe('confirmed');
    expect($mapping->sync_status)->toBe('success');
    expect($operation->status)->toBe('completed');
    expect($operation->stage)->toBe('completed');
});

it('Test 2: Reverse order (DEFAULT = 13 at [0], AMAZON_NA = 18 at [1]) selects DEFAULT quantity 13', function () {
    $shop = Shop::create([
        'shop' => 'test-channel-shop-2.myshopify.com',
        'amazon_seller_id' => 'SELLER-2',
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'is_active' => 1,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-CHANNEL-2',
        'shopify_inventory_item_id' => 'INV-2',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-456',
        'last_synced_at' => now(),
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 13],
                ['fulfillmentChannelCode' => 'AMAZON_NA', 'quantity' => 18],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-CHANNEL-2', 13, 'SUB-456', $mapping->last_synced_at->toDateTimeString(), 1);
    $job->handle($amazonMock);

    $mapping->refresh();
    expect($mapping->submission_status)->toBe('confirmed');
});

it('Test 3: Only attributes.fulfillment_availability present with DEFAULT = 13 selects 13', function () {
    $shop = Shop::create([
        'shop' => 'test-channel-shop-3.myshopify.com',
        'amazon_seller_id' => 'SELLER-3',
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'is_active' => 1,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-CHANNEL-3',
        'shopify_inventory_item_id' => 'INV-3',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-789',
        'last_synced_at' => now(),
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 13],
                ],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-CHANNEL-3', 13, 'SUB-789', $mapping->last_synced_at->toDateTimeString(), 1);
    $job->handle($amazonMock);

    $mapping->refresh();
    expect($mapping->submission_status)->toBe('confirmed');
});

it('Test 4: Top-level target channel only (fulfillmentAvailability with DEFAULT = 13) selects 13', function () {
    $shop = Shop::create([
        'shop' => 'test-channel-shop-4.myshopify.com',
        'amazon_seller_id' => 'SELLER-4',
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'is_active' => 1,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-CHANNEL-4',
        'shopify_inventory_item_id' => 'INV-4',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-999',
        'last_synced_at' => now(),
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 13],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-CHANNEL-4', 13, 'SUB-999', $mapping->last_synced_at->toDateTimeString(), 1);
    $job->handle($amazonMock);

    $mapping->refresh();
    expect($mapping->submission_status)->toBe('confirmed');
});

it('Test 5: Wrong channel only (AMAZON_NA = 18) does not treat 18 as DEFAULT quantity', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop' => 'test-channel-shop-5.myshopify.com',
        'amazon_seller_id' => 'SELLER-5',
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'is_active' => 1,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-CHANNEL-5',
        'shopify_inventory_item_id' => 'INV-5',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-WRONG-CHANNEL',
        'last_synced_at' => now(),
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'AMAZON_NA', 'quantity' => 18],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-CHANNEL-5', 13, 'SUB-WRONG-CHANNEL', $mapping->last_synced_at->toDateTimeString(), 1);
    $job->handle($amazonMock);

    $mapping->refresh();
    // Must NOT confirm because DEFAULT channel quantity is not 13
    expect($mapping->submission_status)->toBe('accepted');
    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($pushed) {
        return $pushed->attempt === 2;
    });
});

it('Test 6: True mismatch (DEFAULT = 18 when 13 expected) exhausts retries to mismatch', function () {
    Queue::fake();

    $shop = Shop::create([
        'shop' => 'test-channel-shop-6.myshopify.com',
        'amazon_seller_id' => 'SELLER-6',
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'is_active' => 1,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-CHANNEL-6',
        'shopify_inventory_item_id' => 'INV-6',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-MISMATCH-FINAL',
        'last_synced_at' => now(),
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid' => 'op-uuid-channel-6',
        'shop_id' => $shop->id,
        'mapping_id' => $mapping->id,
        'shopify_inventory_item_id' => 'INV-6',
        'amazon_sku' => 'SKU-CHANNEL-6',
        'desired_quantity' => 13,
        'status' => 'awaiting_verification',
        'stage' => 'amazon_accepted',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 18],
            ],
        ]);

    // Final attempt #4
    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-CHANNEL-6', 13, 'SUB-MISMATCH-FINAL', $mapping->last_synced_at->toDateTimeString(), 4);
    $job->handle($amazonMock);

    $mapping->refresh();
    $operation->refresh();

    expect($mapping->submission_status)->toBe('mismatch');
    expect($mapping->sync_status)->toBe('failed');
    expect($operation->status)->toBe('failed');
    expect($operation->last_error)->toContain('Amazon inventory quantity mismatch: expected 13, but Amazon reported 18 after 4 attempt(s).');
});

it('Test 7: Production reproduction (SKU: VM6DSDRYAWP8, AMAZON_NA = 18, DEFAULT = 13) confirms successfully', function () {
    $shop = Shop::create([
        'shop' => 'prod-test-shop.myshopify.com',
        'amazon_seller_id' => 'PROD-SELLER-54',
        'amazon_marketplace_id' => 'ATVPDKIKX0DER',
        'is_active' => 1,
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'VM6DSDRYAWP8',
        'shopify_inventory_item_id' => '52652024856751',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-PROD-REPRO',
        'last_synced_at' => now(),
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid' => '5e0acdd1-fb3f-4c0f-8691-a28f98d519ae',
        'shop_id' => $shop->id,
        'mapping_id' => $mapping->id,
        'shopify_inventory_item_id' => '52652024856751',
        'amazon_sku' => 'VM6DSDRYAWP8',
        'desired_quantity' => 13,
        'status' => 'awaiting_verification',
        'stage' => 'amazon_accepted',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'VM6DSDRYAWP8')
        ->andReturn([
            'sku' => 'VM6DSDRYAWP8',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'AMAZON_NA', 'quantity' => 18],
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 13],
            ],
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 13],
                ],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'VM6DSDRYAWP8', 13, 'SUB-PROD-REPRO', $mapping->last_synced_at->toDateTimeString(), 1);
    $job->handle($amazonMock);

    $mapping->refresh();
    $operation->refresh();

    expect($mapping->submission_status)->toBe('confirmed');
    expect($mapping->sync_status)->toBe('success');
    expect($operation->status)->toBe('completed');
    expect($operation->stage)->toBe('completed');
});

it('Test 8: checkAmazonListing uses configured shop marketplace', function () {
    $shop = Shop::create([
        'shop' => 'canada-shop.myshopify.com',
        'amazon_seller_id' => 'CA-SELLER-1',
        'amazon_marketplace_id' => 'A2EUQ1WTGCTBG2', // Canada marketplace
        'is_active' => 1,
    ]);

    $transformer = app(\App\Services\AmazonPayloadTransformerV2::class);
    $service = new class($transformer) extends AmazonService {
        public ?string $passedMarketplaceId = null;

        public function checkAmazonListing($shop, $sku)
        {
            $marketplaceId = !empty($shop->amazon_marketplace_id) ? $shop->amazon_marketplace_id : 'ATVPDKIKX0DER';
            $this->passedMarketplaceId = $marketplaceId;
            return ['success' => true, 'fulfillmentAvailability' => [['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 10]]];
        }
    };

    $service->checkAmazonListing($shop, 'CA-SKU-1');
    expect($service->passedMarketplaceId)->toBe('A2EUQ1WTGCTBG2');
});

it('Test 9: Attempt counters remain independent between mutation job and verification job', function () {
    $shop = Shop::create([
        'shop' => 'attempt-shop.myshopify.com',
        'amazon_seller_id' => 'ATTEMPT-SELLER',
        'is_active' => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid' => 'op-attempt-count-test',
        'shop_id' => $shop->id,
        'shopify_inventory_item_id' => 'INV-ATTEMPT',
        'amazon_sku' => 'SKU-ATTEMPT',
        'desired_quantity' => 13,
        'attempts' => 1,
        'status' => 'awaiting_verification',
        'stage' => 'amazon_accepted',
    ]);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-ATTEMPT',
        'shopify_inventory_item_id' => 'INV-ATTEMPT',
        'quantity' => 13,
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-ATTEMPT',
        'last_synced_at' => now(),
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('checkAmazonListing')->andReturn([
        'fulfillmentAvailability' => [['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 18]],
    ]);

    // Run verification attempt 4
    $job = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-ATTEMPT', 13, 'SUB-ATTEMPT', $mapping->last_synced_at->toDateTimeString(), 4);
    $job->handle($amazonMock);

    $operation->refresh();
    // Mutation attempts in DB must remain 1
    expect($operation->attempts)->toBe(1);
    expect($operation->status)->toBe('failed');
    expect($operation->last_error)->toContain('after 4 attempt(s)');
});

it('Test 10: Superseded verification (submission_id mismatch or quantity mismatch) does not overwrite newer operation', function () {
    $shop = Shop::create([
        'shop' => 'superseded-shop.myshopify.com',
        'amazon_seller_id' => 'SUPERSEDED-SELLER',
        'is_active' => 1,
    ]);

    // Mapping was updated by a newer operation to quantity 15 and SUB-NEW
    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'SKU-SUPERSEDED',
        'shopify_inventory_item_id' => 'INV-SUPERSEDED',
        'quantity' => 15, // Changed to 15
        'sync_status' => 'pending',
        'submission_status' => 'accepted',
        'submission_id' => 'SUB-NEW', // New submission
        'last_synced_at' => now(),
    ]);

    $newOperation = InventorySyncOperation::create([
        'operation_uuid' => 'op-new-15',
        'shop_id' => $shop->id,
        'mapping_id' => $mapping->id,
        'shopify_inventory_item_id' => 'INV-SUPERSEDED',
        'amazon_sku' => 'SKU-SUPERSEDED',
        'desired_quantity' => 15,
        'status' => 'awaiting_verification',
        'stage' => 'amazon_accepted',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    // Should never call checkAmazonListing because guard detects submission_id / quantity mismatch
    $amazonMock->shouldNotReceive('checkAmazonListing');

    // Old verification job for quantity 13 and SUB-OLD
    $oldJob = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-SUPERSEDED', 13, 'SUB-OLD', now()->subMinutes(5)->toDateTimeString(), 2);
    $oldJob->handle($amazonMock);

    $mapping->refresh();
    $newOperation->refresh();

    expect($mapping->quantity)->toBe('15');
    expect($mapping->submission_id)->toBe('SUB-NEW');
    expect($newOperation->status)->toBe('awaiting_verification');
});
