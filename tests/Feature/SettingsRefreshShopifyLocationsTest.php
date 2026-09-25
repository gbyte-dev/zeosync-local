<?php

use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Queue::fake();

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

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
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
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
            $table->integer('amazon_quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('sync_limit')->default(100);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    ProductMarketplaceMapping::query()->delete();
    ShopSubscription::query()->delete();
    Plan::query()->delete();
    Shop::query()->delete();
});

function createSettingsTestShop(int $id, string $domain, array $locations = [], ?int $selectedIndex = 0): Shop
{
    $shop = new Shop();
    $shop->id = $id;
    $shop->shop = $domain;
    $shop->shop_name = 'Test Store';
    $shop->email = "{$domain}@example.com";
    $shop->access_token = "token-{$id}";
    $shop->is_active = 1;
    $shop->shopify_locations = $locations;
    $shop->selected_location_index = $selectedIndex;
    $shop->amazon_seller_id = "SELLER_{$id}";
    $shop->save();

    return $shop;
}

function mockSettingsShopAuth(Shop $shop): void
{
    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => $shop->shop,
        'shop_model' => $shop,
        'payload'    => ['dest' => "https://{$shop->shop}"],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);
}

test('1 & 2: Get Latest Shopify Locations successfully fetches all Shopify locations and updates shopify_locations', function () {
    $shop = createSettingsTestShop(1, 'store-refresh.myshopify.com', [
        ['id' => 101, 'name' => 'Old Location A'],
    ], 0);
    mockSettingsShopAuth($shop);

    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        ['id' => 'gid://shopify/Location/201', 'legacyResourceId' => '201', 'name' => 'New Warehouse 1', 'isActive' => true],
                        ['id' => 'gid://shopify/Location/202', 'legacyResourceId' => '202', 'name' => 'New Warehouse 2', 'isActive' => true],
                    ]
                ]
            ]
        ], 200),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('settings.refresh-locations'), [
        'shop' => $shop->shop,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Shopify locations updated successfully.',
    ]);
    expect($response->json('locations'))->toHaveCount(2);

    $shop->refresh();
    expect($shop->shopify_locations)->toHaveCount(2)
        ->and($shop->shopify_locations[0]['name'])->toBe('New Warehouse 1')
        ->and($shop->shopify_locations[1]['name'])->toBe('New Warehouse 2');
});

test('3: Existing selected location remains selected if it still exists in latest locations', function () {
    // Initially selected is Location 102 (index 1)
    $shop = createSettingsTestShop(1, 'store-preserve.myshopify.com', [
        ['id' => 101, 'name' => 'Location 101'],
        ['id' => 102, 'name' => 'Location 102'],
    ], 1);
    mockSettingsShopAuth($shop);

    // Shopify returns locations in a new order: 103, 102, 101
    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        ['id' => 'gid://shopify/Location/103', 'legacyResourceId' => '103', 'name' => 'Location 103', 'isActive' => true],
                        ['id' => 'gid://shopify/Location/102', 'legacyResourceId' => '102', 'name' => 'Location 102', 'isActive' => true],
                        ['id' => 'gid://shopify/Location/101', 'legacyResourceId' => '101', 'name' => 'Location 101', 'isActive' => true],
                    ]
                ]
            ]
        ], 200),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('settings.refresh-locations'), [
        'shop' => $shop->shop,
    ]);

    $response->assertStatus(200);

    $shop->refresh();
    // In the new list [103, 102, 101], Location 102 is at index 1
    expect($shop->selected_location_index)->toBe(1)
        ->and($shop->shopify_locations[$shop->selected_location_index]['id'])->toBe(102);
});

test('4: Deleted selected location is handled safely with application fallback', function () {
    // Initially selected was 999
    $shop = createSettingsTestShop(1, 'store-deleted.myshopify.com', [
        ['id' => 999, 'name' => 'Discontinued Warehouse'],
    ], 0);
    mockSettingsShopAuth($shop);

    // 999 was deleted on Shopify; now only 501 and 502 exist
    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        ['id' => 'gid://shopify/Location/501', 'legacyResourceId' => '501', 'name' => 'Active Warehouse 1', 'isActive' => true],
                        ['id' => 'gid://shopify/Location/502', 'legacyResourceId' => '502', 'name' => 'Active Warehouse 2', 'isActive' => true],
                    ]
                ]
            ]
        ], 200),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('settings.refresh-locations'), [
        'shop' => $shop->shop,
    ]);

    $response->assertStatus(200);

    $shop->refresh();
    // Fallback to first available location (index 0)
    expect($shop->selected_location_index)->toBe(0)
        ->and($shop->shopify_locations[$shop->selected_location_index]['id'])->toBe(501);
});

test('5 & 6: Shopify API failure does not overwrite existing locations or change selected_location_index', function () {
    $originalLocations = [
        ['id' => 101, 'name' => 'Original Loc 1'],
        ['id' => 102, 'name' => 'Original Loc 2'],
    ];
    $shop = createSettingsTestShop(1, 'store-failure.myshopify.com', $originalLocations, 1);
    mockSettingsShopAuth($shop);

    // Simulate Shopify API failure
    Http::fake([
        '*graphql.json*' => Http::response([
            'errors' => [
                ['message' => 'Internal Shopify server error.']
            ]
        ], 500),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('settings.refresh-locations'), [
        'shop' => $shop->shop,
    ]);

    $response->assertStatus(500);

    $shop->refresh();
    // Preserved completely
    expect($shop->shopify_locations)->toBe($originalLocations)
        ->and($shop->selected_location_index)->toBe(1);
});

test('7 & 8: Settings view renders Get Latest Shopify Locations button and dropdown properly', function () {
    $shop = createSettingsTestShop(1, 'store-view.myshopify.com', [
        ['id' => 1001, 'name' => 'Main Center'],
        ['id' => 1002, 'name' => 'Secondary Center'],
    ], 0);
    mockSettingsShopAuth($shop);

    $activeShop = $shop->shop;
    $settings = null;
    $notifications = collect();
    $cspNonce = 'test-nonce';
    $errors = new \Illuminate\Support\ViewErrorBag();

    $viewHtml = view('settings', compact('activeShop', 'shop', 'settings', 'notifications', 'cspNonce', 'errors'))->render();

    expect($viewHtml)->toContain('id="btnRefreshLocations"')
        ->and($viewHtml)->toContain('Get Latest Shopify Locations')
        ->and($viewHtml)->toContain('id="selectedLocationIndex"')
        ->and($viewHtml)->toContain('Main Center')
        ->and($viewHtml)->toContain('Secondary Center');
});

test('9: Refreshing locations does NOT modify existing product_marketplace_mappings.shopify_location_id', function () {
    $shop = createSettingsTestShop(1, 'store-mapping-preserve.myshopify.com', [
        ['id' => 101, 'name' => 'Loc 101'],
    ], 0);
    mockSettingsShopAuth($shop);

    // Existing mapping has a specific shopify_location_id
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'             => $shop->id,
        'amazon_sku'          => 'AMZ-SKU-CUSTOM',
        'shopify_product_id'  => '701',
        'shopify_variant_id'  => '7011',
        'shopify_location_id' => 'custom_loc_999',
    ]);

    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        ['id' => 'gid://shopify/Location/101', 'legacyResourceId' => '101', 'name' => 'Loc 101', 'isActive' => true],
                        ['id' => 'gid://shopify/Location/102', 'legacyResourceId' => '102', 'name' => 'Loc 102', 'isActive' => true],
                    ]
                ]
            ]
        ], 200),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('settings.refresh-locations'), [
        'shop' => $shop->shop,
    ]);

    $response->assertStatus(200);

    $mapping->refresh();
    expect($mapping->shopify_location_id)->toBe('custom_loc_999');
});

test('10: Location refresh maintains strict tenant isolation between Shop A and Shop B', function () {
    $shopA = createSettingsTestShop(1, 'store-a-tenant.myshopify.com', [['id' => 101, 'name' => 'Store A Loc']], 0);
    $shopB = createSettingsTestShop(2, 'store-b-tenant.myshopify.com', [['id' => 201, 'name' => 'Store B Loc']], 0);

    mockSettingsShopAuth($shopA);

    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => [
                        ['id' => 'gid://shopify/Location/101', 'legacyResourceId' => '101', 'name' => 'Store A Updated Loc', 'isActive' => true],
                    ]
                ]
            ]
        ], 200),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('settings.refresh-locations'), [
        'shop' => $shopA->shop,
    ]);

    $response->assertStatus(200);

    $shopA->refresh();
    $shopB->refresh();

    expect($shopA->shopify_locations[0]['name'])->toBe('Store A Updated Loc')
        ->and($shopB->shopify_locations[0]['name'])->toBe('Store B Loc');
});
