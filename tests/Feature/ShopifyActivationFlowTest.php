<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Models\Plan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
    ]);

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

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
            $table->json('previous_activation_details')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('shopify_connection_status')->nullable();
            $table->string('store_status')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->string('hmac')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    } else {
        if (!Schema::hasColumn('shops', 'previous_activation_details')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->json('previous_activation_details')->nullable();
            });
        }
        Shop::query()->forceDelete();
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Standard');
            $table->decimal('price', 8, 2)->default(10.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('shopify_subscription_gid')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_trial')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('trial_used')->default(false);
            $table->timestamps();
        });
    } else {
        ShopSubscription::query()->forceDelete();
    }

    if (!Schema::hasTable('notification_settings')) {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->nullable();
            $table->boolean('email_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(false);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_notifications')) {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
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

    if (!Schema::hasTable('mail_templates')) {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    view()->share('cspNonce', 'test-nonce');

    Http::fake([
        '*graphql.json*' => Http::response(['data' => ['shop' => ['id' => '1', 'name' => 'Store']]], 200),
        '*/admin/api/*/locations.json' => Http::response(['locations' => [['id' => 12345, 'name' => 'Primary']]], 200),
        '*/admin/oauth/access_token' => Http::response([
            'access_token' => 'shpat_test_token_123',
            'expires_in' => 3600,
        ], 200),
    ]);
});

it('1. Uninstall webhook resets activation fields and sets inactive status', function () {
    $shop = Shop::create([
        'shop' => 'uninstall-test.myshopify.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
        'shop_name' => 'Active Store',
        'email' => 'active@store.com',
    ]);

    $payload = json_encode(['id' => 123, 'domain' => 'uninstall-test.myshopify.com']);
    $secret = 'test-api-secret';
    $hmac = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_SECRET'],
        ['option_value' => $secret]
    );

    $response = $this->call(
        'POST',
        '/webhooks/app-uninstalled',
        [],
        [],
        [],
        [
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'uninstall-test.myshopify.com',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        ],
        $payload
    );

    $response->assertStatus(200);

    $shop->refresh();
    expect((int) $shop->is_active)->toBe(0);
    expect($shop->shop_name)->toBeNull();
    expect($shop->email)->toBeNull();
    expect($shop->shopify_connection_status)->toBe('uninstalled');
});

it('2. Unauthenticated AJAX request receives 401 JSON with requires_reauth', function () {
    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/dashboard?shop=unauthenticated.myshopify.com');

    $response->assertStatus(401);
    $response->assertHeader('content-type', 'application/json');
    $response->assertJson([
        'success' => false,
        'requires_reauth' => true,
        'error' => 'Unauthorized',
    ]);
    expect($response->json('redirect_url'))->toContain('/install');
});

it('3. Inactive subscription on protected route returns 403 JSON with requires_subscription for AJAX', function () {
    $shop = Shop::create([
        'shop' => 'no-sub-store.myshopify.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
        'shop_name' => 'No Sub Store',
        'email' => 'nosub@store.com',
    ]);

    // Route protected by ResolveActiveShop and CheckSubscription
    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/inventory?shop=' . $shop->shop);

    $response->assertStatus(403);
    $response->assertHeader('content-type', 'application/json');
    $response->assertJson([
        'success' => false,
        'requires_subscription' => true,
    ]);
    expect($response->json('redirect_url'))->toContain('/plans');
});

it('4. Uninstall flow preserves latest name and email in previous_activation_details and clears active fields', function () {
    $shop = Shop::create([
        'shop' => 'uninstall-test-8.myshopify.com',
        'shop_name' => 'Active Store Name',
        'email' => 'active@example.com',
        'access_token' => 'shpat_initial_token',
        'is_active' => 1,
        'shopify_connection_status' => 'connected',
        'store_status' => 'active',
    ]);

    $payload = json_encode(['id' => 9999, 'domain' => $shop->shop]);
    $secret = 'test-api-secret';
    $hmac = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_SECRET'],
        ['option_value' => $secret]
    );

    $response = $this->call(
        'POST',
        '/webhooks/app-uninstalled',
        [],
        [],
        [],
        [
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        ],
        $payload
    );

    $response->assertStatus(200);

    $shop->refresh();
    expect($shop->is_active)->toBe(0);
    expect($shop->access_token)->toBeNull();
    expect($shop->shop_name)->toBeNull();
    expect($shop->email)->toBeNull();
    expect($shop->shopify_connection_status)->toBe('uninstalled');
    expect($shop->previous_activation_details)->toBeArray();
    expect($shop->previous_activation_details['shop_name'])->toBe('Active Store Name');
    expect($shop->previous_activation_details['email'])->toBe('active@example.com');
    expect($shop->previous_activation_details['saved_at'])->not->toBeEmpty();
});

it('5. Uninstall webhook is idempotent and does not wipe previous_activation_details if already null', function () {
    $shop = Shop::create([
        'shop' => 'idempotent-uninstall.myshopify.com',
        'shop_name' => 'First Store Name',
        'email' => 'first@example.com',
        'access_token' => 'shpat_tok_1',
        'is_active' => 1,
        'shopify_connection_status' => 'connected',
    ]);

    $payload = json_encode(['id' => 1111, 'domain' => $shop->shop]);
    $secret = 'test-api-secret';
    $hmac = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_SECRET'],
        ['option_value' => $secret]
    );

    // First uninstall
    $res1 = $this->call(
        'POST',
        '/webhooks/app-uninstalled',
        [],
        [],
        [],
        [
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        ],
        $payload
    );
    $res1->assertStatus(200);

    $shop->refresh();
    $savedAt = $shop->previous_activation_details['saved_at'];
    expect($shop->previous_activation_details['shop_name'])->toBe('First Store Name');
    expect($shop->previous_activation_details['email'])->toBe('first@example.com');

    // Second uninstall immediately after (when shop_name and email are null)
    $res2 = $this->call(
        'POST',
        '/webhooks/app-uninstalled',
        [],
        [],
        [],
        [
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        ],
        $payload
    );
    $res2->assertStatus(200);

    $shop->refresh();
    expect($shop->previous_activation_details['shop_name'])->toBe('First Store Name');
    expect($shop->previous_activation_details['email'])->toBe('first@example.com');
    expect($shop->previous_activation_details['saved_at'])->toBe($savedAt);
});

it('6. Repeated uninstall overwrites previous_activation_details with latest name and email', function () {
    $shop = Shop::create([
        'shop' => 'repeated-uninstall.myshopify.com',
        'shop_name' => 'Version 2 Store',
        'email' => 'v2@store.com',
        'access_token' => 'shpat_tok_v2',
        'is_active' => 1,
        'shopify_connection_status' => 'connected',
        'previous_activation_details' => [
            'shop_name' => 'Version 1 Store',
            'email' => 'v1@store.com',
            'saved_at' => '2025-01-01T00:00:00+00:00',
        ],
    ]);

    $payload = json_encode(['id' => 2222, 'domain' => $shop->shop]);
    $secret = 'test-api-secret';
    $hmac = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_SECRET'],
        ['option_value' => $secret]
    );

    $response = $this->call(
        'POST',
        '/webhooks/app-uninstalled',
        [],
        [],
        [],
        [
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop->shop,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        ],
        $payload
    );

    $response->assertStatus(200);

    $shop->refresh();
    expect($shop->is_active)->toBe(0);
    expect($shop->access_token)->toBeNull();
    expect($shop->shop_name)->toBeNull();
    expect($shop->email)->toBeNull();
    // previous_activation_details was overwritten with Version 2 details
    expect($shop->previous_activation_details['shop_name'])->toBe('Version 2 Store');
    expect($shop->previous_activation_details['email'])->toBe('v2@store.com');
    expect($shop->previous_activation_details['saved_at'])->not->toBe('2025-01-01T00:00:00+00:00');
});
