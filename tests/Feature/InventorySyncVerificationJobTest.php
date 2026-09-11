<?php

use App\Jobs\VerifyAmazonInventoryQuantityJob;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
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
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_parent_asin')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('quantity')->nullable();
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'shopify_variant_id'], 'unique_shop_shopify_variant');
            $table->unique(['shop_id', 'amazon_sku'], 'unique_shop_amazon_sku');
        });
    } else {
        ProductMarketplaceMapping::truncate();
    }
});

function createVerificationTestShop(int $id = 1, string $domain = 'test-verify.myshopify.com'): Shop
{
    return Shop::create([
        'id'                      => $id,
        'shop'                    => $domain,
        'access_token'            => 'token-' . $id,
        'selected_location_index' => 0,
        'shopify_locations'       => [
            ['id' => 'loc_123', 'name' => 'Primary Location']
        ],
        'amazon_seller_id'        => 'SELLER_' . $id,
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
        'amazon_mws_region'       => 'na',
        'is_active'               => 1,
    ]);
}

it('1. ACCEPTED response schedules VerifyAmazonInventoryQuantityJob in background (T+25s)', function () {
    Queue::fake();

    $shop = createVerificationTestShop(601);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-601',
        'shopify_inventory_item_id' => 'INV-601',
        'amazon_sku'                => 'SKU-QUEUE-1',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn([
        'status'       => 'ACCEPTED',
        'submissionId' => 'SUBMISSION-JOB-123',
        'issues'       => [],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')
        ->once()
        ->andReturn($mockResponse);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries'  => [['productType' => 'PRODUCT']],
        'attributes' => ['fulfillment_availability' => [['fulfillment_channel_code' => 'DEFAULT']]],
    ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $mockShopify = Mockery::mock(ShopifyService::class);
    $mockShopify->shouldReceive('shopifyRest')->andReturn(['inventory_level' => ['available' => 45]]);
    app()->instance(ShopifyService::class, $mockShopify);

    $amazonService->updateInventory($shop, 'SKU-QUEUE-1', 45);

    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($job) use ($shop) {
        return $job->shopId === $shop->id
            && $job->sku === 'SKU-QUEUE-1'
            && $job->expectedQuantity === 45
            && $job->submissionId === 'SUBMISSION-JOB-123'
            && $job->attempt === 1;
    });
});

it('2. ACCEPTED response does not become CONFIRMED immediately', function () {
    Queue::fake();

    $shop = createVerificationTestShop(602);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-602',
        'shopify_inventory_item_id' => 'INV-602',
        'amazon_sku'                => 'SKU-NOT-CONFIRMED-YET',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn([
        'status'       => 'ACCEPTED',
        'submissionId' => 'SUBMISSION-NO-CONFIRM',
        'issues'       => [],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn($mockResponse);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries'  => [['productType' => 'PRODUCT']],
        'attributes' => ['fulfillment_availability' => [['fulfillment_channel_code' => 'DEFAULT']]],
    ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $mockShopify = Mockery::mock(ShopifyService::class);
    $mockShopify->shouldReceive('shopifyRest')->andReturn(['inventory_level' => ['available' => 70]]);
    app()->instance(ShopifyService::class, $mockShopify);

    $amazonService->updateInventory($shop, 'SKU-NOT-CONFIRMED-YET', 70);

    $freshMapping = $mapping->fresh();
    expect($freshMapping->submission_status)->toBe('accepted');
    expect($freshMapping->submission_status)->not->toBe('confirmed');
    expect($freshMapping->sync_status)->toBe('success');
});

it('3. Matching quantity without issues transitions accepted -> confirmed', function () {
    $shop = createVerificationTestShop(603);

    $syncedAt = now()->toDateTimeString();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-603',
        'shopify_inventory_item_id' => 'INV-603',
        'amazon_sku'                => 'SKU-VERIFY-MATCH',
        'quantity'                  => 50,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-MATCH-1',
        'last_synced_at'            => $syncedAt,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('checkAmazonListing')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-VERIFY-MATCH')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 50]
            ],
            'issues'                  => [],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-VERIFY-MATCH',
        50,
        'SUB-MATCH-1',
        $syncedAt,
        1
    );

    $job->handle($amazonService);

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('success');
    expect($freshMapping->submission_status)->toBe('confirmed');
    expect($freshMapping->error_message)->toBeNull();
});

it('4. FIX 1: Generic unrelated ERROR issue + matching Amazon quantity -> CONFIRMED (not falsely rejected)', function () {
    $shop = createVerificationTestShop(604);

    $syncedAt = now()->toDateTimeString();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-604',
        'shopify_inventory_item_id' => 'INV-604',
        'amazon_sku'                => 'SKU-GENERIC-ERROR-MATCH',
        'quantity'                  => 50,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-GENERIC-1',
        'last_synced_at'            => $syncedAt,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    // Amazon returns pre-existing catalog issues (e.g. missing bullet point / image), but live quantity is 50!
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'status'                  => 'DISCOVERABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 50]
            ],
            'issues'                  => [
                [
                    'severity' => 'ERROR',
                    'code'     => '90001',
                    'message'  => 'Missing mandatory bullet_point attribute for category.',
                ],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-GENERIC-ERROR-MATCH',
        50,
        'SUB-GENERIC-1',
        $syncedAt,
        1
    );

    $job->handle($amazonService);

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('success');
    expect($freshMapping->submission_status)->toBe('confirmed');
    expect($freshMapping->error_message)->toBeNull();
});

it('5. FIX 1: Generic ERROR issue + mismatching quantity -> retries without immediate false rejection', function () {
    Queue::fake();

    $shop = createVerificationTestShop(605);

    $syncedAt = now()->toDateTimeString();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-605',
        'shopify_inventory_item_id' => 'INV-605',
        'amazon_sku'                => 'SKU-GENERIC-ERROR-MISMATCH',
        'quantity'                  => 80,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-GENERIC-MISMATCH',
        'last_synced_at'            => $syncedAt,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    // Amazon has generic issues and quantity is still pre-patch 20 (still processing)
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'status'                  => 'DISCOVERABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 20]
            ],
            'issues'                  => [
                [
                    'severity' => 'ERROR',
                    'code'     => '90001',
                    'message'  => 'Brand catalog verification notice.',
                ],
            ],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-GENERIC-ERROR-MISMATCH',
        80,
        'SUB-GENERIC-MISMATCH',
        $syncedAt,
        1
    );

    $job->handle($amazonService);

    // Remains accepted (NOT rejected!) and schedules attempt 2
    $freshMapping = $mapping->fresh();
    expect($freshMapping->submission_status)->toBe('accepted');
    expect($freshMapping->sync_status)->toBe('success');

    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($pushedJob) {
        return $pushedJob->attempt === 2 && $pushedJob->expectedQuantity === 80;
    });
});

it('6. FIX 2: Bounded retry schedule dispatches Attempt 2 (+35s), Attempt 3 (+60s), Attempt 4 (+60s)', function () {
    Queue::fake();

    $shop = createVerificationTestShop(606);
    $syncedAt = now()->toDateTimeString();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-606',
        'shopify_inventory_item_id' => 'INV-606',
        'amazon_sku'                => 'SKU-SCHEDULE-CHECK',
        'quantity'                  => 60,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-SCHED',
        'last_synced_at'            => $syncedAt,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('checkAmazonListing')
        ->times(3)
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 10] // Not 60 yet
            ],
            'issues'                  => [],
        ]);

    // Attempt 1 -> schedules Attempt 2
    $job1 = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-SCHEDULE-CHECK', 60, 'SUB-SCHED', $syncedAt, 1);
    $job1->handle($amazonService);

    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($job) {
        return $job->attempt === 2;
    });

    // Attempt 2 -> schedules Attempt 3
    $job2 = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-SCHEDULE-CHECK', 60, 'SUB-SCHED', $syncedAt, 2);
    $job2->handle($amazonService);

    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($job) {
        return $job->attempt === 3;
    });

    // Attempt 3 -> schedules Attempt 4
    $job3 = new VerifyAmazonInventoryQuantityJob($shop->id, 'SKU-SCHEDULE-CHECK', 60, 'SUB-SCHED', $syncedAt, 3);
    $job3->handle($amazonService);

    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($job) {
        return $job->attempt === 4;
    });
});

it('7. FIX 2: Final attempt (Attempt 4 @ T+180s) marks mismatch if quantity still differs', function () {
    $shop = createVerificationTestShop(607);

    $syncedAt = now()->toDateTimeString();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-607',
        'shopify_inventory_item_id' => 'INV-607',
        'amazon_sku'                => 'SKU-FINAL-MISMATCH',
        'quantity'                  => 100,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-FINAL',
        'last_synced_at'            => $syncedAt,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    // On attempt 4, live quantity is still 40
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 40]
            ],
            'issues'                  => [],
        ]);

    $job4 = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-FINAL-MISMATCH',
        100,
        'SUB-FINAL',
        $syncedAt,
        4 // Attempt 4 (Final attempt)
    );

    $job4->handle($amazonService);

    $freshMapping = $mapping->fresh();
    expect($freshMapping->submission_status)->toBe('mismatch');
    expect($freshMapping->sync_status)->toBe('failed');
    expect($freshMapping->error_message)->toContain('Amazon inventory quantity mismatch: expected 100, but Amazon reported 40 after 4 attempt(s).');
    // Audit protection: local requested quantity 100 is NOT overwritten by Amazon's 40
    expect((int) $freshMapping->quantity)->toBe(100);
});

it('8. Transient network / cURL exceptions trigger retry bounded by 4 attempts', function () {
    Queue::fake();

    $shop = createVerificationTestShop(608);

    $syncedAt = now()->toDateTimeString();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-608',
        'shopify_inventory_item_id' => 'INV-608',
        'amazon_sku'                => 'SKU-NET-RETRY',
        'quantity'                  => 15,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-NET-RETRY',
        'last_synced_at'            => $syncedAt,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->andThrow(new \Exception('Connection timeout to SP-API'));

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-NET-RETRY',
        15,
        'SUB-NET-RETRY',
        $syncedAt,
        1
    );

    $job->handle($amazonService);

    expect($mapping->fresh()->submission_status)->toBe('accepted');

    Queue::assertPushed(VerifyAmazonInventoryQuantityJob::class, function ($pushedJob) {
        return $pushedJob->attempt === 2;
    });
});

it('9. Older submission verification cannot modify a newer submission (submission_id mismatch)', function () {
    $shop = createVerificationTestShop(609);

    // Newer submission B is active
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-609',
        'shopify_inventory_item_id' => 'INV-609',
        'amazon_sku'                => 'SKU-RACE-PROTECT',
        'quantity'                  => 60,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUBMISSION-B-NEWER',
        'last_synced_at'            => now(),
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldNotReceive('checkAmazonListing');

    // Delayed verification job for older submission A runs
    $jobA = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-RACE-PROTECT',
        50,
        'SUBMISSION-A-OLDER',
        now()->subMinutes(2)->toDateTimeString(),
        1
    );

    $jobA->handle($amazonService);

    // Current DB state for submission B is completely preserved
    $freshMapping = $mapping->fresh();
    expect($freshMapping->submission_id)->toBe('SUBMISSION-B-NEWER');
    expect((int) $freshMapping->quantity)->toBe(60);
    expect($freshMapping->submission_status)->toBe('accepted');
});

it('10. Newer manual quantity remains untouched by older verification (timestamp / quantity check)', function () {
    $shop = createVerificationTestShop(610);

    $t0 = now()->subMinutes(5)->toDateTimeString();
    $t1 = now()->subMinute()->toDateTimeString();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-610',
        'shopify_inventory_item_id' => 'INV-610',
        'amazon_sku'                => 'SKU-TIMESTAMP-PROTECT',
        'quantity'                  => 90,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-SAME-ID',
        'last_synced_at'            => $t1,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldNotReceive('checkAmazonListing');

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-TIMESTAMP-PROTECT',
        50, // Old expected quantity
        'SUB-SAME-ID',
        $t0, // Old sync timestamp
        1
    );

    $job->handle($amazonService);

    $freshMapping = $mapping->fresh();
    expect((int) $freshMapping->quantity)->toBe(90);
    expect($freshMapping->submission_status)->toBe('accepted');
});

it('11. Shop/tenant isolation: verification for Shop A cannot modify Shop B mapping', function () {
    $shopA = createVerificationTestShop(611, 'store-a.myshopify.com');
    $shopB = createVerificationTestShop(612, 'store-b.myshopify.com');

    $syncedAt = now()->toDateTimeString();
    $mappingA = ProductMarketplaceMapping::create([
        'shop_id'            => $shopA->id,
        'shopify_variant_id' => 'V-611',
        'amazon_sku'         => 'SHARED-SKU-VERIFY',
        'quantity'           => 10,
        'sync_status'        => 'success',
        'submission_status'  => 'accepted',
        'submission_id'      => 'SUB-A',
        'last_synced_at'     => $syncedAt,
    ]);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'            => $shopB->id,
        'shopify_variant_id' => 'V-612',
        'amazon_sku'         => 'SHARED-SKU-VERIFY',
        'quantity'           => 20,
        'sync_status'        => 'success',
        'submission_status'  => 'accepted',
        'submission_id'      => 'SUB-B',
        'last_synced_at'     => $syncedAt,
    ]);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldReceive('checkAmazonListing')
        ->with(Mockery::on(fn($s) => $s->id === $shopA->id), 'SHARED-SKU-VERIFY')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 10]
            ],
            'issues'                  => [],
        ]);

    $jobForShopA = new VerifyAmazonInventoryQuantityJob(
        $shopA->id,
        'SHARED-SKU-VERIFY',
        10,
        'SUB-A',
        $syncedAt,
        1
    );

    $jobForShopA->handle($amazonService);

    // Shop A is confirmed
    expect($mappingA->fresh()->submission_status)->toBe('confirmed');
    // Shop B remains untouched
    expect($mappingB->fresh()->submission_status)->toBe('accepted');
    expect((int) $mappingB->fresh()->quantity)->toBe(20);
});

it('12. Missing mapping is handled safely without throwing exception', function () {
    $shop = createVerificationTestShop(613);

    $amazonService = Mockery::mock(AmazonService::class);
    $amazonService->shouldNotReceive('checkAmazonListing');

    $job = new VerifyAmazonInventoryQuantityJob(
        $shop->id,
        'SKU-DOES-NOT-EXIST',
        10,
        'SUB-MISSING',
        now()->toDateTimeString(),
        1
    );

    $job->handle($amazonService);
    expect(true)->toBeTrue();
});
