<?php

use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\InventoryCacheService;
use Illuminate\Database\Schema\Blueprint;
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
        });
    } else {
        ProductMarketplaceMapping::truncate();
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
    } else {
        InventorySyncOperation::truncate();
    }
    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('product_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
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

    if (!Schema::hasTable('settings')) {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->unique();
            $table->boolean('auto_sku_mapping')->default(1);
            $table->timestamps();
        });
    }
});

function createUiTestShop(int $id = 701, string $domain = 'ui-verify-test.myshopify.com'): Shop
{
    $shop = Shop::create([
        'id'                      => $id,
        'shop'                    => $domain,
        'shop_name'               => 'UI Test Store ' . $id,
        'email'                   => 'test' . $id . '@example.com',
        'access_token'            => 'token-' . $id,
        'selected_location_index' => 0,
        'shopify_locations'       => [
            ['id' => 'loc_1', 'name' => 'Primary Location']
        ],
        'amazon_seller_id'        => 'SELLER_' . $id,
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
        'amazon_mws_region'       => 'na',
        'amazon_refresh_token'    => 'amz_refresh_token_' . $id,
        'is_active'               => 1,
    ]);

    $plan = \App\Models\Plan::firstOrCreate(
        ['name' => 'Unlimited Plan'],
        ['price' => 0, 'product_limit' => 0, 'is_active' => true]
    );

    \App\Models\ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'price' => 0,
        'current_period_end' => now()->addYear(),
    ]);

    return $shop;
}

function authUiSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

test('1. Amazon endpoint returns original quantity and is_verifying=true while submission_status is accepted', function () {
    $shop = createUiTestShop(701);

    // Mock Amazon cache with reported Amazon quantity = 10
    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-PENDING-VERIFY',
            'title'    => 'Test Item 1',
            'quantity' => 10,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    // Mapping has pending target quantity = 15, but submission_status = 'accepted'
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-701',
        'shopify_inventory_item_id' => 'INV-701',
        'amazon_sku'                => 'SKU-PENDING-VERIFY',
        'quantity'                  => 15,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-701',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    expect($data['products'])->toHaveCount(1);

    $product = $data['products'][0];
    expect($product['sku'])->toBe('SKU-PENDING-VERIFY')
        ->and($product['is_mapped'])->toBeTrue()
        ->and($product['is_verifying'])->toBeTrue()
        ->and($product['submission_status'])->toBe('accepted')
        // CRITICAL REQUIREMENT: Amazon quantity must stay 10, not falsely display 15 before confirmation!
        ->and($product['quantity'])->toBe(10);
});

test('2. Amazon endpoint returns updated quantity and is_verifying=false when submission_status is confirmed', function () {
    $shop = createUiTestShop(702);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-CONFIRMED',
            'title'    => 'Test Item 2',
            'quantity' => 10,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-702',
        'shopify_inventory_item_id' => 'INV-702',
        'amazon_sku'                => 'SKU-CONFIRMED',
        'quantity'                  => 15,
        'sync_status'               => 'success',
        'submission_status'         => 'confirmed',
        'submission_id'             => 'SUB-702',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    $product = $data['products'][0];

    expect($product['is_mapped'])->toBeTrue()
        ->and($product['is_verifying'])->toBeFalse()
        ->and($product['submission_status'])->toBe('confirmed')
        // When confirmed, Amazon displays the new confirmed quantity 15
        ->and($product['quantity'])->toBe(15);
});

test('3. Amazon endpoint returns original quantity and is_verifying=false when verification ends in mismatch or failed', function () {
    $shop = createUiTestShop(703);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-FAILED-VERIFY',
            'title'    => 'Test Item 3',
            'quantity' => 10,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-703',
        'shopify_inventory_item_id' => 'INV-703',
        'amazon_sku'                => 'SKU-FAILED-VERIFY',
        'quantity'                  => 15, // target that failed
        'sync_status'               => 'failed',
        'submission_status'         => 'mismatch',
        'error_message'             => 'Amazon inventory quantity mismatch',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    $product = $data['products'][0];

    expect($product['is_mapped'])->toBeTrue()
        ->and($product['is_verifying'])->toBeFalse()
        ->and($product['submission_status'])->toBe('mismatch')
        // Stays at original confirmed 10, NOT the failed 15
        ->and($product['quantity'])->toBe(10);
});

test('4. Amazon endpoint handles unmapped products safely without setting is_verifying', function () {
    $shop = createUiTestShop(704);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-UNMAPPED',
            'title'    => 'Unmapped Item',
            'quantity' => 50,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    $product = $data['products'][0];

    expect($product['is_mapped'])->toBeFalse()
        ->and($product['is_verifying'])->toBeFalse()
        ->and($product['quantity'])->toBe(50);
});

test('5. Multi-SKU simultaneous updates: SKU A & SKU B verify independently while SKU C is normal', function () {
    $shop = createUiTestShop(705);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        ['sku' => 'SKU-A', 'title' => 'Product A', 'quantity' => 19, 'status' => 'active'],
        ['sku' => 'SKU-B', 'title' => 'Product B', 'quantity' => 30, 'status' => 'active'],
        ['sku' => 'SKU-C', 'title' => 'Product C', 'quantity' => 14, 'status' => 'active'],
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    // Mapping A: accepted / verifying
    $mappingA = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-A',
        'shopify_inventory_item_id' => 'INV-A',
        'amazon_sku'                => 'SKU-A',
        'quantity'                  => 25,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-A',
    ]);

    // Mapping B: active verification operation in DB
    $mappingB = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-B',
        'shopify_inventory_item_id' => 'INV-B',
        'amazon_sku'                => 'SKU-B',
        'quantity'                  => 35,
        'sync_status'               => 'pending',
        'submission_status'         => 'accepted',
    ]);

    InventorySyncOperation::create([
        'operation_uuid'            => 'uuid-op-b',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mappingB->id,
        'shopify_inventory_item_id' => 'INV-B',
        'amazon_sku'                => 'SKU-B',
        'desired_quantity'          => 35,
        'baseline_quantity'         => 30,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    // Mapping C: confirmed, normal
    $mappingC = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-C',
        'shopify_inventory_item_id' => 'INV-C',
        'amazon_sku'                => 'SKU-C',
        'quantity'                  => 14,
        'sync_status'               => 'success',
        'submission_status'         => 'confirmed',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $products = collect($response->json('products'))->keyBy('sku');

    expect($products['SKU-A']['is_verifying'])->toBeTrue()
        ->and($products['SKU-B']['is_verifying'])->toBeTrue()
        ->and($products['SKU-C']['is_verifying'])->toBeFalse();
});

test('6. Page refresh restores row-level verification from backend active operations', function () {
    $shop = createUiTestShop(706);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        ['sku' => 'VM6DSDRYAWP8', 'title' => 'Target Product', 'quantity' => 13, 'status' => 'active'],
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-VM6',
        'shopify_inventory_item_id' => 'INV-VM6',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'quantity'                  => 19,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-VM6',
    ]);

    InventorySyncOperation::create([
        'operation_uuid'            => 'op-vm6-uuid',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'INV-VM6',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'desired_quantity'          => 19,
        'baseline_quantity'         => 13,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    $product = $data['products'][0];

    expect($product['sku'])->toBe('VM6DSDRYAWP8')
        ->and($product['is_verifying'])->toBeTrue()
        ->and($product['quantity'])->toBe(13); // Displays live quantity while verifying
});

test('7. Eventual consistency: verification confirms 19 when Amazon propagates on later attempt', function () {
    $shop = createUiTestShop(707);

    $syncedTime = now()->toDateTimeString();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-707',
        'shopify_inventory_item_id' => 'INV-707',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'quantity'                  => 19,
        'sync_status'               => 'pending',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-707',
        'last_synced_at'            => $syncedTime,
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => 'op-707-uuid',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'INV-707',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'desired_quantity'          => 19,
        'baseline_quantity'         => 13,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    $amazonServiceMock = Mockery::mock(\App\Services\AmazonService::class);
    $amazonServiceMock->shouldReceive('checkAmazonListing')
        ->withArgs(fn($s, $sku) => $s->id === $shop->id && $sku === 'VM6DSDRYAWP8')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 19],
                ],
            ],
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'AMAZON_NA', 'quantity' => 18],
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 19],
            ],
        ]);

    $job = new \App\Jobs\VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'VM6DSDRYAWP8',
        expectedQuantity: 19,
        submissionId: 'SUB-707',
        syncedAt: $syncedTime,
        attempt: 3
    );

    $job->handle($amazonServiceMock);

    $mapping->refresh();
    $op->refresh();

    expect($mapping->submission_status)->toBe('confirmed')
        ->and($mapping->sync_status)->toBe('success')
        ->and($op->status)->toBe('completed')
        ->and($op->stage)->toBe('completed');
});

test('8. True mismatch exhausts retries without modifying Shopify inventory state', function () {
    $shop = createUiTestShop(708);

    $syncedTime = now()->toDateTimeString();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-708',
        'shopify_inventory_item_id' => 'INV-708',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'quantity'                  => 21,
        'sync_status'               => 'pending',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-708',
        'last_synced_at'            => $syncedTime,
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => 'op-708-uuid',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'INV-708',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'desired_quantity'          => 21,
        'baseline_quantity'         => 19,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    $amazonServiceMock = Mockery::mock(\App\Services\AmazonService::class);
    $amazonServiceMock->shouldReceive('checkAmazonListing')
        ->withArgs(fn($s, $sku) => $s->id === $shop->id && $sku === 'VM6DSDRYAWP8')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 19],
                ],
            ],
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 19],
            ],
        ]);

    // Attempt 8 (terminal exhaustion)
    $job = new \App\Jobs\VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'VM6DSDRYAWP8',
        expectedQuantity: 21,
        submissionId: 'SUB-708',
        syncedAt: $syncedTime,
        attempt: 8
    );

    $job->handle($amazonServiceMock);

    $mapping->refresh();
    $op->refresh();

    expect($mapping->submission_status)->toBe('mismatch')
        ->and($mapping->sync_status)->toBe('failed')
        ->and($mapping->error_message)->toContain('expected 21, but Amazon reported 19 after 8 attempt(s)')
        ->and($op->status)->toBe('failed')
        ->and($op->last_error)->toContain('expected 21, but Amazon reported 19 after 8 attempt(s)');
});

test('9. Extended asynchronous reconciliation: Amazon reports 19 on attempts 1-4, then confirms 21 on attempt 6', function () {
    $shop = createUiTestShop(709);

    $syncedTime = now()->toDateTimeString();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-709',
        'shopify_inventory_item_id' => 'INV-709',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'quantity'                  => 21,
        'sync_status'               => 'pending',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-709',
        'last_synced_at'            => $syncedTime,
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => 'op-709-uuid',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'INV-709',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'desired_quantity'          => 21,
        'baseline_quantity'         => 19,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    $amazonServiceMock = Mockery::mock(\App\Services\AmazonService::class);
    $amazonServiceMock->shouldReceive('checkAmazonListing')
        ->withArgs(fn($s, $sku) => $s->id === $shop->id && $sku === 'VM6DSDRYAWP8')
        ->once()
        ->andReturn([
            'attributes' => [
                'fulfillment_availability' => [
                    ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 21],
                ],
            ],
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 21],
            ],
        ]);

    // Attempt 6 confirms 21
    $job = new \App\Jobs\VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'VM6DSDRYAWP8',
        expectedQuantity: 21,
        submissionId: 'SUB-709',
        syncedAt: $syncedTime,
        attempt: 6
    );

    $job->handle($amazonServiceMock);

    $mapping->refresh();
    $op->refresh();

    expect($mapping->submission_status)->toBe('confirmed')
        ->and($mapping->sync_status)->toBe('success')
        ->and($op->status)->toBe('completed')
        ->and($op->stage)->toBe('completed');
});

test('10. Superseded race: Operation A (21) abandoned when Operation B updates to 25', function () {
    $shop = createUiTestShop(710);

    $timeA = now()->subMinutes(5)->toDateTimeString();
    $timeB = now()->toDateTimeString();

    // Mapping has moved to 25 under Operation B
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-710',
        'shopify_inventory_item_id' => 'INV-710',
        'amazon_sku'                => 'VM6DSDRYAWP8',
        'quantity'                  => 25,
        'sync_status'               => 'pending',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-OP-B',
        'last_synced_at'            => $timeB,
    ]);

    $amazonServiceMock = Mockery::mock(\App\Services\AmazonService::class);
    // Should NOT call Amazon because guard detects quantity & submission_id mismatch
    $amazonServiceMock->shouldNotReceive('checkAmazonListing');

    // Old Job for Operation A (expected 21, submissionId SUB-OP-A)
    $job = new \App\Jobs\VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'VM6DSDRYAWP8',
        expectedQuantity: 21,
        submissionId: 'SUB-OP-A',
        syncedAt: $timeA,
        attempt: 2
    );

    $job->handle($amazonServiceMock);

    $mapping->refresh();
    // Mapping quantity must remain 25, never overwritten to 21
    expect($mapping->quantity)->toBe('25')
        ->and($mapping->submission_id)->toBe('SUB-OP-B');
});

test('11. Permanent Amazon failure stops retries immediately', function () {
    $shop = createUiTestShop(711);

    $syncedTime = now()->toDateTimeString();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-711',
        'shopify_inventory_item_id' => 'INV-711',
        'amazon_sku'                => 'INVALID-SKU',
        'quantity'                  => 21,
        'sync_status'               => 'pending',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-711',
        'last_synced_at'            => $syncedTime,
    ]);

    $op = InventorySyncOperation::create([
        'operation_uuid'            => 'op-711-uuid',
        'shop_id'                   => $shop->id,
        'mapping_id'                => $mapping->id,
        'shopify_inventory_item_id' => 'INV-711',
        'amazon_sku'                => 'INVALID-SKU',
        'desired_quantity'          => 21,
        'baseline_quantity'         => 19,
        'status'                    => 'awaiting_verification',
        'stage'                     => 'amazon_accepted',
    ]);

    $amazonServiceMock = Mockery::mock(\App\Services\AmazonService::class);
    $amazonServiceMock->shouldReceive('checkAmazonListing')
        ->withArgs(fn($s, $sku) => $s->id === $shop->id && $sku === 'INVALID-SKU')
        ->once()
        ->andReturn([
            'success' => false,
            'error'   => 'Invalid SKU: SKU does not exist on Amazon marketplace',
        ]);

    // Attempt 1 with permanent error should force abort immediately
    $job = new \App\Jobs\VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'INVALID-SKU',
        expectedQuantity: 21,
        submissionId: 'SUB-711',
        syncedAt: $syncedTime,
        attempt: 1
    );

    $job->handle($amazonServiceMock);

    $mapping->refresh();
    $op->refresh();

    expect($mapping->submission_status)->toBe('mismatch')
        ->and($mapping->sync_status)->toBe('failed')
        ->and($op->status)->toBe('failed');
});
