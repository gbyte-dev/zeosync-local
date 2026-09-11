<?php

use App\Console\Commands\RecoverInventorySyncOperationsCommand;
use App\Http\Controllers\InventoryMappingController;
use App\Jobs\ProcessInventoryUpdateJob;
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

function createStaleTestShop(array $attributes = []): Shop
{
    return Shop::create(array_merge([
        'shop'                    => 'test-stale-store.myshopify.com',
        'shop_name'               => 'Test Stale Store',
        'email'                   => 'stale-owner@example.com',
        'access_token'            => 'shp_token_stale',
        'access_token_expires_at' => now()->addDays(30),
        'is_active'               => 1,
        'store_status'            => 'active',
        'shopify_locations'       => [
            ['id' => 'loc_101', 'name' => 'Main Location'],
            ['id' => 'loc_202', 'name' => 'Secondary Location'],
        ],
        'selected_location_index' => 0,
        'amazon_seller_id'        => 'AMZN_SELLER_STALE',
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
    ], $attributes));
}

// 1. OLD MANUAL 20 + NEW SHOPIFY 18: fresh GET detects 18 and prevents writing 20
test('1. old manual 20 queued + shopify independently becomes 18: worker detects difference and does NOT write 20', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_123',
        'amazon_sku'                => 'SKU-123',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_123',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-123',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    // Live Shopify inventory is now 18 (e.g. customer purchased 2 units)
    Http::fake([
        '*inventory_levels.json*'     => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_123', 'location_id' => 'loc_101', 'available' => 18]]], 200),
        '*inventory_levels/set.json*' => Http::response(['inventory_level' => ['available' => 20]], 200),
        '*'                           => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    // Stale external state detected
    expect($operation->status)->toBe('stale_external_state')
        ->and($operation->last_error)->toContain('Shopify inventory changed externally from baseline 20 to 18');

    // Local mapping is refreshed to authoritative live quantity (18), NOT overwritten with desired 20
    expect((int) $mapping->quantity)->toBe(18)
        ->and((int) $mapping->inventory_version)->toBe(2);

    // Assert that Shopify SET was NEVER called
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json');
    });
});

// 2. Delayed webhook scenario: local version unchanged (V1 == V1), but fresh Shopify GET detects 18
test('2. delayed webhook scenario: local version unchanged but fresh Shopify GET detects 18', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_456',
        'amazon_sku'                => 'SKU-456',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_456',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-456',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_456', 'location_id' => 'loc_101', 'available' => 18]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('stale_external_state');
});

// 3. Local version changed before worker: worker aborts without Shopify/Amazon call
test('3. local inventory_version changed before worker: worker aborts before calling Shopify or Amazon', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_789',
        'amazon_sku'                => 'SKU-789',
        'quantity'                  => '18',
        'inventory_version'         => 2, // Version advanced locally by a webhook
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_789',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-789',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1, // Expected V1, but DB is now V2
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('stale_external_state')
        ->and($operation->last_error)->toContain('Stale local inventory version: current=2, expected=1');

    // No external Shopify API calls made
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels');
    });
});

// 4. Intentional manual override: baseline 18, desired 20, live 18 -> succeeds
test('4. intentional manual override: baseline 18, desired 20, live 18 succeeds', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_override',
        'amazon_sku'                => 'SKU-OVERRIDE',
        'quantity'                  => '18',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_override',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-OVERRIDE',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 18, // Merchant saw 18 and intentionally submitted 20
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    $liveStock = 18;
    Http::fake([
        '*inventory_levels/set.json*' => function ($request) use (&$liveStock) {
            $liveStock = $request['available'] ?? 20;
            return Http::response(['inventory_level' => ['available' => $liveStock]], 200);
        },
        '*inventory_levels.json*'     => function ($request) use (&$liveStock) {
            return Http::response(['inventory_levels' => [['inventory_item_id' => 'item_override', 'location_id' => 'loc_101', 'available' => $liveStock]]], 200);
        },
        '*'                           => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-OVERRIDE', 20)
        ->andReturn(['submissionId' => 'SUB-OVERRIDE']);

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    expect($operation->status)->toBe('awaiting_verification')
        ->and((int) $mapping->quantity)->toBe(20)
        ->and((int) $mapping->inventory_version)->toBeGreaterThanOrEqual(2);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json')
            && $request['available'] === 20;
    });
});

// 5. Intentional override becomes stale: baseline 18, desired 20, live 16 -> rejected
test('5. intentional override becomes stale: baseline 18, desired 20, live 16 is rejected', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_override_stale',
        'amazon_sku'                => 'SKU-OVR-STALE',
        'quantity'                  => '18',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_override_stale',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-OVR-STALE',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 18,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    // Live Shopify is now 16 (another order happened before worker ran)
    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_override_stale', 'location_id' => 'loc_101', 'available' => 16]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    expect($operation->status)->toBe('stale_external_state')
        ->and((int) $mapping->quantity)->toBe(16);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json');
    });
});

// 6. Multiple newer orders: baseline 20, live 15 -> cannot restore 20
test('6. multiple newer orders: baseline 20, live 15 cannot restore 20', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_multi_order',
        'amazon_sku'                => 'SKU-MULTI',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_multi_order',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-MULTI',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_multi_order', 'location_id' => 'loc_101', 'available' => 15]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('stale_external_state');
});

// 7. Auto-sync changes local version -> old operation aborted
test('7. auto-sync changes local version: old queued operation is aborted via OCC', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_autosync',
        'amazon_sku'                => 'SKU-AUTOSYNC',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_autosync',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-AUTOSYNC',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    // Auto-sync runs in background and updates local mapping to 18 and version to 2
    $mapping->update([
        'quantity'          => '18',
        'inventory_version' => 2,
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('stale_external_state');
});

// 8. Amazon stage stale check: mapping changed between Stage 1 and Stage 2 -> stale Amazon quantity NOT sent
test('8. stage 2 amazon check: mapping changed between stage 1 and stage 2 prevents sending stale quantity to Amazon', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_stage2',
        'amazon_sku'                => 'SKU-STAGE2',
        'quantity'                  => '15', // Order reduced mapping during Stage 1
        'inventory_version'         => 3,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_stage2',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-STAGE2',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'processing',
        'stage'                      => 'shopify_completed', // Stage 1 finished previously
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('stale_external_state')
        ->and($operation->last_error)->toContain('Inventory state changed before Amazon sync');
});

// 9. Negative state: baseline 0, live -2 -> stale manual operation does not restore 0
test('9. negative state: baseline 0, live -2 does not restore 0', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_negative',
        'amazon_sku'                => 'SKU-NEG',
        'quantity'                  => '0',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_negative',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-NEG',
        'desired_quantity'           => 0,
        'baseline_quantity'          => 0,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    // Shopify became -2 because of an oversold order
    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_negative', 'location_id' => 'loc_101', 'available' => -2]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    expect($operation->status)->toBe('stale_external_state')
        ->and((int) $mapping->quantity)->toBe(-2);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json');
    });
});

// 10. Unknown state: live quantity unknown/null -> no destructive guessed update
test('10. unknown state: live quantity unknown or null causes safe rejection without guessing 0', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_unknown',
        'amazon_sku'                => 'SKU-UNK',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_unknown',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-UNK',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    // Shopify returns empty inventory levels (item not tracked / not found at location)
    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => []], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('failed')
        ->and($operation->last_error)->toContain('Live Shopify inventory level unknown or null');

    // Assert that Shopify SET was NOT called
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json');
    });
});

// 11. Correct location: operation for Location 2 checks Location 2, not Location 0/1
test('11. correct location: operation for Location 2 checks Location 2 inventory level', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_loc2',
        'amazon_sku'                => 'SKU-LOC2',
        'quantity'                  => '50',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_loc2',
        'shopify_location_id'        => 'loc_202', // Explicitly Location 2
        'amazon_sku'                 => 'SKU-LOC2',
        'desired_quantity'           => 50,
        'baseline_quantity'          => 50,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    Http::fake([
        '*inventory_levels.json*'     => Http::response(['inventory_levels' => [
            ['inventory_item_id' => 'item_loc2', 'location_id' => 'loc_101', 'available' => 10],
            ['inventory_item_id' => 'item_loc2', 'location_id' => 'loc_202', 'available' => 50],
        ]], 200),
        '*inventory_levels/set.json*' => Http::response(['inventory_level' => ['available' => 50]], 200),
        '*'                           => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')->once()->andReturn(['submissionId' => 'SUB-LOC2']);

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('awaiting_verification');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json')
            && $request['location_id'] === 'loc_202';
    });
});

// 12. Duplicate job: stale operation repeated causes no external writes
test('12. duplicate job: repeated execution of stale operation exits harmlessly without external writes', function () {
    $shop = createStaleTestShop();
    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'shopify_inventory_item_id'  => 'item_dup',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-DUP',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'stale_external_state',
        'stage'                      => 'pending',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json');
    });
});

// 13. Multi-tenant isolation
test('13. multi-tenant isolation: stale checks on Shop A never touch Shop B mappings', function () {
    $shopA = createStaleTestShop(['shop' => 'tenant-a.myshopify.com']);
    $shopB = createStaleTestShop(['shop' => 'tenant-b.myshopify.com']);

    $mappingA = ProductMarketplaceMapping::create([
        'shop_id'                   => $shopA->id,
        'shopify_inventory_item_id' => 'item_iso',
        'amazon_sku'                => 'SKU-ISO',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'                   => $shopB->id,
        'shopify_inventory_item_id' => 'item_iso',
        'amazon_sku'                => 'SKU-ISO',
        'quantity'                  => '99',
        'inventory_version'         => 1,
    ]);

    $opA = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shopA->id,
        'mapping_id'                 => $mappingA->id,
        'shopify_inventory_item_id'  => 'item_iso',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-ISO',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_iso', 'location_id' => 'loc_101', 'available' => 14]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $jobA = new ProcessInventoryUpdateJob($opA->id);
    $jobA->handle($mockAmazon);

    $mappingA->refresh();
    $mappingB->refresh();

    // Mapping A updated to live 14
    expect((int) $mappingA->quantity)->toBe(14);
    // Mapping B is completely untouched (still 99)
    expect((int) $mappingB->quantity)->toBe(99);
});

// 14. Controller captures baseline and expected version at request time
test('14. controller captures baseline_quantity and expected_inventory_version at request time', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_ctrl',
        'amazon_sku'                => 'SKU-CTRL',
        'quantity'                  => '42',
        'inventory_version'         => 3,
    ]);

    $controller = new InventoryMappingController();
    $request = Request::create('/inventory/update-shopify-inventory', 'POST', [
        'inventory_item_id' => 'item_ctrl',
        'quantity'          => '55',
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($request);
    $data = $response->getData(true);

    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('pending');

    $operation = InventorySyncOperation::find($data['operation_id']);
    expect($operation)->not->toBeNull()
        ->and($operation->desired_quantity)->toBe(55)
        ->and($operation->baseline_quantity)->toBe(42)
        ->and($operation->expected_inventory_version)->toBe(3);
});

// 15. Full latest-wins manual sequence: 20 -> 25 -> 18
test('15. full latest-wins manual sequence: 20 -> 25 -> 18 supersedes older pending operations', function () {
    Queue::fake();

    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_seq',
        'amazon_sku'                => 'SKU-SEQ',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $controller = new InventoryMappingController();

    // Request 1: 20
    $req1 = Request::create('/inventory/update-shopify-inventory', 'POST', ['inventory_item_id' => 'item_seq', 'quantity' => '20']);
    $req1->attributes->set('active_shop_model', $shop);
    $res1 = $controller->updateShopifyInventory($req1)->getData(true);

    // Request 2: 25
    $req2 = Request::create('/inventory/update-shopify-inventory', 'POST', ['inventory_item_id' => 'item_seq', 'quantity' => '25']);
    $req2->attributes->set('active_shop_model', $shop);
    $res2 = $controller->updateShopifyInventory($req2)->getData(true);

    // Request 3: 18
    $req3 = Request::create('/inventory/update-shopify-inventory', 'POST', ['inventory_item_id' => 'item_seq', 'quantity' => '18']);
    $req3->attributes->set('active_shop_model', $shop);
    $res3 = $controller->updateShopifyInventory($req3)->getData(true);

    $op1 = InventorySyncOperation::find($res1['operation_id']);
    $op2 = InventorySyncOperation::find($res2['operation_id']);
    $op3 = InventorySyncOperation::find($res3['operation_id']);

    expect($op1->status)->toBe('superseded')
        ->and($op2->status)->toBe('superseded')
        ->and($op3->status)->toBe('pending');
});

// 16. Recovery sweeper + stale Shopify state: recovered operation checks fresh Shopify GET and aborts if stale
test('16. recovery sweeper + stale Shopify state: recovered operation detects external change and does not write', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_rec',
        'amazon_sku'                => 'SKU-REC',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_rec',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-REC',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
        'created_at'                 => now()->subMinutes(15),
        'last_dispatched_at'         => now()->subMinutes(15),
    ]);

    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_rec', 'location_id' => 'loc_101', 'available' => 17]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    Queue::fake();

    // Sweeper discovers and redispatches
    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    Queue::assertPushed(ProcessInventoryUpdateJob::class, function ($job) use ($operation) {
        return $job->operationId === $operation->id;
    });

    // Now worker processes the recovered job
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $workerJob = new ProcessInventoryUpdateJob($operation->id);
    $workerJob->handle($mockAmazon);

    $operation->refresh();
    expect($operation->status)->toBe('stale_external_state');

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json');
    });
});

// =========================================================================
// STAGE 2 TARGETED AUDIT REGRESSION TESTS (A, B, C, D)
// =========================================================================

// Test A: Stage 2 delayed webhook race
test('17. Stage 2 delayed webhook race: Stage 1 establishes 20, fresh Shopify Stage 2 read returns 18 -> Amazon update is NOT called', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_stage2_race',
        'amazon_sku'                => 'SKU-STAGE2-RACE',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_stage2_race',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-STAGE2-RACE',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'processing',
        'stage'                      => 'shopify_completed', // Stage 1 already completed
    ]);

    // Live Shopify read before Stage 2 returns 18 (customer purchased 2 units right after Stage 1)
    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_stage2_race', 'location_id' => 'loc_101', 'available' => 18]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    expect($operation->status)->toBe('stale_external_state')
        ->and($operation->last_error)->toContain('Shopify inventory changed externally to 18 after Shopify stage before Amazon sync.')
        ->and((int) $mapping->quantity)->toBe(18)
        ->and($mapping->inventory_version)->toBe(2);
});

// Test B: Stage 2 oversold race
test('18. Stage 2 oversold race: Stage 1 establishes 0, fresh Shopify returns -2 -> Amazon is not called with stale 0 and mapping is -2', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_stage2_oversold',
        'amazon_sku'                => 'SKU-STAGE2-NEG',
        'quantity'                  => '0',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_stage2_oversold',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-STAGE2-NEG',
        'desired_quantity'           => 0,
        'baseline_quantity'          => 0,
        'expected_inventory_version' => 1,
        'status'                     => 'processing',
        'stage'                      => 'shopify_completed',
    ]);

    // Live Shopify dropped to -2
    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_stage2_oversold', 'location_id' => 'loc_101', 'available' => -2]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    expect($operation->status)->toBe('stale_external_state')
        ->and((int) $mapping->quantity)->toBe(-2)
        ->and($operation->last_error)->toContain('Shopify inventory changed externally to -2 after Shopify stage before Amazon sync.');
});

// Test C: Stage 2 clean path
test('19. Stage 2 clean path: Stage 1 establishes 20, fresh Shopify remains 20 -> Amazon receives 20 and reaches awaiting_verification', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_stage2_clean',
        'amazon_sku'                => 'SKU-STAGE2-CLEAN',
        'quantity'                  => '20',
        'inventory_version'         => 2,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_stage2_clean',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-STAGE2-CLEAN',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'processing',
        'stage'                      => 'shopify_completed',
    ]);

    // Live Shopify matches desired 20
    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_stage2_clean', 'location_id' => 'loc_101', 'available' => 20]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'SKU-STAGE2-CLEAN', 20)
        ->andReturn(['submissionId' => 'SUB-STAGE2-CLEAN', 'status' => 'ACCEPTED']);

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    expect($operation->status)->toBe('awaiting_verification')
        ->and($operation->stage)->toBe('amazon_accepted')
        ->and($mapping->sync_status)->toBe('success')
        ->and($mapping->submission_status)->toBe('accepted')
        ->and($mapping->submission_id)->toBe('SUB-STAGE2-CLEAN');
});

// Test D: Delayed orders/create webhook after stale manual operation
test('20. Delayed orders/create webhook after stale manual operation cannot overwrite newer Shopify quantity', function () {
    $shop = createStaleTestShop();
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_webhook_race',
        'shopify_variant_id'        => 'var_webhook_race',
        'amazon_sku'                => 'SKU-WEBHOOK-RACE',
        'quantity'                  => '20',
        'inventory_version'         => 1,
    ]);

    $operation = InventorySyncOperation::create([
        'operation_uuid'             => (string) Str::uuid(),
        'shop_id'                    => $shop->id,
        'mapping_id'                 => $mapping->id,
        'shopify_inventory_item_id'  => 'item_webhook_race',
        'shopify_location_id'        => 'loc_101',
        'amazon_sku'                 => 'SKU-WEBHOOK-RACE',
        'desired_quantity'           => 20,
        'baseline_quantity'          => 20,
        'expected_inventory_version' => 1,
        'status'                     => 'pending',
        'stage'                      => 'pending',
    ]);

    // Live Shopify is already 18
    Http::fake([
        '*inventory_levels.json*' => Http::response(['inventory_levels' => [['inventory_item_id' => 'item_webhook_race', 'location_id' => 'loc_101', 'available' => 18]]], 200),
        '*'                       => Http::response(['access_token' => 'dummy'], 200),
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');

    $job = new ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $operation->refresh();
    $mapping->refresh();

    expect($operation->status)->toBe('stale_external_state')
        ->and((int) $mapping->quantity)->toBe(18)
        ->and($mapping->inventory_version)->toBe(2);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'inventory_levels/set.json');
    });
});

