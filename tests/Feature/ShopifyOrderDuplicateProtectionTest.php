<?php

use App\Models\AdminSetting;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\UserNotification;
use App\Models\UserNotificationSetting;
use App\Services\AmazonService;
use App\Services\ShopifyOrderSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'services.shopify.app_url'    => 'https://test-zeosync.com',
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
    AdminSetting::forget('SHOPIFY_APP_URL');

    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_KEY'], ['option_value' => 'test-api-key']);
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_SECRET'], ['option_value' => 'test-api-secret']);
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_APP_URL'], ['option_value' => 'https://test-zeosync.com']);

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('domain')->nullable();
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
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('shopify_order_id')->nullable();
            $table->unique(['shop_id', 'shopify_order_id']);
            $table->string('admin_graphql_api_id')->nullable();
            $table->string('shopify_event_id')->nullable()->index();
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
            $table->string('shipment_status')->nullable()->index();
            $table->string('currency', 10)->nullable();
            $table->decimal('subtotal_price', 10, 2)->default(0);
            $table->decimal('total_tax', 10, 2)->default(0);
            $table->decimal('total_discounts', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->integer('line_items_count')->default(0);
            $table->string('source_name')->nullable();
            $table->text('tags')->nullable();
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

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('quantity')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('user_notifications')) {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('notification_key')->default('general');
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('user_notification_settings')) {
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->boolean('app_enabled')->default(true);
            $table->boolean('mail_enabled')->default(false);
            $table->timestamps();
        });
    }

    UserNotificationSetting::updateOrCreate(
        ['notification_key' => 'order_sync'],
        [
            'title' => 'Order Synchronization',
            'description' => 'Notifications for order updates',
            'app_enabled' => true,
            'mail_enabled' => false,
        ]
    );
});

test('1. New orders/create webhook creates exactly one row and deducts inventory once', function () {
    $shop = Shop::create([
        'shop'         => 'duplicate-test.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_variant_id' => '444101',
        'amazon_sku'         => 'SKU-TEST-1001',
        'quantity'           => '10',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('updateInventory')
        ->once()
        ->with(
            Mockery::on(fn($s) => $s->id === $shop->id),
            'SKU-TEST-1001',
            9,
            false,
            9
        )
        ->andReturn(['status' => 'SUCCESS']);

    $this->app->instance(AmazonService::class, $amazonMock);
    $this->app->instance(ShopifyOrderSyncService::class, new ShopifyOrderSyncService($amazonMock));

    $payload = [
        'id'                 => 1001,
        'order_number'       => 1001,
        'name'               => '#1001',
        'email'              => 'customer@example.com',
        'financial_status'   => 'paid',
        'fulfillment_status' => null,
        'total_price'        => '53.00',
        'line_items'         => [
            ['variant_id' => '444101', 'quantity' => 1, 'title' => 'Test Item'],
        ],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.create'), $shop, $payload, 'evt_create_1001');

    $response->assertStatus(200);
    expect(ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1001')->count())->toBe(1);
});

test('2. Same orders/create event retry returns HTTP 200 without duplicate row or second inventory deduction', function () {
    $shop = Shop::create([
        'shop'         => 'retry-test.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('updateInventory')->once()->andReturn(['status' => 'SUCCESS']);
    $this->app->instance(AmazonService::class, $amazonMock);
    $this->app->instance(ShopifyOrderSyncService::class, new ShopifyOrderSyncService($amazonMock));

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_variant_id' => '444102',
        'amazon_sku'         => 'SKU-TEST-1002',
        'quantity'           => '5',
    ]);

    $payload = [
        'id'                 => 1002,
        'order_number'       => 1002,
        'name'               => '#1002',
        'email'              => 'retry@example.com',
        'financial_status'   => 'paid',
        'line_items'         => [
            ['variant_id' => '444102', 'quantity' => 1],
        ],
    ];

    // Delivery 1
    sendShopifyWebhook($this, route('shopify.webhooks.orders.create'), $shop, $payload, 'evt_retry_1002')
        ->assertStatus(200);

    // Delivery 2 (Shopify retry with same event ID)
    $response2 = sendShopifyWebhook($this, route('shopify.webhooks.orders.create'), $shop, $payload, 'evt_retry_1002');

    $response2->assertStatus(200);
    expect(ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1002')->count())->toBe(1);
});

test('3. orders/create followed by orders/update with different event IDs maintains exactly one row', function () {
    $shop = Shop::create([
        'shop'         => 'create-update.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldReceive('updateInventory')->once()->andReturn(['status' => 'SUCCESS']);
    $this->app->instance(AmazonService::class, $amazonMock);
    $this->app->instance(ShopifyOrderSyncService::class, new ShopifyOrderSyncService($amazonMock));

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'shopify_variant_id' => '444103',
        'amazon_sku'         => 'SKU-TEST-1003',
        'quantity'           => '8',
    ]);

    $createPayload = [
        'id'                 => 1003,
        'order_number'       => 1003,
        'name'               => '#1003',
        'financial_status'   => 'pending',
        'fulfillment_status' => null,
        'line_items'         => [
            ['variant_id' => '444103', 'quantity' => 1],
        ],
    ];

    $updatePayload = array_merge($createPayload, [
        'financial_status'   => 'paid',
        'fulfillment_status' => 'fulfilled',
        'fulfillments'       => [
            ['status' => 'success', 'shipment_status' => 'in_transit'],
        ],
    ]);

    // 1. orders/create arrives
    sendShopifyWebhook($this, route('shopify.webhooks.orders.create'), $shop, $createPayload, 'evt_create_1003')
        ->assertStatus(200);

    // 2. orders/update arrives
    sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $updatePayload, 'evt_update_1003')
        ->assertStatus(200);

    $orders = ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1003')->get();
    expect($orders->count())->toBe(1);
    expect($orders->first()->financial_status)->toBe('paid');
    expect($orders->first()->fulfillment_status)->toBe('fulfilled');
    expect($orders->first()->shipment_status)->toBe('in_transit');
});

test('4. Concurrent create/update simulation: duplicate key race gracefully updates and keeps 1 row', function () {
    $shop = Shop::create([
        'shop'         => 'race-test.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $syncService = new ShopifyOrderSyncService($amazonMock);

    $payload = [
        'id'                 => 1004,
        'order_number'       => 1004,
        'name'               => '#1004',
        'financial_status'   => 'paid',
        'fulfillment_status' => 'unfulfilled',
    ];

    // Thread 1 syncs
    $res1 = $syncService->syncOrder($shop, $payload, 'create', 'evt_race_1', null);
    expect($res1['result'])->toBe('created');

    // Thread 2 syncs same order concurrently with updated status
    $updatedPayload = array_merge($payload, ['fulfillment_status' => 'fulfilled']);
    $res2 = $syncService->syncOrder($shop, $updatedPayload, 'update', 'evt_race_2', null);

    expect($res2['result'])->toBe('updated');
    expect(ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1004')->count())->toBe(1);
    expect(ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1004')->first()->fulfillment_status)->toBe('fulfilled');
});

test('5. Fulfillment status update changes existing row and sends notification', function () {
    $shop = Shop::create([
        'shop'         => 'fulfillment-update.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    ShopifyOrder::create([
        'shop_id'            => $shop->id,
        'shopify_order_id'   => '1005',
        'order_number'       => 1005,
        'name'               => '#1005',
        'fulfillment_status' => 'unfulfilled',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $amazonMock->shouldNotReceive('updateInventory');
    $this->app->instance(AmazonService::class, $amazonMock);
    $this->app->instance(ShopifyOrderSyncService::class, new ShopifyOrderSyncService($amazonMock));

    $payload = [
        'id'                 => 1005,
        'order_number'       => 1005,
        'name'               => '#1005',
        'fulfillment_status' => 'fulfilled',
    ];

    sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_fulfill_1005')
        ->assertStatus(200);

    $order = ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1005')->first();
    expect($order->fulfillment_status)->toBe('fulfilled');
    expect(UserNotification::where('shop_id', $shop->id)->where('title', 'Shopify Order Fulfilled')->count())->toBe(1);
});

test('6. Financial status update changes existing row', function () {
    $shop = Shop::create([
        'shop'         => 'financial-update.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    ShopifyOrder::create([
        'shop_id'          => $shop->id,
        'shopify_order_id' => '1006',
        'financial_status' => 'pending',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $syncService = new ShopifyOrderSyncService($amazonMock);

    $payload = [
        'id'               => 1006,
        'financial_status' => 'paid',
    ];

    $res = $syncService->syncOrder($shop, $payload, 'update', 'evt_fin_1006');
    expect($res['result'])->toBe('updated');
    expect(ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1006')->first()->financial_status)->toBe('paid');
});

test('7. Cancellation update changes existing row', function () {
    $shop = Shop::create([
        'shop'         => 'cancel-update.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    ShopifyOrder::create([
        'shop_id'          => $shop->id,
        'shopify_order_id' => '1007',
        'financial_status' => 'paid',
        'cancelled_at'     => null,
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $syncService = new ShopifyOrderSyncService($amazonMock);

    $payload = [
        'id'               => 1007,
        'financial_status' => 'voided',
        'cancelled_at'     => '2026-09-18T10:00:00Z',
    ];

    $res = $syncService->syncOrder($shop, $payload, 'update', 'evt_cancel_1007');
    expect($res['result'])->toBe('updated');
    $order = ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1007')->first();
    expect($order->cancelled_at)->not->toBeNull();
});

test('8. Shipment/tracking update changes shipment_status and sends delivered notification', function () {
    $shop = Shop::create([
        'shop'         => 'delivery-update.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    ShopifyOrder::create([
        'shop_id'            => $shop->id,
        'shopify_order_id'   => '1008',
        'order_number'       => 1008,
        'name'               => '#1008',
        'fulfillment_status' => 'fulfilled',
        'shipment_status'    => 'in_transit',
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $this->app->instance(AmazonService::class, $amazonMock);
    $this->app->instance(ShopifyOrderSyncService::class, new ShopifyOrderSyncService($amazonMock));

    $payload = [
        'id'                 => 1008,
        'order_number'       => 1008,
        'name'               => '#1008',
        'fulfillment_status' => 'fulfilled',
        'fulfillments'       => [
            ['status' => 'success', 'shipment_status' => 'delivered'],
        ],
    ];

    sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_deliv_1008')
        ->assertStatus(200);

    $order = ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1008')->first();
    expect($order->shipment_status)->toBe('delivered');
    expect(UserNotification::where('shop_id', $shop->id)->where('title', 'Shopify Order Delivered')->count())->toBe(1);
});

test('9. Identical meaningful payload returns unchanged without DB write overhead', function () {
    $shop = Shop::create([
        'shop'         => 'identical-test.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    $payload = [
        'id'                 => 1009,
        'order_number'       => 1009,
        'name'               => '#1009',
        'financial_status'   => 'paid',
        'fulfillment_status' => 'unfulfilled',
        'total_price'        => '45.00',
    ];

    $amazonMock = Mockery::mock(AmazonService::class);
    $syncService = new ShopifyOrderSyncService($amazonMock);

    $syncService->syncOrder($shop, $payload, 'create', 'evt_1');
    $res = $syncService->syncOrder($shop, $payload, 'update', 'evt_2');

    expect($res['result'])->toBe('unchanged');
    expect(ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1009')->count())->toBe(1);
});

test('10. Multi-shop isolation: same Shopify order ID in two shops creates two separate tenant rows', function () {
    $shopA = Shop::create([
        'shop'         => 'shop-a.myshopify.com',
        'access_token' => 'token_a',
        'is_active'    => 1,
    ]);

    $shopB = Shop::create([
        'shop'         => 'shop-b.myshopify.com',
        'access_token' => 'token_b',
        'is_active'    => 1,
    ]);

    $amazonMock = Mockery::mock(AmazonService::class);
    $syncService = new ShopifyOrderSyncService($amazonMock);

    $payload = [
        'id'           => 9999,
        'order_number' => 9999,
        'name'         => '#9999',
    ];

    $resA = $syncService->syncOrder($shopA, $payload, 'create', 'evt_a');
    $resB = $syncService->syncOrder($shopB, $payload, 'create', 'evt_b');

    expect($resA['result'])->toBe('created');
    expect($resB['result'])->toBe('created');
    expect(ShopifyOrder::where('shopify_order_id', '9999')->count())->toBe(2);
    expect(ShopifyOrder::where('shop_id', $shopA->id)->where('shopify_order_id', '9999')->count())->toBe(1);
    expect(ShopifyOrder::where('shop_id', $shopB->id)->where('shopify_order_id', '9999')->count())->toBe(1);
});

test('11. Delete webhook deletes by composite key shop_id and shopify_order_id', function () {
    $shop = Shop::create([
        'shop'         => 'delete-test.myshopify.com',
        'access_token' => 'shpat_test_123',
        'is_active'    => 1,
    ]);

    ShopifyOrder::create([
        'shop_id'          => $shop->id,
        'shopify_order_id' => '1010',
    ]);

    $payload = ['id' => 1010];

    sendShopifyWebhook($this, route('shopify.webhooks.orders.delete'), $shop, $payload)
        ->assertStatus(200);

    expect(ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', '1010')->exists())->toBeFalse();
});
