<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryMappingController;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    } else {
        if (!Schema::hasColumn('product_marketplace_mappings', 'inventory_version')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->unsignedBigInteger('inventory_version')->default(1);
            });
        }
        ProductMarketplaceMapping::truncate();
    }
});

function createMockShop(int $id = 1, string $domain = 'test-shop.myshopify.com'): Shop
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

it('1. Amazon 200 + ACCEPTED updates mapping to success + accepted with submission_id', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $shop = createMockShop(501);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-501',
        'shopify_inventory_item_id' => 'INV-501',
        'amazon_sku'                => 'SKU-ACCEPTED-1',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn([
        'status'       => 'ACCEPTED',
        'submissionId' => 'SUBMISSION-ABC-123',
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

    // Mock Shopify service for inventory set
    $mockShopify = Mockery::mock(ShopifyService::class);
    $mockShopify->shouldReceive('shopifyRest')->andReturn(['inventory_level' => ['available' => 25]]);
    app()->instance(ShopifyService::class, $mockShopify);

    $result = $amazonService->updateInventory($shop, 'SKU-ACCEPTED-1', 25);

    expect($result['status'])->toBe('ACCEPTED');
    expect($result['submissionId'])->toBe('SUBMISSION-ABC-123');

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('success');
    expect($freshMapping->submission_status)->toBe('accepted');
    expect($freshMapping->submission_id)->toBe('SUBMISSION-ABC-123');
    expect($freshMapping->error_message)->toBeNull();
    expect((int) $freshMapping->quantity)->toBe(25);
    expect($freshMapping->last_synced_at)->not->toBeNull();
});

it('2. Amazon 200 + issues updates mapping to failed + rejected and throws exception', function () {
    $shop = createMockShop(502);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-502',
        'shopify_inventory_item_id' => 'INV-502',
        'amazon_sku'                => 'SKU-ISSUES-1',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn([
        'status'       => 'ACCEPTED',
        'submissionId' => 'SUBMISSION-REJECTED-456',
        'issues'       => [
            [
                'severity' => 'ERROR',
                'code'     => '90001',
                'message'  => 'SKU does not exist in catalog.',
            ],
            [
                'severity' => 'WARNING',
                'code'     => '8541',
                'message'  => 'Attribute mismatch.',
            ],
        ],
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

    try {
        $amazonService->updateInventory($shop, 'SKU-ISSUES-1', 30);
        $this->fail('Expected exception was not thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('ERROR [90001]: SKU does not exist in catalog.');
        expect($e->getMessage())->toContain('WARNING [8541]: Attribute mismatch.');
    }

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('failed');
    expect($freshMapping->submission_status)->toBe('rejected');
    expect($freshMapping->submission_id)->toBe('SUBMISSION-REJECTED-456');
    expect($freshMapping->error_message)->toContain('ERROR [90001]: SKU does not exist in catalog.');
});

it('3. Amazon 4xx/5xx updates mapping to failed + failed and records error', function () {
    $shop = createMockShop(503);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-503',
        'shopify_inventory_item_id' => 'INV-503',
        'amazon_sku'                => 'SKU-500-ERROR',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('status')->andReturn(500);
    $mockResponse->shouldReceive('json')->andReturn([
        'message' => 'Internal Server Error on Amazon SP-API',
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

    try {
        $amazonService->updateInventory($shop, 'SKU-500-ERROR', 15);
        $this->fail('Expected exception was not thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('Internal Server Error on Amazon SP-API');
    }

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('failed');
    expect($freshMapping->submission_status)->toBe('failed');
    expect($freshMapping->error_message)->toBe('Internal Server Error on Amazon SP-API');
});

it('4. Amazon network/connection exception marks mapping as failed + failed', function () {
    $shop = createMockShop(504);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-504',
        'shopify_inventory_item_id' => 'INV-504',
        'amazon_sku'                => 'SKU-TIMEOUT-1',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')
        ->once()
        ->andThrow(new \Exception('Connection timed out after 5000ms'));

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries'  => [['productType' => 'PRODUCT']],
        'attributes' => ['fulfillment_availability' => [['fulfillment_channel_code' => 'DEFAULT']]],
    ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    try {
        $amazonService->updateInventory($shop, 'SKU-TIMEOUT-1', 15);
        $this->fail('Expected exception was not thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toBe('Connection timed out after 5000ms');
    }

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('failed');
    expect($freshMapping->submission_status)->toBe('failed');
    expect($freshMapping->error_message)->toBe('Connection timed out after 5000ms');
});

it('5. Failure before Amazon call marks mapping as not_submitted', function () {
    $shop = createMockShop(505);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-505',
        'shopify_inventory_item_id' => 'INV-505',
        'amazon_sku'                => 'SKU-PREFLIGHT-FAIL',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')
        ->once()
        ->andThrow(new \Exception('Listing does not exist on Amazon'));

    try {
        $amazonService->updateInventory($shop, 'SKU-PREFLIGHT-FAIL', 15);
        $this->fail('Expected exception was not thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toBe('Listing does not exist on Amazon');
    }

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('failed');
    expect($freshMapping->submission_status)->toBe('not_submitted');
    expect($freshMapping->error_message)->toBe('Listing does not exist on Amazon');
});

it('6. InventoryMappingController::updateShopifyInventory reports accurate user-facing message on Amazon accepted vs rejected', function () {
    $shop = createMockShop(506);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-506',
        'shopify_inventory_item_id' => 'INV-506',
        'amazon_sku'                => 'SKU-CTRL-TEST',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    // Mock Shopify HTTP calls with dynamic state
    $currentShopifyStock = 10;
    \Illuminate\Support\Facades\Http::fake([
        '*/inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) use (&$currentShopifyStock) {
            $data = $request->data();
            $currentShopifyStock = $data['available'] ?? 20;
            return \Illuminate\Support\Facades\Http::response(['inventory_level' => ['available' => $currentShopifyStock]], 200);
        },
        '*/inventory_levels.json*' => function (\Illuminate\Http\Client\Request $request) use (&$currentShopifyStock) {
            return \Illuminate\Support\Facades\Http::response([
                'inventory_levels' => [
                    ['inventory_item_id' => 'INV-506', 'location_id' => 'loc_123', 'available' => $currentShopifyStock]
                ]
            ], 200);
        },
        '*/products.json*' => \Illuminate\Support\Facades\Http::response(['products' => []], 200),
        '*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    // Test Success (Accepted) Flow
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-CTRL-TEST', 20, false)
        ->andReturn([
            'status'       => 'ACCEPTED',
            'submissionId' => 'SUB-999',
        ]);
    app()->instance(AmazonService::class, $mockAmazon);

    $controller = new InventoryMappingController();
    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'INV-506',
        'quantity'          => 20,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($request);
    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('pending')
        ->and($data['message'])->toBe('Inventory update queued successfully.');

    // Process outbox operation via worker job
    $operation = \App\Models\InventorySyncOperation::find($data['operation_id']);
    expect($operation)->not->toBeNull();

    $job = new \App\Jobs\ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $freshMapping = $mapping->fresh();
    expect($freshMapping->sync_status)->toBe('success');
    expect($freshMapping->submission_status)->toBe('accepted');
    expect($freshMapping->submission_id)->toBe('SUB-999');
    expect($freshMapping->error_message)->toBeNull();

    // Test Rejection Flow
    $mockAmazonRejected = Mockery::mock(AmazonService::class);
    $mockAmazonRejected->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-CTRL-TEST', 30, false)
        ->andThrow(new \Exception('ERROR [90001]: Amazon rejected the inventory update submission.'));
    app()->instance(AmazonService::class, $mockAmazonRejected);


    $request2 = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'INV-506',
        'quantity'          => 30,
    ]);
    $request2->attributes->set('active_shop_model', $shop);

    $response2 = $controller->updateShopifyInventory($request2);
    expect($response2->getStatusCode())->toBe(200);

    $data2 = $response2->getData(true);
    expect($data2['success'])->toBeTrue()
        ->and($data2['status'])->toBe('pending');

    $operation2 = \App\Models\InventorySyncOperation::find($data2['operation_id']);
    expect($operation2)->not->toBeNull();

    $job2 = new \App\Jobs\ProcessInventoryUpdateJob($operation2->id);
    try {
        $job2->handle($mockAmazonRejected);
    } catch (\Throwable $e) {
        // Expected transient / permanent exception
    }

    $freshMapping2 = $mapping->fresh();
    expect($freshMapping2->sync_status)->toBe('failed');
    expect($freshMapping2->error_message)->toContain('ERROR [90001]');
});


it('7. InventoryController::updateAmazonQuantity aligns with status semantics and returns consistent responses', function () {
    $shop = createMockShop(507);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-507',
        'shopify_inventory_item_id' => 'INV-507',
        'amazon_sku'                => 'SKU-AMZ-DIRECT',
        'quantity'                  => 10,
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-AMZ-DIRECT', 55)
        ->once()
        ->andReturn([
            'status'       => 'ACCEPTED',
            'submissionId' => 'SUB-AMZ-DIRECT-1',
        ]);
    app()->instance(AmazonService::class, $mockAmazon);

    $controller = new InventoryController();
    $request = Request::create('/inventory/amazon/SKU-AMZ-DIRECT/update-quantity', 'POST', [
        'quantity' => 55,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateAmazonQuantity($request, 'SKU-AMZ-DIRECT');
    expect($response->getStatusCode())->toBe(200);
    $data = $response->getData(true);
    expect($data['status'])->toBe('ACCEPTED');
    expect($data['submissionId'])->toBe('SUB-AMZ-DIRECT-1');
});

it('8. Backward compatibility is maintained for sync_status (pending, success, failed)', function () {
    $shop = createMockShop(508);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-508',
        'shopify_inventory_item_id' => 'INV-508',
        'amazon_sku'                => 'SKU-COMPAT',
        'sync_status'               => 'pending',
        'submission_status'         => 'not_submitted',
    ]);

    expect($mapping->sync_status)->toBe('pending');
    expect($mapping->submission_status)->toBe('not_submitted');

    $mapping->update([
        'sync_status'       => 'success',
        'submission_status' => 'accepted',
    ]);

    expect($mapping->fresh()->sync_status)->toBe('success');
    expect($mapping->fresh()->submission_status)->toBe('accepted');

    $mapping->update([
        'sync_status'       => 'failed',
        'submission_status' => 'rejected',
    ]);

    expect($mapping->fresh()->sync_status)->toBe('failed');
    expect($mapping->fresh()->submission_status)->toBe('rejected');
});
