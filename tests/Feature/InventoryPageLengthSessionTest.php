<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->text('amazon_refresh_token')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->boolean('is_active')->default(1);
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
            $table->timestamps();
        });
    }
});

it('defaults to page length 10 on index when no session exists', function () {
    $shop = Shop::create([
        'shop'         => 'test-store-len-1.myshopify.com',
        'shop_name'    => 'Test Store 1',
        'email'        => 'owner@store1.com',
        'access_token' => 'shp_token_123',
        'is_active'    => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ])->get(route('shopify.inventory.index', ['shop' => $shop->shop]));

    $response->assertStatus(200);
    $response->assertViewHas('shopifyPageLength', 10);
    $response->assertViewHas('amazonPageLength', 10);
});

it('updates and persists shopify and amazon page lengths independently in session', function () {
    $shop = Shop::create([
        'shop'         => 'test-store-len-2.myshopify.com',
        'shop_name'    => 'Test Store 2',
        'email'        => 'owner@store2.com',
        'access_token' => 'shp_token_456',
        'is_active'    => 1,
    ]);

    $sessionData = [
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];

    // Update shopify length to 50
    $updateShopifyResponse = $this->withSession($sessionData)
        ->postJson(route('shopify.inventory.page_length', ['shop' => $shop->shop]), [
            'type'   => 'shopify',
            'length' => 50,
        ]);

    $updateShopifyResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
            'type'    => 'shopify',
            'length'  => 50,
        ]);

    $this->assertEquals(50, session("inventory_page_length_{$shop->id}.shopify"));
    $this->assertNull(session("inventory_page_length_{$shop->id}.amazon"));

    // Update amazon length to 25
    $sessionData["inventory_page_length_{$shop->id}.shopify"] = 50;

    $updateAmazonResponse = $this->withSession($sessionData)
        ->postJson(route('shopify.inventory.page_length', ['shop' => $shop->shop]), [
            'type'   => 'amazon',
            'length' => 25,
        ]);

    $updateAmazonResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
            'type'    => 'amazon',
            'length'  => 25,
        ]);

    $this->assertEquals(25, session("inventory_page_length_{$shop->id}.amazon"));
    $this->assertEquals(50, session("inventory_page_length_{$shop->id}.shopify"));

    // Verify index returns both updated lengths
    $sessionData["inventory_page_length_{$shop->id}.amazon"] = 25;

    $indexResponse = $this->withSession($sessionData)
        ->get(route('shopify.inventory.index', ['shop' => $shop->shop]));

    $indexResponse->assertStatus(200);
    $indexResponse->assertViewHas('shopifyPageLength', 50);
    $indexResponse->assertViewHas('amazonPageLength', 25);
});

it('rejects invalid page length or invalid type', function () {
    $shop = Shop::create([
        'shop'         => 'test-store-len-3.myshopify.com',
        'shop_name'    => 'Test Store 3',
        'email'        => 'owner@store3.com',
        'access_token' => 'shp_token_789',
        'is_active'    => 1,
    ]);

    $sessionData = [
        '_shopify_verified_shop' => $shop->shop,
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];

    // Invalid length
    $responseInvalidLength = $this->withSession($sessionData)
        ->postJson(route('shopify.inventory.page_length', ['shop' => $shop->shop]), [
            'type'   => 'shopify',
            'length' => 999,
        ]);
    $responseInvalidLength->assertStatus(422);

    // Invalid type
    $responseInvalidType = $this->withSession($sessionData)
        ->postJson(route('shopify.inventory.page_length', ['shop' => $shop->shop]), [
            'type'   => 'ebay',
            'length' => 25,
        ]);
    $responseInvalidType->assertStatus(422);
});

it('scopes page lengths per shop id preventing cross-tenant pollution', function () {
    $shop1 = Shop::create([
        'shop'         => 'store-a.myshopify.com',
        'shop_name'    => 'Store A',
        'email'        => 'a@store.com',
        'access_token' => 'shp_token_a',
        'is_active'    => 1,
    ]);

    $shop2 = Shop::create([
        'shop'         => 'store-b.myshopify.com',
        'shop_name'    => 'Store B',
        'email'        => 'b@store.com',
        'access_token' => 'shp_token_b',
        'is_active'    => 1,
    ]);

    $sessionData = [
        '_shopify_verified_shop' => $shop1->shop,
        'active_shop'            => $shop1->shop,
        'active_shop_id'         => $shop1->id,
        "inventory_page_length_{$shop1->id}.shopify" => 100,
    ];

    $responseShop1 = $this->withSession($sessionData)
        ->get(route('shopify.inventory.index', ['shop' => $shop1->shop]));

    $responseShop1->assertStatus(200);
    $responseShop1->assertViewHas('shopifyPageLength', 100);

    // Switch session to Shop 2 (which has no page length set)
    $sessionDataShop2 = [
        '_shopify_verified_shop' => $shop2->shop,
        'active_shop'            => $shop2->shop,
        'active_shop_id'         => $shop2->id,
        "inventory_page_length_{$shop1->id}.shopify" => 100, // Shop 1 value in session
    ];

    $responseShop2 = $this->withSession($sessionDataShop2)
        ->get(route('shopify.inventory.index', ['shop' => $shop2->shop]));

    $responseShop2->assertStatus(200);
    // Should default to 10 for Shop 2
    $responseShop2->assertViewHas('shopifyPageLength', 10);
});
