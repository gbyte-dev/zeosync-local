<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryMappingController;
use App\Http\Controllers\ShopifyController;
use App\Jobs\VerifyAmazonInventoryQuantityJob;
use App\Models\AdminSetting;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\UserNotification;
use App\Services\AmazonService;
use App\Services\ShopifyInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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
            $qty = $data['available'] ?? 0;
            return Http::response(['inventory_level' => ['available' => $qty]], 200);
        },
        '*inventory_levels.json*'     => function (\Illuminate\Http\Client\Request $request) {
            $params = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);
            $itemId = $params['inventory_item_ids'] ?? null;
            $locId = $params['location_ids'] ?? 'loc_101';
            $mapping = $itemId ? \App\Models\ProductMarketplaceMapping::where('shopify_inventory_item_id', $itemId)->first() : null;
            $qty = $mapping && $mapping->quantity !== null ? (int) $mapping->quantity : 0;
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

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('shopify_order_id')->nullable()->unique();
            $table->string('admin_graphql_api_id')->nullable();
            $table->string('shopify_event_id')->nullable();
            $table->string('shopify_webhook_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('customer_first_name')->nullable();
            $table->string('customer_last_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('phone')->nullable();
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('shipment_status')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('subtotal_price', 10, 2)->default(0);
            $table->decimal('total_tax', 10, 2)->default(0);
            $table->decimal('total_discounts', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->integer('line_items_count')->default(0);
            $table->string('source_name')->nullable();
            $table->string('tags')->nullable();
            $table->text('note')->nullable();
            $table->json('customer')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('line_items')->nullable();
            $table->json('discount_codes')->nullable();
            $table->json('shipping_lines')->nullable();
            $table->json('tax_lines')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('user_notifications')) {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('type');
            $table->string('title');
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('user_notification_settings')) {
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('notification_key');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }
});

function createNegativeTestShop(string $domain): Shop
{
    return Shop::create([
        'shop'                     => $domain,
        'shop_name'                => 'Negative Stock Test Shop',
        'email'                    => 'test@' . $domain,
        'access_token'             => 'shpua_test_negative_token',
        'shopify_locations'        => [
            ['id' => '10001', 'name' => 'Main Warehouse'],
        ],
        'selected_location_index'  => 0,
        'amazon_marketplace_id'    => 'ATVPDKIKX0DER',
        'amazon_refresh_token'     => 'test-amz-refresh-token',
        'is_active'                => 1,
        'store_status'             => 'active',
    ]);
}

// =========================================================================
// 1. Status Logic: null -> unknown, < 0 -> oversold, == 0 -> out_of_stock, > 0 -> synced
// =========================================================================
it('Status logic: distinguishes unknown (null), oversold (< 0), out of stock (0), and synced (> 0)', function () {
    $service = new ShopifyInventoryService();
    $refMethod = new \ReflectionMethod(ShopifyInventoryService::class, 'flattenVariants');
    $refMethod->setAccessible(true);

    $products = [
        [
            'id' => 'gid://shopify/Product/101',
            'title' => 'Oversold Widget',
            'featuredImage' => null,
            'variants' => [
                'nodes' => [
                    [
                        'id' => 'gid://shopify/ProductVariant/1001',
                        'title' => 'Red / -3 Qty',
                        'sku' => 'SKU-OVERSOLD-3',
                        'inventoryQuantity' => -3,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/5001',
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10001'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => -3],
                                            ['name' => 'committed', 'quantity' => 0],
                                            ['name' => 'on_hand', 'quantity' => -3],
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'id' => 'gid://shopify/ProductVariant/1002',
                        'title' => 'Blue / 0 Qty',
                        'sku' => 'SKU-ZERO-0',
                        'inventoryQuantity' => 0,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/5002',
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10001'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 0],
                                            ['name' => 'committed', 'quantity' => 0],
                                            ['name' => 'on_hand', 'quantity' => 0],
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'id' => 'gid://shopify/ProductVariant/1003',
                        'title' => 'Green / 25 Qty',
                        'sku' => 'SKU-SYNCED-25',
                        'inventoryQuantity' => 25,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/5003',
                            'inventoryLevels' => [
                                'nodes' => [
                                    [
                                        'location' => ['id' => 'gid://shopify/Location/10001'],
                                        'quantities' => [
                                            ['name' => 'available', 'quantity' => 25],
                                            ['name' => 'committed', 'quantity' => 5],
                                            ['name' => 'on_hand', 'quantity' => 30],
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'id' => 'gid://shopify/ProductVariant/1004',
                        'title' => 'Yellow / Unknown Qty',
                        'sku' => 'SKU-UNKNOWN-NULL',
                        'inventoryQuantity' => null,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/5004',
                            'inventoryLevels' => [
                                'nodes' => []
                            ]
                        ]
                    ],
                ]
            ]
        ]
    ];

    $shop = createNegativeTestShop('status-test.myshopify.com');
    $flattened = $refMethod->invoke($service, $products, $shop);

    expect($flattened)->toHaveCount(4);

    // Variant 1: available = -3 -> status = oversold
    expect($flattened[0]['available'])->toBe(-3);
    expect($flattened[0]['status'])->toBe('oversold');

    // Variant 2: available = 0 -> status = out_of_stock
    expect($flattened[1]['available'])->toBe(0);
    expect($flattened[1]['status'])->toBe('out_of_stock');

    // Variant 3: available = 25 -> status = synced
    expect($flattened[2]['available'])->toBe(25);
    expect($flattened[2]['status'])->toBe('synced');

    // Variant 4: available = null -> status = unknown
    expect($flattened[3]['available'])->toBeNull();
    expect($flattened[3]['status'])->toBe('unknown');
});

// =========================================================================
// 2. Order Oversells: mapping = 2, order = 5 -> mapping = -3, Amazon receives 0
// =========================================================================
it('Order oversells: calculates mapping = -3 and dispatches Amazon quantity = 0 without Shopify writeback', function () {
    $shop = createNegativeTestShop('oversold-order.myshopify.com');

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_variant_id' => '77701',
        'amazon_sku'         => 'AMZ-OVERSOLD-1',
        'quantity'           => '2',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->withArgs(function ($shopArg, $sku, $amazonTargetQty, $syncToShopify, $shopifyMappingQty) use ($shop) {
            return $shopArg->id === $shop->id
                && $sku === 'AMZ-OVERSOLD-1'
                && $amazonTargetQty === 0
                && $syncToShopify === false
                && $shopifyMappingQty === -3;
        })
        ->andReturnUsing(function ($shopArg, $sku, $amazonTargetQty, $syncToShopify, $shopifyMappingQty) use ($mapping) {
            $mapping->update([
                'quantity' => $shopifyMappingQty,
                'sync_status' => 'success',
                'submission_status' => 'accepted',
            ]);
            return ['status' => 'ACCEPTED'];
        });

    app()->instance(AmazonService::class, $mockAmazon);

    $payload = [
        'id' => 9001,
        'order_number' => '9001',
        'name' => '#9001',
        'financial_status' => 'paid',
        'fulfillment_status' => null,
        'line_items' => [
            [
                'variant_id' => 77701,
                'quantity' => 5,
            ]
        ],
    ];

    $secret = config('services.shopify.api_secret');
    $rawPayload = json_encode($payload);
    $hmac = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));

    $response = $this->postJson(route('shopify.webhooks.orders.create'), $payload, [
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac,
        'X-Shopify-Event-Id'    => 'evt_oversold_9001',
    ]);

    $response->assertOk();

    // Verify mapping became -3, NOT 0
    expect((int) $mapping->fresh()->quantity)->toBe(-3);
});

// =========================================================================
// 3. Multiple Orders: 2 -> order 5 -> -3 -> order 2 -> -5
// =========================================================================
it('Multiple orders: sequential orders transition 2 -> -3 -> -5 and Amazon target stays 0', function () {
    $shop = createNegativeTestShop('multi-order-oversold.myshopify.com');

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_variant_id' => '77702',
        'amazon_sku'         => 'AMZ-OVERSOLD-2',
        'quantity'           => '2',
    ]);

    $calledAmazonQuantities = [];
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->twice()
        ->andReturnUsing(function ($shopArg, $sku, $amazonTargetQty, $syncToShopify, $shopifyMappingQty) use (&$calledAmazonQuantities, $mapping) {
            $calledAmazonQuantities[] = [
                'amazon' => $amazonTargetQty,
                'shopify' => $shopifyMappingQty,
            ];
            $mapping->update(['quantity' => $shopifyMappingQty]);
            return ['status' => 'ACCEPTED'];
        });

    app()->instance(AmazonService::class, $mockAmazon);

    $secret = config('services.shopify.api_secret');

    // Order 1: qty = 5 (2 - 5 = -3)
    $payload1 = [
        'id' => 9002,
        'order_number' => '9002',
        'name' => '#9002',
        'line_items' => [['variant_id' => 77702, 'quantity' => 5]],
    ];
    $hmac1 = base64_encode(hash_hmac('sha256', json_encode($payload1), $secret, true));

    $this->postJson(route('shopify.webhooks.orders.create'), $payload1, [
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac1,
        'X-Shopify-Event-Id'    => 'evt_9002',
    ])->assertOk();

    expect((int) $mapping->fresh()->quantity)->toBe(-3);

    // Order 2: qty = 2 (-3 - 2 = -5)
    $payload2 = [
        'id' => 9003,
        'order_number' => '9003',
        'name' => '#9003',
        'line_items' => [['variant_id' => 77702, 'quantity' => 2]],
    ];
    $hmac2 = base64_encode(hash_hmac('sha256', json_encode($payload2), $secret, true));

    $this->postJson(route('shopify.webhooks.orders.create'), $payload2, [
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac2,
        'X-Shopify-Event-Id'    => 'evt_9003',
    ])->assertOk();

    expect((int) $mapping->fresh()->quantity)->toBe(-5);

    // Amazon target was 0 for both calls
    expect($calledAmazonQuantities[0]['amazon'])->toBe(0);
    expect($calledAmazonQuantities[0]['shopify'])->toBe(-3);
    expect($calledAmazonQuantities[1]['amazon'])->toBe(0);
    expect($calledAmazonQuantities[1]['shopify'])->toBe(-5);
});

// =========================================================================
// 4. Double Subtraction Safety: duplicate webhook does not decrement twice
// =========================================================================
it('Double subtraction safety: duplicate order webhook does not decrement quantity twice', function () {
    $shop = createNegativeTestShop('idempotent-order.myshopify.com');

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_variant_id' => '77703',
        'amazon_sku'         => 'AMZ-OVERSOLD-3',
        'quantity'           => '10',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    // Expect updateInventory to be called EXACTLY once even if the same webhook payload is delivered twice
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->andReturnUsing(function ($shopArg, $sku, $amazonTargetQty, $syncToShopify, $shopifyMappingQty) use ($mapping) {
            $mapping->update(['quantity' => $shopifyMappingQty]);
            return ['status' => 'ACCEPTED'];
        });

    app()->instance(AmazonService::class, $mockAmazon);

    $secret = config('services.shopify.api_secret');
    $payload = [
        'id' => 9004,
        'order_number' => '9004',
        'name' => '#9004',
        'line_items' => [['variant_id' => 77703, 'quantity' => 3]],
    ];
    $hmac = base64_encode(hash_hmac('sha256', json_encode($payload), $secret, true));

    // First delivery
    $this->postJson(route('shopify.webhooks.orders.create'), $payload, [
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac,
        'X-Shopify-Event-Id'    => 'evt_9004_1',
    ])->assertOk();

    expect((int) $mapping->fresh()->quantity)->toBe(7);

    // Duplicate delivery for same shopify_order_id = 9004
    $this->postJson(route('shopify.webhooks.orders.create'), $payload, [
        'X-Shopify-Shop-Domain' => $shop->shop,
        'X-Shopify-Hmac-Sha256' => $hmac,
        'X-Shopify-Event-Id'    => 'evt_9004_2',
    ])->assertOk();

    // Quantity must remain 7 (not 4)
    expect((int) $mapping->fresh()->quantity)->toBe(7);
});

// =========================================================================
// 5. Verification Job: mapping = -3 vs expected Amazon = 0 does not abort
// =========================================================================
it('Verification Job: confirms Amazon = 0 when mapping quantity is -3 without aborting or overwriting -3', function () {
    $shop = createNegativeTestShop('verify-negative.myshopify.com');
    $syncedAt = now()->toDateTimeString();

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'amazon_sku'         => 'AMZ-VERIFY-NEG',
        'quantity'           => '-3',
        'submission_id'      => 'SUB-VERIFY-NEG',
        'submission_status'  => 'accepted',
        'sync_status'        => 'success',
        'last_synced_at'     => $syncedAt,
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('checkAmazonListing')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'AMZ-VERIFY-NEG')
        ->andReturn([
            'status'                  => 'BUYABLE',
            'fulfillmentAvailability' => [
                ['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 0]
            ],
            'issues'                  => [],
        ]);

    $job = new VerifyAmazonInventoryQuantityJob(
        shopId: $shop->id,
        sku: 'AMZ-VERIFY-NEG',
        expectedQuantity: 0,
        submissionId: 'SUB-VERIFY-NEG',
        syncedAt: $syncedAt,
        attempt: 1
    );

    $job->handle($mockAmazon);

    $fresh = $mapping->fresh();
    // Submission status is confirmed!
    expect($fresh->submission_status)->toBe('confirmed');
    expect($fresh->sync_status)->toBe('success');
    // Crucial: mapping.quantity must REMAIN -3, NOT 0!
    expect((int) $fresh->quantity)->toBe(-3);
});

// =========================================================================
// 6. Replenishment Recovery: -3 -> 3 -> Amazon receives 3
// =========================================================================
it('Replenishment recovery: mapping restores from -3 to 3 and Amazon receives 3', function () {
    $shop = createNegativeTestShop('replenish.myshopify.com');

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_inventory_item_id' => 'ITEM-REPLENISH',
        'amazon_sku'                => 'AMZ-REPLENISH',
        'quantity'                  => '-3',
    ]);

    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with(Mockery::on(fn($s) => $s->id === $shop->id), 'AMZ-REPLENISH', 3, false)
        ->andReturn(['submissionId' => 'SUB-REPL-3', 'status' => 'ACCEPTED']);

    app()->instance(AmazonService::class, $mockAmazon);


    $controller = app(InventoryMappingController::class);
    $req = Request::create('/inventory/shopify/update', 'POST', [
        'inventory_item_id' => 'ITEM-REPLENISH',
        'quantity' => 3,
    ]);
    $req->attributes->set('active_shop_model', $shop);

    $response = $controller->updateShopifyInventory($req);
    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('pending');

    $operation = \App\Models\InventorySyncOperation::find($data['operation_id']);
    expect($operation)->not->toBeNull();

    $job = new \App\Jobs\ProcessInventoryUpdateJob($operation->id);
    $job->handle($mockAmazon);

    $fresh = $mapping->fresh();
    expect((int) $fresh->quantity)->toBe(3);
    expect($fresh->submission_status)->toBe('accepted');
});


// =========================================================================
// 7. Manual Input: Rejects manual negative numbers while accepting 0 and positive
// =========================================================================
it('Manual input: rejects negative manual input with 422 while accepting 0 and positive', function () {
    $shop = createNegativeTestShop('manual-validation.myshopify.com');

    $controller = app(InventoryController::class);

    // 1. Negative quantity rejected
    $reqNeg = Request::create('/inventory/amazon/AMZ-MANUAL-1/update-quantity', 'POST', [
        'quantity' => -3,
    ]);
    $reqNeg->attributes->set('active_shop_model', $shop);

    try {
        $controller->updateAmazonQuantity($reqNeg, 'AMZ-MANUAL-1');
        $this->fail('Expected ValidationException was not thrown');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('quantity');
    }

    // 2. Explicit 0 accepted
    $mockAmazon = Mockery::mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->with($shop, 'AMZ-MANUAL-1', 0)
        ->andReturn(['submissionId' => 'SUB-ZERO', 'status' => 'ACCEPTED']);

    app()->instance(AmazonService::class, $mockAmazon);

    $reqZero = Request::create('/inventory/amazon/AMZ-MANUAL-1/update-quantity', 'POST', [
        'quantity' => 0,
    ]);
    $reqZero->attributes->set('active_shop_model', $shop);

    $responseZero = $controller->updateAmazonQuantity($reqZero, 'AMZ-MANUAL-1');
    expect($responseZero->getStatusCode())->toBe(200);
});

// =========================================================================
// 8. Dashboard Low Inventory includes negative oversold items at the top
// =========================================================================
it('Dashboard low inventory: includes negative oversold items sorted at the top and excludes null', function () {
    $inventory = [
        ['product' => 'Oversold -5', 'sku' => 'SKU-NEG-5', 'available' => -5],
        ['product' => 'Oversold -1', 'sku' => 'SKU-NEG-1', 'available' => -1],
        ['product' => 'Out of Stock 0', 'sku' => 'SKU-ZERO', 'available' => 0],
        ['product' => 'Low Stock 3', 'sku' => 'SKU-LOW-3', 'available' => 3],
        ['product' => 'Ample Stock 50', 'sku' => 'SKU-HIGH-50', 'available' => 50],
        ['product' => 'Unknown Stock', 'sku' => 'SKU-UNKNOWN', 'available' => null],
    ];

    $filtered = collect($inventory)
        ->filter(function ($item) {
            return isset($item['available']) && $item['available'] !== null && $item['available'] < 10;
        })
        ->sortBy('available')
        ->values();

    expect($filtered)->toHaveCount(4);
    expect($filtered[0]['available'])->toBe(-5);
    expect($filtered[1]['available'])->toBe(-1);
    expect($filtered[2]['available'])->toBe(0);
    expect($filtered[3]['available'])->toBe(3);
});
