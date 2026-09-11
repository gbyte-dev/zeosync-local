<?php

use App\Console\Commands\RecoverInventorySyncOperationsCommand;
use App\Http\Controllers\InventoryMappingController;
use App\Jobs\ProcessInventoryUpdateJob;
use App\Jobs\VerifyAmazonInventoryQuantityJob;
use App\Models\AdminSetting;
use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*graphql.json*'              => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => [['id' => 'gid://shopify/AppSubscription/1', 'name' => 'Pro', 'status' => 'ACTIVE', 'currentPeriodEnd' => '2030-01-01T00:00:00Z']]]]], 200),
        '*inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            $qty = $data['available'] ?? 25;
            return Http::response(['inventory_level' => ['available' => $qty]], 200);
        },
        '*inventory_levels.json*'     => function (\Illuminate\Http\Client\Request $request) {
            $params = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);
            $itemId = $params['inventory_item_ids'] ?? 12345;
            $locId = $params['location_ids'] ?? 'loc_101';
            $mapping = ProductMarketplaceMapping::where('shopify_inventory_item_id', (string) $itemId)->first();
            $qty = $mapping && $mapping->quantity !== null ? (int) $mapping->quantity : 20;
            return Http::response(['inventory_levels' => [['inventory_item_id' => $itemId, 'location_id' => $locId, 'available' => $qty]]], 200);
        },
        '*products.json*'             => Http::response(['products' => []], 200),
        '*'                           => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

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
            $table->string('store_status')->default('active');
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
        });
    }

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->unique();
            $table->unsignedBigInteger('shop_id')->index();
            $table->unsignedBigInteger('mapping_id')->nullable()->index();
            $table->string('shopify_inventory_item_id');
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('desired_quantity');
            $table->integer('baseline_quantity')->nullable();
            $table->unsignedBigInteger('expected_inventory_version')->default(1);
            $table->string('source')->default('manual_ui');
            $table->string('status')->default('pending');
            $table->string('stage')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(4);
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status'], 'idx_shop_status');
            $table->index(['shop_id', 'shopify_inventory_item_id', 'status'], 'idx_shop_item_status');
            $table->index(['shop_id', 'amazon_sku', 'status'], 'idx_shop_sku_status');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index(['status', 'processing_started_at'], 'idx_status_processing');
            $table->index(['status', 'last_dispatched_at'], 'idx_status_dispatched');
        });
    }

    if (Schema::hasTable('product_marketplace_mappings') && !Schema::hasColumn('product_marketplace_mappings', 'inventory_version')) {
        Schema::table('product_marketplace_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_version')->default(1);
        });
    }

    if (Schema::hasTable('inventory_sync_operations')) {
        if (!Schema::hasColumn('inventory_sync_operations', 'baseline_quantity')) {
            Schema::table('inventory_sync_operations', function (Blueprint $table) {
                $table->integer('baseline_quantity')->nullable();
            });
        }
        if (!Schema::hasColumn('inventory_sync_operations', 'expected_inventory_version')) {
            Schema::table('inventory_sync_operations', function (Blueprint $table) {
                $table->unsignedBigInteger('expected_inventory_version')->default(1);
            });
        }
    }

    Shop::query()->truncate();
    ProductMarketplaceMapping::query()->truncate();
    InventorySyncOperation::query()->truncate();
});

if (!function_exists('createDurableTestShop')) {
    function createDurableTestShop(array $attributes = []): Shop
    {
        return Shop::create(array_merge([
            'shop'                    => 'test-durable-store.myshopify.com',
            'shop_name'               => 'Test Durable Store',
            'email'                   => 'owner@example.com',
            'access_token'            => 'shp_token_123',
            'access_token_expires_at' => now()->addDays(30),
            'is_active'               => 1,
            'store_status'            => 'active',
            'shopify_locations'       => [
                ['id' => 'loc_101', 'name' => 'Main Warehouse'],
                ['id' => 'loc_202', 'name' => 'Secondary Location'],
            ],
            'selected_location_index' => 0,
            'amazon_seller_id'        => 'AMZN_SELLER_1',
            'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
        ], $attributes));
    }
}


// 1. User request creates durable operation before external API call
test('1. user request creates durable operation in database', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $controller = app(InventoryMappingController::class);

    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_999',
        'quantity'          => 25,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($request);

    expect($response->getStatusCode())->toBe(200);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('pending')
        ->and($data['operation_id'])->not->toBeNull();

    $op = InventorySyncOperation::find($data['operation_id']);
    expect($op)->not->toBeNull()
        ->and($op->shop_id)->toBe($shop->id)
        ->and($op->shopify_inventory_item_id)->toBe('item_999')
        ->and($op->desired_quantity)->toBe(25)
        ->and($op->status)->toBe('pending');
});

// 2. Controller does NOT synchronously call Shopify
test('2. controller does not synchronously call Shopify API', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $controller = app(InventoryMappingController::class);

    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_999',
        'quantity'          => 25,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $controller->updateShopifyInventory($request);

    // Assert no HTTP call was made to Shopify inventory_levels/set.json
    Http::assertNotSent(function ($httpRequest) {
        return str_contains($httpRequest->url(), 'inventory_levels/set.json');
    });
});

// 3. Controller does NOT synchronously call Amazon
test('3. controller does not synchronously call Amazon API', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_999',
        'amazon_sku'                => 'SKU-999',
        'quantity'                  => '10',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');
    $this->app->instance(AmazonService::class, $mockAmazon);

    $controller = app(InventoryMappingController::class);

    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_999',
        'quantity'          => 25,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $controller->updateShopifyInventory($request);

    Queue::assertPushed(ProcessInventoryUpdateJob::class);
});

// 4. Desired quantity is stored exactly
test('4. desired quantity is stored exactly in the outbox operation', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $controller = app(InventoryMappingController::class);

    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_exact',
        'quantity'          => 42,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($request);
    $opId = $response->getData(true)['operation_id'];

    $op = InventorySyncOperation::find($opId);
    expect($op->desired_quantity)->toBe(42);
});

// 5. Explicit quantity 0 is stored and processed correctly
test('5. explicit quantity 0 is stored and processed correctly', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $controller = app(InventoryMappingController::class);

    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_zero',
        'quantity'          => 0,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($request);
    $opId = $response->getData(true)['operation_id'];

    $op = InventorySyncOperation::find($opId);
    expect($op->desired_quantity)->toBe(0)
        ->and($op->status)->toBe('pending');
});

// 6. Pending operation survives request completion
test('6. pending operation survives in database independently', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $controller = app(InventoryMappingController::class);

    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_survive',
        'quantity'          => 15,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $controller->updateShopifyInventory($request);

    $persisted = InventorySyncOperation::where('shopify_inventory_item_id', 'item_survive')->first();
    expect($persisted)->not->toBeNull()
        ->and($persisted->status)->toBe('pending');
});

// 7. Worker processes pending operation
test('7. worker processes pending operation to completion', function () {
    $shop = createDurableTestShop();
    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_worker',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 30,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('completed')
        ->and($operation->stage)->toBe('completed')
        ->and($operation->completed_at)->not->toBeNull();
});

// 8. Worker updates Shopify with desired quantity
test('8. worker updates Shopify with desired quantity and location', function () {
    $shop = createDurableTestShop();
    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_shopify_call',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 77,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    Http::assertSent(function ($httpRequest) {
        if (str_contains($httpRequest->url(), 'inventory_levels/set.json')) {
            $data = $httpRequest->data();
            return $data['location_id'] === 'loc_101'
                && $data['inventory_item_id'] === 'item_shopify_call'
                && $data['available'] === 77;
        }
        return false;
    });
});

// 9. Worker updates Amazon with desired quantity
test('9. worker updates Amazon with desired quantity when mapped', function () {
    $shop = createDurableTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_amazon_call',
        'amazon_sku'                => 'AMZN-SKU-77',
        'quantity'                  => '10',
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_amazon_call',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'AMZN-SKU-77',
        'desired_quantity'          => 50,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(
            Mockery::on(fn($s) => $s->id === $shop->id),
            'AMZN-SKU-77',
            50
        )
        ->andReturn(['submissionId' => 'sub_123']);


    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('awaiting_verification')
        ->and($operation->stage)->toBe('amazon_accepted');
});

// 10. Existing Amazon verification job is dispatched correctly (via AmazonService)
test('10. AmazonService handles Amazon sync and verification job integration', function () {
    $shop = createDurableTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_verify_test',
        'amazon_sku'                => 'VERIFY-SKU',
        'quantity'                  => '10',
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_verify_test',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'VERIFY-SKU',
        'desired_quantity'          => 20,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->andReturn(['submissionId' => 'sub_verif_999']);

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('awaiting_verification')
        ->and($operation->stage)->toBe('amazon_accepted');

    // Simulate VerifyAmazonInventoryQuantityJob running and confirming live listing
    $mockAmazonVerify = Mockery::mock(AmazonService::class);
    $mockAmazonVerify->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['quantity' => 20]
            ],
        ]);

    $verifyJob = new VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'VERIFY-SKU',
        expectedQuantity: 20,
        submissionId: 'sub_verif_999'
    );
    $verifyJob->handle($mockAmazonVerify);

    $operation->refresh();
    expect($operation->status)->toBe('completed')
        ->and($operation->stage)->toBe('completed')
        ->and($operation->completed_at)->not->toBeNull();
});

// 11. Shopify failure retries
test('11. Shopify transient failure throws exception to trigger queue retry', function () {
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        '*inventory_levels/set.json*' => Http::response(['error' => 'Rate limit exceeded', 'message' => 'Rate limit exceeded'], 429),
        '*'                           => Http::response([], 200),
    ]);

    $shop = createDurableTestShop();

    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_retry',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 12,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $job = new ProcessInventoryUpdateJob($operation->id);

    $exceptionThrown = false;
    try {
        $job->handle($mockAmazon);
    } catch (\Throwable $e) {
        $exceptionThrown = true;
        expect($e->getMessage())->toContain('Rate limit exceeded');
    }

    expect($exceptionThrown)->toBeTrue();

    $operation->refresh();
    expect($operation->stage)->toBe('pending')
        ->and($operation->last_error)->toContain('Rate limit exceeded');
});



// 12. Amazon failure retries
test('12. Amazon transient failure throws exception to trigger queue retry while Shopify remains completed', function () {
    $shop = createDurableTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_amz_retry',
        'amazon_sku'                => 'RETRY-SKU',
        'quantity'                  => '18',
        'inventory_version'         => 2,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_amz_retry',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'RETRY-SKU',
        'desired_quantity'          => 18,
        'status'                    => 'pending',
        'stage'                     => 'shopify_completed', // Shopify was already done!
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->andThrow(new \Exception('Amazon API 503 Service Unavailable'));

    $job = new ProcessInventoryUpdateJob($operation->id);

    try {
        $job->handle($mockAmazon);
        $this->fail('Expected exception for Amazon transient failure');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('503 Service Unavailable');
    }

    $operation->refresh();
    expect($operation->stage)->toBe('shopify_completed')
        ->and($operation->last_error)->toContain('503 Service Unavailable');

    // Make sure Shopify was not called again
    Http::assertNotSent(function ($req) {
        return str_contains($req->url(), 'inventory_levels/set.json');
    });
});

// 13. Terminal failure becomes failed
test('13. terminal failure marks operation as permanently failed', function () {
    $shop = createDurableTestShop();
    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_terminal',
        'shopify_location_id'       => null, // Missing location
        'desired_quantity'          => 10,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $shop->update(['shopify_locations' => []]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('failed')
        ->and($operation->last_error)->toContain('No Shopify inventory location');
});

// 14. Duplicate job execution is idempotent
test('14. duplicate job execution on completed operation exits immediately', function () {
    $shop = createDurableTestShop();
    $operation = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_idempotent',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 35,
        'status'                    => 'completed',
        'stage'                     => 'completed',
        'completed_at'              => now(),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    Http::assertNotSent(function ($req) {
        return str_contains($req->url(), 'inventory_levels/set.json');
    });
});

// 15. Older pending operation becomes superseded
test('15. older pending operation becomes superseded when new operation is created', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $controller = app(InventoryMappingController::class);

    // Op 1
    $req1 = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_supersede',
        'quantity'          => 10,
    ]);
    $req1->attributes->set('active_shop_model', $shop);
    $resp1 = $controller->updateShopifyInventory($req1);
    $opId1 = $resp1->getData(true)['operation_id'];

    $op1 = InventorySyncOperation::find($opId1);
    expect($op1->status)->toBe('pending');

    // Op 2 for same item
    $req2 = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_supersede',
        'quantity'          => 20,
    ]);
    $req2->attributes->set('active_shop_model', $shop);
    $resp2 = $controller->updateShopifyInventory($req2);
    $opId2 = $resp2->getData(true)['operation_id'];

    $op1->refresh();
    $op2 = InventorySyncOperation::find($opId2);

    expect($op1->status)->toBe('superseded')
        ->and($op2->status)->toBe('pending')
        ->and($op2->desired_quantity)->toBe(20);
});

// 16. Newest operation wins: 20 -> 25 -> 18 => only 18 executes
test('16. latest-wins: 20, 25, 18 sequentially submitted results in only 18 executing', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $controller = app(InventoryMappingController::class);

    // Submit 20
    $req1 = Request::create('/inventory/shopify/update', 'POST', ['inventory_item_id' => 'item_chain', 'quantity' => 20]);
    $req1->attributes->set('active_shop_model', $shop);
    $opId1 = $controller->updateShopifyInventory($req1)->getData(true)['operation_id'];

    // Submit 25
    $req2 = Request::create('/inventory/shopify/update', 'POST', ['inventory_item_id' => 'item_chain', 'quantity' => 25]);
    $req2->attributes->set('active_shop_model', $shop);
    $opId2 = $controller->updateShopifyInventory($req2)->getData(true)['operation_id'];

    // Submit 18
    $req3 = Request::create('/inventory/shopify/update', 'POST', ['inventory_item_id' => 'item_chain', 'quantity' => 18]);
    $req3->attributes->set('active_shop_model', $shop);
    $opId3 = $controller->updateShopifyInventory($req3)->getData(true)['operation_id'];

    $op1 = InventorySyncOperation::find($opId1);
    $op2 = InventorySyncOperation::find($opId2);
    $op3 = InventorySyncOperation::find($opId3);

    expect($op1->status)->toBe('superseded')
        ->and($op2->status)->toBe('superseded')
        ->and($op3->status)->toBe('pending');

    // Run job for Op 1 (should exit as superseded)
    $mockAmazon = Mockery::mock(AmazonService::class);
    $job1 = new ProcessInventoryUpdateJob($op1->id);
    $job1->handle($mockAmazon);
    $op1->refresh();
    expect($op1->status)->toBe('superseded');

    // Run job for Op 3 (should execute 18)
    $job3 = new ProcessInventoryUpdateJob($op3->id);
    $job3->handle($mockAmazon);
    $op3->refresh();
    expect($op3->status)->toBe('completed')
        ->and($op3->desired_quantity)->toBe(18);
});

// 17. Stale operation cannot overwrite newer operation
test('17. stale operation is superseded before external calls even if marked processing', function () {
    $shop = createDurableTestShop();

    $opOld = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_stale',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 10,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    // Newer operation exists
    $opNew = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_stale',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 40,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($opOld->id);
    $job->handle($mockAmazon);

    $opOld->refresh();
    expect($opOld->status)->toBe('superseded');

    Http::assertNotSent(function ($req) {
        return str_contains($req->url(), 'inventory_levels/set.json');
    });
});

// 18. Worker crash before Shopify is recoverable
test('18. worker crash before Shopify is recoverable by sweeper', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_crash_1',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 55,
        'status'                    => 'processing',
        'stage'                     => 'pending',
        'attempts'                  => 1,
    ]);
    InventorySyncOperation::where('id', $op->id)->update(['processing_started_at' => now()->subMinutes(5)]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    $op->refresh();
    expect($op->status)->toBe('pending');
    Queue::assertPushed(ProcessInventoryUpdateJob::class, fn($job) => $job->operationId === $op->id);
});

// 19. Worker crash after Shopify but before Amazon is recoverable
test('19. worker crash after Shopify but before Amazon resumes at Amazon stage without re-updating Shopify', function () {
    $shop = createDurableTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_crash_2',
        'amazon_sku'                => 'CRASH-SKU',
        'quantity'                  => '60',
        'inventory_version'         => 2,
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_crash_2',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'CRASH-SKU',
        'desired_quantity'          => 60,
        'status'                    => 'processing',
        'stage'                     => 'shopify_completed', // Shopify was completed before crash!
        'attempts'                  => 1,
    ]);
    InventorySyncOperation::where('id', $op->id)->update(['processing_started_at' => now()->subMinutes(5)]);

    // Sweeper resets to pending
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    $op->refresh();
    expect($op->status)->toBe('pending')
        ->and($op->stage)->toBe('shopify_completed');


    // Worker re-runs
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(
            Mockery::on(fn($s) => $s->id === $shop->id),
            'CRASH-SKU',
            60
        )
        ->andReturn(['submissionId' => 'sub_recovered']);


    $job = new ProcessInventoryUpdateJob($op->id);
    $job->handle($mockAmazon);

    $op->refresh();
    expect($op->status)->toBe('awaiting_verification')
        ->and($op->stage)->toBe('amazon_accepted');

    // Verify Shopify was NOT called again
    Http::assertNotSent(function ($req) {
        return str_contains($req->url(), 'inventory_levels/set.json');
    });
});

// 20. Pending operation with no dispatched queue job is recovered by sweeper
test('20. pending operation with lost queue dispatch is recovered by sweeper', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_lost_dispatch',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 19,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);
    InventorySyncOperation::where('id', $op->id)->update(['created_at' => now()->subMinutes(5)]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    Queue::assertPushed(ProcessInventoryUpdateJob::class, fn($job) => $job->operationId === $op->id);
});

// 21. Stale processing operation is detected and recovered
test('21. stale processing operation is detected by sweeper and requeued', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_stale_proc',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 80,
        'status'                    => 'processing',
        'stage'                     => 'pending',
        'attempts'                  => 1,
        'max_attempts'              => 4,
    ]);
    InventorySyncOperation::where('id', $op->id)->update(['processing_started_at' => now()->subMinutes(10)]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    $op->refresh();
    expect($op->status)->toBe('pending');
    Queue::assertPushed(ProcessInventoryUpdateJob::class);
});

// 22. Processing operation is not concurrently recovered by two workers (attempts limit check)
test('22. processing operation reaching max attempts is marked failed by sweeper', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_max_attempts',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 88,
        'status'                    => 'processing',
        'stage'                     => 'pending',
        'attempts'                  => 4,
        'max_attempts'              => 4,
    ]);
    InventorySyncOperation::where('id', $op->id)->update(['processing_started_at' => now()->subMinutes(10)]);

    Queue::fake([ProcessInventoryUpdateJob::class]);


    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    $op->refresh();
    expect($op->status)->toBe('failed')
        ->and($op->last_error)->toContain('Max recovery attempts reached');
    Queue::assertNotPushed(ProcessInventoryUpdateJob::class);
});

// 23. Shop isolation: Shop A operation cannot execute on Shop B SKU
test('23. shop isolation guarantees operations are strictly scoped by shop_id', function () {
    $shopA = createDurableTestShop(['shop' => 'shop-a.myshopify.com']);
    $shopB = createDurableTestShop(['shop' => 'shop-b.myshopify.com']);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'                   => $shopB->id,
        'shopify_inventory_item_id' => 'shared_item_id',
        'amazon_sku'                => 'SKU-SHOP-B',
        'quantity'                  => '10',
    ]);

    // Operation belongs to Shop A
    $opA = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shopA->id,
        'shopify_inventory_item_id' => 'shared_item_id',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 99,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    // Should NOT call Amazon with Shop B's mapping
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($opA->id);
    $job->handle($mockAmazon);

    $opA->refresh();
    $mappingB->refresh();

    expect($opA->status)->toBe('completed')
        ->and($mappingB->quantity)->toBe('10'); // Untouched!
});

// 24. Location is preserved: operation created for location 2 -> worker updates location 2
test('24. operation uses the exact location captured at creation time', function () {
    $shop = createDurableTestShop([
        'shopify_locations' => [
            ['id' => 'loc_primary', 'name' => 'Primary'],
            ['id' => 'loc_secondary', 'name' => 'Secondary'],
        ],
        'selected_location_index' => 1, // Currently on secondary
    ]);

    // Operation was created specifically for loc_secondary
    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_loc_preserve',
        'shopify_location_id'       => 'loc_secondary',
        'desired_quantity'          => 44,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    // Later, shop changes selected location index to 0
    $shop->update(['selected_location_index' => 0]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $job = new ProcessInventoryUpdateJob($op->id);
    $job->handle($mockAmazon);

    Http::assertSent(function ($req) {
        if (str_contains($req->url(), 'inventory_levels/set.json')) {
            return $req->data()['location_id'] === 'loc_secondary';
        }
        return false;
    });
});

// 25. Cache is invalidated but unconfirmed quantity is NOT written as actual
test('25. cache is invalidated upon queuing but unconfirmed quantity is not written to cache', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    Cache::put("shopify_inventory_{$shop->shop}_location_0", ['old_cache_data']);

    $controller = app(InventoryMappingController::class);
    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_cache_test',
        'quantity'          => 70,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $controller->updateShopifyInventory($request);

    // Cache was forgotten
    expect(Cache::has("shopify_inventory_{$shop->shop}_location_0"))->toBeFalse();
});

// 26. Mapping quantity is not falsely updated to desired quantity before Shopify succeeds
test('26. mapping quantity is not updated to desired quantity before Shopify succeeds', function () {
    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createDurableTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_mapping_test',
        'amazon_sku'                => 'MAP-TEST-SKU',
        'quantity'                  => '10', // Authoritative quantity
    ]);

    $controller = app(InventoryMappingController::class);
    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_mapping_test',
        'quantity'          => 95,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $controller->updateShopifyInventory($request);

    $mapping->refresh();
    // Must STILL be 10, not 95!
    expect($mapping->quantity)->toBe('10');
});

// =========================================================================
// SECTION 9 AUDIT REQUIREMENTS TESTS (1-14)
// =========================================================================

// AUDIT 1: Pending operation is not repeatedly dispatched every minute
test('AUDIT 1. pending operation is not repeatedly dispatched every minute during worker downtime', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_1',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 15,
        'status'                    => 'pending',
        'stage'                     => 'pending',
        'last_dispatched_at'        => now(),
    ]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    // Run sweeper immediately (0 minutes passed, timeout is 2 minutes)
    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    // Must NOT redispatch because last_dispatched_at is recent
    Queue::assertNotPushed(ProcessInventoryUpdateJob::class);
});

// AUDIT 2: Pending operation is recovered after lost afterCommit dispatch
test('AUDIT 2. pending operation is recovered after lost afterCommit dispatch', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_2',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 22,
        'status'                    => 'pending',
        'stage'                     => 'pending',
        'last_dispatched_at'        => null, // Lost dispatch before queue push
    ]);
    InventorySyncOperation::where('id', $op->id)->update(['created_at' => now()->subMinutes(5)]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    $op->refresh();
    expect($op->last_dispatched_at)->not->toBeNull();
    Queue::assertPushed(ProcessInventoryUpdateJob::class, fn($j) => $j->operationId === $op->id);
});

// AUDIT 3: Stale processing operation is recovered exactly once
test('AUDIT 3. stale processing operation is recovered exactly once', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_3',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 33,
        'status'                    => 'processing',
        'stage'                     => 'pending',
        'attempts'                  => 1,
    ]);
    InventorySyncOperation::where('id', $op->id)->update(['processing_started_at' => now()->subMinutes(10)]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    // First recovery run
    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    $op->refresh();
    expect($op->status)->toBe('pending')
        ->and($op->processing_started_at)->toBeNull()
        ->and($op->last_dispatched_at)->not->toBeNull();
    Queue::assertPushed(ProcessInventoryUpdateJob::class, 1);

    // Second immediate sweeper run must NOT dispatch duplicate job
    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    Queue::assertPushed(ProcessInventoryUpdateJob::class, 1); // Still 1!
});

// AUDIT 4: Live processing operation is not stolen by sweeper
test('AUDIT 4. live processing operation holding SKU lock is not stolen by sweeper', function () {
    $shop = createDurableTestShop();
    $sku = 'AMZ-LIVE-4';

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_4',
        'amazon_sku'                => $sku,
        'quantity'                  => '10',
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_audit_4',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => $sku,
        'desired_quantity'          => 45,
        'status'                    => 'processing',
        'stage'                     => 'pending',
        'attempts'                  => 1,
    ]);
    // Simulate operation timed out past 3 minutes
    InventorySyncOperation::where('id', $op->id)->update(['processing_started_at' => now()->subMinutes(5)]);

    // Simulate an active worker currently holding the SKU cache lock
    $lockKey = "inventory_sku_lock_{$shop->id}_{$sku}";
    $activeWorkerLock = Cache::lock($lockKey, 60);
    expect($activeWorkerLock->get())->toBeTrue();

    Queue::fake([ProcessInventoryUpdateJob::class]);

    // Run sweeper while worker holds the lock
    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    // Sweeper safely skips because lock is held by live worker
    $op->refresh();
    expect($op->status)->toBe('processing');
    Queue::assertNotPushed(ProcessInventoryUpdateJob::class);

    $activeWorkerLock->release();
});

// AUDIT 5: Duplicate job for same operation exits safely
test('AUDIT 5. duplicate job for same operation exits safely without duplicate external calls', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_5',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 50,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);

    // Job 1 runs and completes
    $job1 = new ProcessInventoryUpdateJob($op->id);
    $job1->handle($mockAmazon);

    $op->refresh();
    expect($op->status)->toBe('completed');

    // Job 2 runs for the same operation ID (simulating duplicate queue delivery)
    $job2 = new ProcessInventoryUpdateJob($op->id);
    $job2->handle($mockAmazon);

    // Only 1 HTTP call made to Shopify across both jobs
    Http::assertSentCount(1);
});

// AUDIT 6: Superseded queued job does no external work
test('AUDIT 6. superseded queued job does no external work', function () {
    $shop = createDurableTestShop();

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_6',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 10,
        'status'                    => 'superseded',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($op->id);
    $job->handle($mockAmazon);

    Http::assertNotSent(function ($req) {
        return str_contains($req->url(), 'inventory_levels/set.json');
    });
});

// AUDIT 7: Latest-wins remains correct after queue recovery
test('AUDIT 7. latest-wins remains correct after queue recovery', function () {
    $shop = createDurableTestShop();

    // Op A = 20 created 10 minutes ago
    $opA = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_7',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 20,
        'status'                    => 'pending',
        'stage'                     => 'pending',
        'last_dispatched_at'        => null,
    ]);
    InventorySyncOperation::where('id', $opA->id)->update(['created_at' => now()->subMinutes(10)]);

    // Sweeper runs and redispatches A
    Queue::fake([ProcessInventoryUpdateJob::class]);
    $this->artisan('inventory:recover-operations', ['--pending-timeout' => 2, '--processing-timeout' => 3]);
    Queue::assertPushed(ProcessInventoryUpdateJob::class, fn($j) => $j->operationId === $opA->id);

    // Op B = 30 created right after sweeper runs
    $opB = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_7',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 30,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    // Now Worker runs job for Op A
    $mockAmazon = Mockery::mock(AmazonService::class);
    $jobA = new ProcessInventoryUpdateJob($opA->id);
    $jobA->handle($mockAmazon);

    $opA->refresh();
    // Op A must detect Op B and become superseded without updating inventory to 20
    expect($opA->status)->toBe('superseded');

    // Worker runs job for Op B
    $jobB = new ProcessInventoryUpdateJob($opB->id);
    $jobB->handle($mockAmazon);

    $opB->refresh();
    expect($opB->status)->toBe('completed')
        ->and($opB->desired_quantity)->toBe(30);
});

// AUDIT 8: Amazon ACCEPTED does not falsely appear as final confirmed state
test('AUDIT 8. Amazon ACCEPTED leaves operation in awaiting_verification and mapping in accepted', function () {
    $shop = createDurableTestShop();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_8',
        'amazon_sku'                => 'SKU-AUDIT-8',
        'quantity'                  => '10',
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_audit_8',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'SKU-AUDIT-8',
        'desired_quantity'          => 25,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->andReturn(['submissionId' => 'SUB-AUDIT-8', 'status' => 'ACCEPTED']);

    $job = new ProcessInventoryUpdateJob($op->id);
    $job->handle($mockAmazon);

    $op->refresh();
    $mapping->refresh();

    // Must be awaiting_verification, NOT completed!
    expect($op->status)->toBe('awaiting_verification')
        ->and($op->stage)->toBe('amazon_accepted')
        ->and($op->completed_at)->toBeNull()
        ->and($mapping->submission_status)->toBe('accepted')
        ->and($mapping->sync_status)->toBe('success');
});

// AUDIT 9: Amazon verification transitions operation to final state correctly
test('AUDIT 9. Amazon verification transitions operation from awaiting_verification to completed or failed', function () {
    $shop = createDurableTestShop();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_9',
        'amazon_sku'                => 'SKU-AUDIT-9',
        'quantity'                  => '25',
        'submission_id'             => 'SUB-AUDIT-9',
        'submission_status'         => 'accepted',
        'sync_status'               => 'success',
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_audit_9',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'SKU-AUDIT-9',
        'desired_quantity'          => 25,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    // Case 1: Verification confirms live quantity
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [['quantity' => 25]],
        ]);

    $verifyJob = new VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'SKU-AUDIT-9',
        expectedQuantity: 25,
        submissionId: 'SUB-AUDIT-9'
    );
    $verifyJob->handle($mockAmazon);

    $op->refresh();
    $mapping->refresh();

    expect($op->status)->toBe('completed')
        ->and($op->stage)->toBe('completed')
        ->and($op->completed_at)->not->toBeNull()
        ->and($mapping->submission_status)->toBe('confirmed');
});

// AUDIT 10: Mapping quantity semantics are correct after Shopify success/Amazon pending
test('AUDIT 10. mapping.quantity represents established Shopify quantity after Stage 1', function () {
    $shop = createDurableTestShop();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_10',
        'amazon_sku'                => 'SKU-AUDIT-10',
        'quantity'                  => '5', // Old quantity
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_audit_10',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'SKU-AUDIT-10',
        'desired_quantity'          => 40,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    // Simulate Amazon failing on Stage 2
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->andThrow(new \Exception('Amazon 500'));

    $job = new ProcessInventoryUpdateJob($op->id);
    try {
        $job->handle($mockAmazon);
    } catch (\Throwable) {}

    $mapping->refresh();
    $op->refresh();

    // Mapping quantity is established Shopify quantity 40
    expect((int) $mapping->quantity)->toBe(40)
        ->and($op->stage)->toBe('shopify_completed')
        ->and($mapping->sync_status)->toBe('failed');
});

// AUDIT 11: Shopify success + Amazon failure is retryable without re-invoking Shopify
test('AUDIT 11. Shopify success + Amazon failure retries only Amazon stage', function () {
    $shop = createDurableTestShop();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_11',
        'amazon_sku'                => 'SKU-AUDIT-11',
        'quantity'                  => '65',
        'inventory_version'         => 2,
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_audit_11',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'SKU-AUDIT-11',
        'desired_quantity'          => 65,
        'status'                    => 'processing',
        'stage'                     => 'shopify_completed',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-AUDIT-11', 65)
        ->andReturn(['submissionId' => 'SUB-RETRY-OK']);

    $job = new ProcessInventoryUpdateJob($op->id);
    $job->handle($mockAmazon);

    $op->refresh();
    expect($op->status)->toBe('awaiting_verification')
        ->and($op->stage)->toBe('amazon_accepted');

    // Shopify set.json was NOT called because stage was shopify_completed
    Http::assertNotSent(function ($req) {
        return str_contains($req->url(), 'inventory_levels/set.json');
    });
});

// AUDIT 12: Amazon success + process crash converges safely
test('AUDIT 12. Amazon success + worker crash converges safely', function () {
    $shop = createDurableTestShop();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_12',
        'amazon_sku'                => 'SKU-AUDIT-12',
        'quantity'                  => '80',
    ]);

    // Simulating crash after Amazon received feed (stage amazon_accepted)
    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'item_audit_12',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'SKU-AUDIT-12',
        'desired_quantity'          => 80,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    // Verification job runs and confirms live quantity
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [['quantity' => 80]],
        ]);

    $verifyJob = new VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'SKU-AUDIT-12',
        expectedQuantity: 80,
        submissionId: null
    );
    $verifyJob->handle($mockAmazon);

    $op->refresh();
    $mapping->refresh();

    expect($op->status)->toBe('completed')
        ->and($op->stage)->toBe('completed')
        ->and($mapping->submission_status)->toBe('confirmed');
});

// AUDIT 13: Worker unavailable for 30 minutes does not create unlimited duplicate jobs
test('AUDIT 13. worker unavailable for 30 minutes does not create unlimited duplicate jobs', function () {
    $shop = createDurableTestShop();
    $baseTime = now()->startOfSecond();
    \Carbon\Carbon::setTestNow($baseTime);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_audit_13',
        'shopify_location_id'       => 'loc_101',
        'desired_quantity'          => 90,
        'status'                    => 'pending',
        'stage'                     => 'pending',
        'last_dispatched_at'        => $baseTime,
    ]);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    // Sweeper runs 30 times (once every minute) with pending-timeout = 10 minutes
    for ($minute = 1; $minute <= 30; $minute++) {
        // Advance time by 1 minute from base time
        \Carbon\Carbon::setTestNow($baseTime->copy()->addMinutes($minute));

        $this->artisan('inventory:recover-operations', [
            '--pending-timeout'    => 10,
            '--processing-timeout' => 15,
        ]);
    }

    // Over 30 minutes with 30 sweeper runs and worker offline, ShouldBeUnique + last_dispatched_at
    // guarantees the job is deduplicated and NOT pushed 30 times.
    expect(Queue::pushed(ProcessInventoryUpdateJob::class)->count())->toBeLessThanOrEqual(3)
        ->and(Queue::pushed(ProcessInventoryUpdateJob::class)->count())->toBeGreaterThanOrEqual(1);
    \Carbon\Carbon::setTestNow(); // Reset test now
});

// AUDIT 14: Multi-tenant operation isolation remains intact
test('AUDIT 14. multi-tenant operation isolation remains intact', function () {
    $shop1 = createDurableTestShop(['shop' => 'tenant-1.myshopify.com']);
    $shop2 = createDurableTestShop(['shop' => 'tenant-2.myshopify.com']);

    $mapping1 = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop1->id,
        'shopify_inventory_item_id' => 'item_shared',
        'amazon_sku'                => 'SHARED-SKU',
        'quantity'                  => '10',
    ]);

    $mapping2 = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop2->id,
        'shopify_inventory_item_id' => 'item_shared',
        'amazon_sku'                => 'SHARED-SKU',
        'quantity'                  => '100',
    ]);

    $op1 = InventorySyncOperation::create([
        'operation_uuid'            => (string) Str::uuid(),
        'shop_id'                   => $shop1->id,
        'mapping_id'                => $mapping1->id,
        'shopify_inventory_item_id' => 'item_shared',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'SHARED-SKU',
        'desired_quantity'          => 20,
        'status'                    => 'pending',
        'stage'                     => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop1->id), 'SHARED-SKU', 20)
        ->andReturn(['submissionId' => 'SUB-TENANT-1']);

    $job1 = new ProcessInventoryUpdateJob($op1->id);
    $job1->handle($mockAmazon);

    $mapping1->refresh();
    $mapping2->refresh();

    // Tenant 1 was updated
    expect((int) $mapping1->quantity)->toBe(20);
    // Tenant 2 was untouched
    expect((int) $mapping2->quantity)->toBe(100);
});

