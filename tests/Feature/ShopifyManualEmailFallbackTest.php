<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key' => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
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
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    } else {
        Shop::query()->forceDelete();
    }

    if (!Schema::hasTable('user_notifications')) {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    view()->share('cspNonce', 'test-nonce');
});

it('1. Redirects unactivated shop (missing email) to setup form on protected route', function () {
    $shop = Shop::create([
        'shop' => 'no-email-store.myshopify.com',
        'shop_name' => 'No Email Store',
        'email' => null,
        'access_token' => 'shpat_valid_token_123',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/help?shop=' . $shop->shop);

    $response->assertRedirect(route('setup.form', ['shop' => $shop->shop]));
});

it('2. GET /setup renders form without redirect loop for unactivated shop', function () {
    $shop = Shop::create([
        'shop' => 'setup-view-store.myshopify.com',
        'shop_name' => 'Setup View Store',
        'email' => null,
        'access_token' => 'shpat_valid_token_123',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/setup?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertSee('Complete Store Setup');
    $response->assertSee('Setup View Store');
});

it('3. GET /setup forwards to dashboard if shop already has an email', function () {
    $shop = Shop::create([
        'shop' => 'already-active.myshopify.com',
        'shop_name' => 'Active Store',
        'email' => 'active@example.com',
        'access_token' => 'shpat_valid_token_123',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/setup?shop=' . $shop->shop);

    $response->assertRedirect(route('dashboard', ['shop' => $shop->shop]));
});

it('4. POST /setup fails validation on invalid email and does not update database', function () {
    $shop = Shop::create([
        'shop' => 'invalid-email-store.myshopify.com',
        'shop_name' => 'Invalid Email Store',
        'email' => null,
        'access_token' => 'shpat_valid_token_123',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->post('/setup', [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
    $shop->refresh();
    expect($shop->email)->toBeNull();
});

it('5. POST /setup saves valid email and redirects to dashboard', function () {
    $shop = Shop::create([
        'shop' => 'valid-email-store.myshopify.com',
        'shop_name' => 'Valid Email Store',
        'email' => null,
        'access_token' => 'shpat_valid_token_123',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->post('/setup', [
        'email' => 'Merchant.Contact@Example.Com ',
    ]);

    $response->assertRedirect(route('dashboard', ['shop' => $shop->shop]));
    $shop->refresh();
    expect($shop->email)->toBe('merchant.contact@example.com');
});

it('6. After saving email, subsequent requests to protected routes pass through to view', function () {
    $shop = Shop::create([
        'shop' => 'activated-store.myshopify.com',
        'shop_name' => 'Activated Store',
        'email' => 'saved@example.com',
        'access_token' => 'shpat_valid_token_123',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/help?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertSee('help Page');
});

it('7. Cross-tenant tampering: Authenticated Shop A cannot update Shop B email via query or body parameters', function () {
    $shopA = Shop::create([
        'shop' => 'attacker-shop-a.myshopify.com',
        'shop_name' => 'Shop A',
        'email' => null,
        'access_token' => 'shpat_token_a',
        'is_active' => 1,
    ]);

    $shopB = Shop::create([
        'shop' => 'victim-shop-b.myshopify.com',
        'shop_name' => 'Shop B',
        'email' => 'victim-original@example.com',
        'access_token' => 'shpat_token_b',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shopA->shop,
        'active_shop' => $shopA->shop,
        'active_shop_id' => $shopA->id,
    ])->post('/setup?shop=' . $shopB->shop, [
        'shop' => $shopB->shop,
        'shop_id' => $shopB->id,
        'email' => 'hijacked@attacker.com',
    ]);

    $response->assertRedirect(route('dashboard', ['shop' => $shopA->shop]));

    $shopA->refresh();
    $shopB->refresh();

    // Shop A is updated
    expect($shopA->email)->toBe('hijacked@attacker.com');

    // Victim Shop B is completely untouched
    expect($shopB->email)->toBe('victim-original@example.com');
});

it('8. Unauthenticated request to POST /setup is rejected and cannot update any shop', function () {
    $shop = Shop::create([
        'shop' => 'target-store.myshopify.com',
        'shop_name' => 'Target Store',
        'email' => null,
        'access_token' => 'shpat_token_target',
        'is_active' => 1,
    ]);

    $response = $this->post('/setup?shop=' . $shop->shop, [
        'shop' => $shop->shop,
        'email' => 'unauth-exploit@attacker.com',
    ]);

    $response->assertStatus(403);

    $shop->refresh();
    expect($shop->email)->toBeNull();
});
