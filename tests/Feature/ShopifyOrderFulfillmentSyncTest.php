<?php

use App\Models\AdminSetting;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\UserNotification;
use App\Models\UserNotificationSetting;
use App\Services\AmazonService;
use App\Services\ShopifyWebhookService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
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
            $table->unsignedBigInteger('shopify_order_id')->unique();
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
            'description' => 'Notifications for order creation, fulfillment, and delivery updates',
            'app_enabled' => true,
            'mail_enabled' => false,
        ]
    );

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
    }
});

if (!function_exists('createTestShopForOrders')) {
    function createTestShopForOrders(string $domain = 'orders-sync-test.myshopify.com'): Shop
    {
        return Shop::updateOrCreate(
            ['shop' => $domain],
            [
                'shop_name'    => 'Order Sync Test Store',
                'email'        => 'merchant@order-sync.com',
                'access_token' => 'shpat_order_sync_token_123',
                'is_active'    => 1,
            ]
        );
    }
}

if (!function_exists('sendShopifyWebhook')) {
    function sendShopifyWebhook($testCase, string $route, Shop $shop, array $payload, ?string $eventId = null, ?string $secret = 'test-api-secret')
    {
        $rawPayload = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $rawPayload, $secret ?? 'test-api-secret', true));

        $server = [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        ];

        if ($eventId) {
            $server['HTTP_X_SHOPIFY_EVENT_ID'] = $eventId;
        }

        return $testCase->call(
            'POST',
            $route,
            [],
            [],
            [],
            $server,
            $rawPayload
        );
    }
}

it('Test A: Webhook Registration - ORDERS_CREATE, ORDERS_UPDATED, and APP_UNINSTALLED are registered and idempotent', function () {
    $shop = createTestShopForOrders('webhook-reg.myshopify.com');
    $service = app(ShopifyWebhookService::class);

    $calls = [];
    Http::fake([
        '*' => function (\Illuminate\Http\Client\Request $request) use (&$calls) {
            $calls[] = $request->data();
            $query = $request->data()['query'] ?? '';

            if (str_contains($query, 'query OrdersCreateWebhooks')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptions' => [
                            'edges' => []
                        ]
                    ]
                ], 200);
            }

            if (str_contains($query, 'query OrdersUpdateWebhooks')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptions' => [
                            'edges' => []
                        ]
                    ]
                ], 200);
            }

            if (str_contains($query, 'mutation WebhookSubscriptionCreate') || str_contains($query, 'mutation webhookSubscriptionCreate')) {
                return Http::response([
                    'data' => [
                        'webhookSubscriptionCreate' => [
                            'webhookSubscription' => [
                                'id' => 'gid://shopify/WebhookSubscription/12345',
                                'topic' => 'ORDERS_UPDATED',
                            ],
                            'userErrors' => [],
                        ]
                    ]
                ], 200);
            }

            return Http::response(['data' => []], 200);
        }
    ]);

    $service->ensureOrdersCreateWebhook($shop);
    $service->ensureOrdersUpdateWebhook($shop);
    $service->ensureAppUninstalledWebhook($shop);

    expect(count($calls))->toBeGreaterThanOrEqual(3);

    // Test Artisan Command catch-up
    $this->artisan('shopify:webhooks:sync', ['--shop' => $shop->shop])
        ->assertSuccessful();
});

it('Test B: Fulfillment update unfulfilled -> fulfilled updates DB and creates notification', function () {
    $shop = createTestShopForOrders('order-fulfill.myshopify.com');

    // Create existing unfulfilled order
    $order = ShopifyOrder::create([
        'shop_id' => $shop->id,
        'shopify_order_id' => 1001,
        'order_number' => '1001',
        'name' => '#1001',
        'fulfillment_status' => null,
        'shipment_status' => null,
    ]);

    $payload = [
        'id' => 1001,
        'order_number' => '1001',
        'name' => '#1001',
        'fulfillment_status' => 'fulfilled',
        'fulfillments' => [],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_fulfill_1');
    $response->assertOk();

    $order->refresh();
    expect($order->fulfillment_status)->toBe('fulfilled');

    $notification = UserNotification::where('shop_id', $shop->id)
        ->where('title', 'Shopify Order Fulfilled')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->message)->toContain('Order #1001 has been fulfilled');
});

it('Test C: No duplicate fulfillment notification on repeat fulfilled webhook', function () {
    $shop = createTestShopForOrders('order-fulfill-repeat.myshopify.com');

    // Already fulfilled order
    ShopifyOrder::create([
        'shop_id' => $shop->id,
        'shopify_order_id' => 1002,
        'order_number' => '1002',
        'name' => '#1002',
        'fulfillment_status' => 'fulfilled',
        'shipment_status' => null,
    ]);

    $payload = [
        'id' => 1002,
        'order_number' => '1002',
        'name' => '#1002',
        'fulfillment_status' => 'fulfilled',
        'fulfillments' => [],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_fulfill_2');
    $response->assertOk();

    $fulfilledNotifications = UserNotification::where('shop_id', $shop->id)
        ->where('title', 'Shopify Order Fulfilled')
        ->count();

    expect($fulfilledNotifications)->toBe(0);
});

it('Test D: Delivery update in_transit -> delivered updates shipment_status and creates notification', function () {
    $shop = createTestShopForOrders('order-deliver.myshopify.com');

    // Existing in_transit order
    $order = ShopifyOrder::create([
        'shop_id' => $shop->id,
        'shopify_order_id' => 1003,
        'order_number' => '1003',
        'name' => '#1003',
        'fulfillment_status' => 'fulfilled',
        'shipment_status' => 'in_transit',
    ]);

    $payload = [
        'id' => 1003,
        'order_number' => '1003',
        'name' => '#1003',
        'fulfillment_status' => 'fulfilled',
        'fulfillments' => [
            [
                'id' => 5001,
                'status' => 'success',
                'shipment_status' => 'delivered',
            ]
        ],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_deliver_1');
    $response->assertOk();

    $order->refresh();
    expect($order->shipment_status)->toBe('delivered');

    $notification = UserNotification::where('shop_id', $shop->id)
        ->where('title', 'Shopify Order Delivered')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->message)->toContain('Order #1003 has been delivered');
});

it('Test E: No duplicate delivery notification on repeat delivered webhook', function () {
    $shop = createTestShopForOrders('order-deliver-repeat.myshopify.com');

    // Already delivered order
    ShopifyOrder::create([
        'shop_id' => $shop->id,
        'shopify_order_id' => 1004,
        'order_number' => '1004',
        'name' => '#1004',
        'fulfillment_status' => 'fulfilled',
        'shipment_status' => 'delivered',
    ]);

    $payload = [
        'id' => 1004,
        'order_number' => '1004',
        'name' => '#1004',
        'fulfillment_status' => 'fulfilled',
        'fulfillments' => [
            [
                'id' => 5002,
                'status' => 'success',
                'shipment_status' => 'delivered',
            ]
        ],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_deliver_2');
    $response->assertOk();

    $deliverNotifications = UserNotification::where('shop_id', $shop->id)
        ->where('title', 'Shopify Order Delivered')
        ->count();

    expect($deliverNotifications)->toBe(0);
});

it('Test F: Fulfilled != Delivered - fulfilled status alone does not mark order delivered', function () {
    $shop = createTestShopForOrders('order-fulfilled-not-delivered.myshopify.com');

    $payload = [
        'id' => 1005,
        'order_number' => '1005',
        'name' => '#1005',
        'fulfillment_status' => 'fulfilled',
        'fulfillments' => [
            [
                'id' => 5003,
                'status' => 'success',
                'shipment_status' => 'in_transit',
            ]
        ],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.create'), $shop, $payload, 'evt_create_1005');
    $response->assertOk();

    $order = ShopifyOrder::where('shopify_order_id', 1005)->first();
    expect($order)->not->toBeNull();
    expect($order->fulfillment_status)->toBe('fulfilled');
    expect($order->shipment_status)->toBe('in_transit');

    $deliveredNotification = UserNotification::where('shop_id', $shop->id)
        ->where('title', 'Shopify Order Delivered')
        ->first();

    expect($deliveredNotification)->toBeNull();
});

it('Test G: HMAC security - invalid HMAC is rejected with 401', function () {
    $shop = createTestShopForOrders('order-hmac-sec.myshopify.com');

    $payload = [
        'id' => 1006,
        'order_number' => '1006',
        'name' => '#1006',
        'fulfillment_status' => 'fulfilled',
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_bad_hmac', 'wrong-secret');
    $response->assertStatus(401);

    expect(ShopifyOrder::where('shopify_order_id', 1006)->exists())->toBeFalse();
    expect(UserNotification::where('shop_id', $shop->id)->count())->toBe(0);
});

it('Test H: Idempotency - duplicate X-Shopify-Event-Id is processed once', function () {
    $shop = createTestShopForOrders('order-idempotent.myshopify.com');

    $order = ShopifyOrder::create([
        'shop_id' => $shop->id,
        'shopify_order_id' => 1007,
        'order_number' => '1007',
        'name' => '#1007',
        'fulfillment_status' => null,
        'shipment_status' => null,
    ]);

    $payload = [
        'id' => 1007,
        'order_number' => '1007',
        'name' => '#1007',
        'fulfillment_status' => 'fulfilled',
        'fulfillments' => [],
    ];

    $res1 = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_same_id_1007');
    $res1->assertOk();

    $res2 = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_same_id_1007');
    $res2->assertOk();

    $notifications = UserNotification::where('shop_id', $shop->id)
        ->where('title', 'Shopify Order Fulfilled')
        ->count();

    expect($notifications)->toBe(1);
});

it('Test I: Existing orders/create regression remains intact', function () {
    $shop = createTestShopForOrders('order-create-reg.myshopify.com');

    // Create marketplace mapping
    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_variant_id' => '99999',
        'amazon_sku' => 'AMZ-SKU-1',
        'quantity' => 10,
    ]);

    $mockAmazon = mock(AmazonService::class);
    $mockAmazon->shouldReceive('updateInventory')
        ->once()
        ->withArgs(function ($shopArg, $sku, $qty) use ($shop) {
            return $shopArg->id === $shop->id && $sku === 'AMZ-SKU-1' && $qty === 8;
        })
        ->andReturn(['success' => true]);

    app()->instance(AmazonService::class, $mockAmazon);

    $payload = [
        'id' => 1008,
        'order_number' => '1008',
        'name' => '#1008',
        'financial_status' => 'paid',
        'fulfillment_status' => null,
        'line_items' => [
            [
                'variant_id' => 99999,
                'quantity' => 2,
            ]
        ],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.create'), $shop, $payload, 'evt_create_1008');
    $response->assertOk();

    $order = ShopifyOrder::where('shopify_order_id', 1008)->first();
    expect($order)->not->toBeNull();
    expect($order->line_items_count)->toBe(1);

    $notification = UserNotification::where('shop_id', $shop->id)
        ->where('title', 'Shopify Order Received')
        ->first();

    expect($notification)->not->toBeNull();
});

it('Test J: Amazon inventory is NOT deducted on fulfillment/delivery orders/updated webhooks', function () {
    $shop = createTestShopForOrders('order-no-double-inv.myshopify.com');

    ShopifyOrder::create([
        'shop_id' => $shop->id,
        'shopify_order_id' => 1009,
        'order_number' => '1009',
        'name' => '#1009',
        'fulfillment_status' => null,
        'shipment_status' => 'in_transit',
        'shopify_event_id' => 'evt_initial_create',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'shopify_variant_id' => '88888',
        'amazon_sku' => 'AMZ-SKU-2',
        'quantity' => 20,
    ]);

    $mockAmazon = mock(AmazonService::class);
    $mockAmazon->shouldNotReceive('updateInventory');
    app()->instance(AmazonService::class, $mockAmazon);

    $payload = [
        'id' => 1009,
        'order_number' => '1009',
        'name' => '#1009',
        'fulfillment_status' => 'fulfilled',
        'fulfillments' => [
            [
                'id' => 5005,
                'status' => 'success',
                'shipment_status' => 'delivered',
            ]
        ],
        'line_items' => [
            [
                'variant_id' => 88888,
                'quantity' => 5,
            ]
        ],
    ];

    $response = sendShopifyWebhook($this, route('shopify.webhooks.orders.update'), $shop, $payload, 'evt_update_fulfill_inv');
    $response->assertOk();

    $order = ShopifyOrder::where('shopify_order_id', 1009)->first();
    expect($order->fulfillment_status)->toBe('fulfilled');
    expect($order->shipment_status)->toBe('delivered');
});

it('Test K: Multiple fulfillments aggregation logic', function () {
    $controller = app(\App\Http\Controllers\ShopifyController::class);

    // 1. One delivered, one in_transit -> in_transit (not delivered overall)
    $fulfillments1 = [
        ['status' => 'success', 'shipment_status' => 'delivered'],
        ['status' => 'success', 'shipment_status' => 'in_transit'],
    ];
    expect($controller->resolveAggregateShipmentStatus($fulfillments1))->toBe('in_transit');

    // 2. Both delivered -> delivered
    $fulfillments2 = [
        ['status' => 'success', 'shipment_status' => 'delivered'],
        ['status' => 'success', 'shipment_status' => 'delivered'],
    ];
    expect($controller->resolveAggregateShipmentStatus($fulfillments2))->toBe('delivered');

    // 3. One cancelled and one delivered -> delivered
    $fulfillments3 = [
        ['status' => 'cancelled', 'shipment_status' => 'failure'],
        ['status' => 'success', 'shipment_status' => 'delivered'],
    ];
    expect($controller->resolveAggregateShipmentStatus($fulfillments3))->toBe('delivered');

    // 4. One out_for_delivery and one in_transit -> out_for_delivery
    $fulfillments4 = [
        ['status' => 'success', 'shipment_status' => 'in_transit'],
        ['status' => 'success', 'shipment_status' => 'out_for_delivery'],
    ];
    expect($controller->resolveAggregateShipmentStatus($fulfillments4))->toBe('out_for_delivery');
});
