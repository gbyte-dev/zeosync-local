<?php

namespace Tests\Feature;

use App\Http\Controllers\InventoryMappingController;
use App\Jobs\ProcessInventoryUpdateJob;
use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;

beforeEach(function () {
    config([
        'queue.default'               => 'sync',
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*graphql.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            $query = $data['query'] ?? '';
            $vars = $data['variables'] ?? [];

            if (str_contains($query, 'locations(') || str_contains($query, 'GetLocations')) {
                return Http::response([
                    'data' => [
                        'locations' => [
                            'nodes' => [
                                ['id' => 'gid://shopify/Location/loc_101', 'legacyResourceId' => 'loc_101', 'name' => 'Primary Location', 'isActive' => true],
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'inventorySetQuantities') || str_contains($query, 'InventorySetQuantities')) {
                $quantities = $vars['input']['quantities'] ?? [];
                $qty = $quantities[0]['quantity'] ?? 50;
                $itemGid = $quantities[0]['inventoryItemId'] ?? 'gid://shopify/InventoryItem/12345';
                $locGid = $quantities[0]['locationId'] ?? 'gid://shopify/Location/loc_101';
                $rawItem = str_contains((string) $itemGid, 'gid://shopify/InventoryItem/') ? substr((string) $itemGid, strrpos((string) $itemGid, '/') + 1) : (string) $itemGid;
                $rawLoc = str_contains((string) $locGid, 'gid://shopify/Location/') ? substr((string) $locGid, strrpos((string) $locGid, '/') + 1) : (string) $locGid;
                return Http::response([
                    'data' => [
                        'inventorySetQuantities' => [
                            'inventoryAdjustmentGroup' => [
                                'reason' => 'cycle_count_available',
                                'changes' => [
                                    [
                                        'name' => 'available',
                                        'delta' => 0,
                                        'quantityAfterChange' => (int) $qty,
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

                return Http::response([
                    'data' => [
                        'inventoryItem' => [
                            'id' => $id,
                            'legacyResourceId' => $rawId,
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'id' => "gid://shopify/InventoryLevel/{$rawId}?location_id=loc_101",
                                        'location' => [
                                            'id' => 'gid://shopify/Location/loc_101',
                                            'legacyResourceId' => 'loc_101',
                                            'name' => 'Primary Location',
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

            return Http::response(['data' => []], 200);
        },
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

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
        });
    }

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->nullable()->unique();
            $table->unsignedBigInteger('shop_id')->index();
            $table->unsignedBigInteger('webhook_event_id')->nullable();
            $table->unsignedBigInteger('mapping_id')->nullable()->index();
            $table->string('source_key')->nullable()->unique();
            $table->string('sku')->nullable()->default('');
            $table->string('amazon_sku')->nullable();
            $table->string('source')->default('manual_ui');
            $table->string('source_state')->nullable()->default('pending');
            $table->string('inventory_item_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('location_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('marketplace_id')->nullable();
            $table->integer('desired_quantity')->default(0);
            $table->unsignedInteger('requested_quantity')->nullable();
            $table->unsignedInteger('observed_quantity')->nullable();
            $table->integer('baseline_quantity')->nullable();
            $table->integer('delta')->default(0);
            $table->unsignedBigInteger('expected_inventory_version')->default(1);
            $table->string('status')->default('pending');
            $table->string('stage')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(4);
            $table->text('error')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('jobs')) {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    Shop::truncate();
    ProductMarketplaceMapping::truncate();
    InventorySyncOperation::truncate();
    DB::table('jobs')->truncate();
});

function createQueueTestShop(int $id = 901): Shop
{
    return Shop::create([
        'id'                      => $id,
        'shop'                    => "queue-shop-{$id}.myshopify.com",
        'access_token'            => "token-{$id}",
        'selected_location_index' => 0,
        'shopify_locations'       => [
            ['id' => 'loc_101', 'name' => 'Primary Location']
        ],
        'amazon_seller_id'        => "SELLER_{$id}",
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
        'amazon_mws_region'       => 'na',
        'is_active'               => 1,
    ]);
}

it('1. ProcessInventoryUpdateJob defaults connection to database and queue to default', function () {
    $job = new ProcessInventoryUpdateJob(123);

    expect($job->connection)->toBe('database')
        ->and($job->queue)->toBe('default');
});

it('2. updateShopifyInventory dispatches ProcessInventoryUpdateJob to database connection and default queue even when default queue driver is sync', function () {
    config(['queue.default' => 'sync']);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createQueueTestShop(902);
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_queue_1',
        'amazon_sku'                => 'SKU-QUEUE-1',
        'quantity'                  => '10',
        'inventory_version'         => 1,
    ]);

    $controller = app(InventoryMappingController::class);
    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_queue_1',
        'quantity'          => 50,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($request);

    expect($response->getStatusCode())->toBe(200);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('pending')
        ->and($data['message'])->toBe('Inventory update queued successfully.');

    Queue::assertPushed(ProcessInventoryUpdateJob::class, function ($job) use ($data) {
        return $job->operationId === $data['operation_id']
            && $job->connection === 'database'
            && $job->queue === 'default';
    });
});

it('3. recover-operations dispatches abandoned operations to database connection and default queue', function () {
    config(['queue.default' => 'sync']);

    Queue::fake([ProcessInventoryUpdateJob::class]);

    $shop = createQueueTestShop(903);
    $op = InventorySyncOperation::create([
        'operation_uuid'            => 'uuid-abandoned-1',
        'source_key'                => 'manual:abandoned-1',
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_abandoned',
        'shopify_location_id'       => 'loc_101',
        'amazon_sku'                => 'SKU-ABANDONED',
        'desired_quantity'          => 30,
        'baseline_quantity'         => 10,
        'status'                    => 'pending',
        'stage'                     => 'pending',
        'attempts'                  => 0,
        'created_at'                => now()->subMinutes(10),
        'last_dispatched_at'        => now()->subMinutes(10),
    ]);

    $this->artisan('inventory:recover-operations', [
        '--pending-timeout'    => 2,
        '--processing-timeout' => 3,
    ])->assertSuccessful();

    Queue::assertPushed(ProcessInventoryUpdateJob::class, function ($job) use ($op) {
        return $job->operationId === $op->id
            && $job->connection === 'database'
            && $job->queue === 'default';
    });
});

it('4. Real database queue insertion: dispatch pushes job into jobs table and worker execution completes operation', function () {
    $shop = createQueueTestShop(904);
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'item_queue_1',
        'amazon_sku'                => 'SKU-QUEUE-1',
        'quantity'                  => '10',
        'inventory_version'         => 1,
    ]);

    $controller = app(InventoryMappingController::class);
    $request = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'item_queue_1',
        'quantity'          => 50,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($request);
    $data = $response->getData(true);
    $operationId = $data['operation_id'];

    // Verify job was persisted into the `jobs` database table
    $queuedJob = DB::table('jobs')->where('queue', 'default')->first();
    expect($queuedJob)->not->toBeNull()
        ->and($queuedJob->queue)->toBe('default');

    $payload = json_decode($queuedJob->payload, true);
    expect($payload['displayName'])->toBe(ProcessInventoryUpdateJob::class);

    // Simulate worker processing the job
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(
            Mockery::on(fn($s) => $s->id === $shop->id),
            'SKU-QUEUE-1',
            50,
            false,
            Mockery::any(),
            false
        )
        ->andReturn(['status' => 'ACCEPTED', 'submission_id' => 'sub_904']);

    $jobInstance = new ProcessInventoryUpdateJob($operationId);
    $jobInstance->handle($mockAmazon);

    $op = InventorySyncOperation::find($operationId);
    expect($op->status)->toBe('awaiting_verification')
        ->and($op->stage)->toBe('amazon_accepted');
});
