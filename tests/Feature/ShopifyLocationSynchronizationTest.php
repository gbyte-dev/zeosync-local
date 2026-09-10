<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
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
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
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
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }
});

if (!function_exists('createLocationTestShop')) {
    function createLocationTestShop(string $domain = 'store-loc.myshopify.com', ?int $selectedIndex = null): Shop
    {
        $locations = [
            [
                'id' => 'gid://shopify/Location/10001',
                'name' => 'Main Warehouse',
                'active' => true,
            ],
            [
                'id' => 'gid://shopify/Location/10002',
                'name' => 'Retail Store NYC',
                'active' => true,
            ],
            [
                'id' => 'gid://shopify/Location/10003',
                'name' => 'West Coast Hub',
                'active' => true,
            ],
        ];

        return Shop::updateOrCreate(
            ['shop' => $domain],
            [
                'shop_name'               => 'Test Location Store',
                'email'                   => 'owner@store-loc.com',
                'access_token'            => 'shpat_test_token_123',
                'is_active'               => 1,
                'shopify_locations'       => $locations,
                'selected_location_index' => $selectedIndex,
            ]
        );
    }
}

if (!function_exists('mockLocationShopAuth')) {
    function mockLocationShopAuth(Shop $shop): void
    {
        session([
            'active_shop'            => $shop->shop,
            'active_shop_id'         => $shop->id,
            '_shopify_verified_shop' => $shop->shop,
        ]);
    }
}

it('Test A: Selecting location in Settings updates shops.selected_location_index and reflects in Inventory view', function () {
    $shop = createLocationTestShop('store-a.myshopify.com', null);
    mockLocationShopAuth($shop);

    $response = $this->post('/settings', [
        'shop'                    => $shop->shop,
        'selected_location_index' => 1, // Retail Store NYC
    ]);

    $response->assertRedirect();
    $shop->refresh();
    expect($shop->selected_location_index)->toBe(1);

    // Verify Inventory view renders with location index 1 selected
    $inventoryView = view('inventory.index', [
        'inventories' => [],
        'shop'        => $shop,
        'syncUsage'   => ['limit' => 0],
    ])->render();

    expect($inventoryView)->toContain('value="1" selected');
    expect($inventoryView)->toContain('Retail Store NYC');
});

it('Test B: Changing location via AJAX updates shops.selected_location_index and reflects in Settings view', function () {
    $shop = createLocationTestShop('store-b.myshopify.com', 0);
    mockLocationShopAuth($shop);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/settings', [
        'shop'                    => $shop->shop,
        'selected_location_index' => 2, // West Coast Hub
    ]);

    $response->assertOk();
    $response->assertJson([
        'success'                 => true,
        'selected_location_index' => 2,
    ]);

    $shop->refresh();
    expect($shop->selected_location_index)->toBe(2);

    // Verify Settings view renders with location index 2 selected
    $settingsView = view('settings', [
        'activeShop'    => $shop->shop,
        'shop'          => $shop,
        'settings'      => null,
        'notifications' => collect(),
    ])->render();

    expect($settingsView)->toContain('value="2" selected');
    expect($settingsView)->toContain('West Coast Hub');
});

it('Test C: Selecting location clears inventory cache for previous and new location keys', function () {
    $shop = createLocationTestShop('store-c.myshopify.com', 0);
    mockLocationShopAuth($shop);

    Cache::put("shopify_inventory_{$shop->shop}_location_0", ['item1'], 600);
    Cache::put("shopify_inventory_{$shop->shop}_location_1", ['item2'], 600);

    $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/settings', [
        'shop'                    => $shop->shop,
        'selected_location_index' => 1,
    ]);

    expect(Cache::has("shopify_inventory_{$shop->shop}_location_0"))->toBeFalse();
    expect(Cache::has("shopify_inventory_{$shop->shop}_location_1"))->toBeFalse();
});

it('Test D: Invalid location index not belonging to shop is rejected with 422', function () {
    $shop = createLocationTestShop('store-d.myshopify.com', 0);
    mockLocationShopAuth($shop);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/settings', [
        'shop'                    => $shop->shop,
        'selected_location_index' => 999, // Out of bounds
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('selected_location_index');

    $shop->refresh();
    expect($shop->selected_location_index)->toBe(0);
});

it('Test E: Deselecting location (setting to null) is supported and persisted', function () {
    $shop = createLocationTestShop('store-e.myshopify.com', 1);
    mockLocationShopAuth($shop);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/settings', [
        'shop'                    => $shop->shop,
        'selected_location_index' => '',
    ]);

    $response->assertOk();
    $shop->refresh();
    expect($shop->selected_location_index)->toBeNull();
});

it('Test F: Tenant isolation prevents Shop A from updating Shop B selected location', function () {
    $shopA = createLocationTestShop('store-tenant-a.myshopify.com', 0);
    $shopB = createLocationTestShop('store-tenant-b.myshopify.com', 0);

    // Authenticated as Shop A
    mockLocationShopAuth($shopA);

    // Attempt to tamper by passing shop=ShopB
    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/settings?shop=' . $shopB->shop, [
        'selected_location_index' => 2,
    ]);

    // Shop A is updated, Shop B remains unchanged
    $shopA->refresh();
    $shopB->refresh();

    expect($shopA->selected_location_index)->toBe(2);
    expect($shopB->selected_location_index)->toBe(0);
});
