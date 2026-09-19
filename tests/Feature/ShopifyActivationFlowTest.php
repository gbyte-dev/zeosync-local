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

it('1. First-time install: setup.store returns JSON success with redirect_url', function () {
    $shop = Shop::create([
        'shop' => 'new-store.myshopify.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
        'shop_name' => null,
        'email' => null,
    ]);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->post('/activate?shop=' . $shop->shop, [
        'shop_url' => $shop->shop,
        'shop_name' => 'New Store Name',
        'email' => 'owner@newstore.com',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Store activated successfully.',
    ]);
    expect($response->json('redirect_url'))->toContain('/dashboard');
    expect($response->json('poll_url'))->toContain('/setup/activation-status');

    $shop->refresh();
    expect($shop->shop_name)->toBe('New Store Name');
    expect($shop->email)->toBe('owner@newstore.com');
    expect((int) $shop->is_active)->toBe(1);
});

it('2. Existing shop details in DB do NOT cause setup.store AJAX to return a 302 HTML redirect', function () {
    $shop = Shop::create([
        'shop' => 'existing-store.myshopify.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
        'shop_name' => 'Old Name',
        'email' => 'old@store.com',
    ]);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->post('/activate?shop=' . $shop->shop, [
        'shop_url' => $shop->shop,
        'shop_name' => 'Updated Name',
        'email' => 'updated@store.com',
    ]);

    // Must be 200 JSON, NEVER a 302 Found redirect to dashboard HTML
    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/json');
    $response->assertJson([
        'success' => true,
    ]);
    expect($response->json('redirect_url'))->toContain('/dashboard');

    $shop->refresh();
    expect($shop->shop_name)->toBe('Updated Name');
});

it('3. Uninstall webhook resets activation fields and sets inactive status', function () {
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

it('4. Reinstall after uninstall clears stale activation details and requires activation', function () {
    // 1. Old shop record in database that was previously uninstalled
    $shop = Shop::create([
        'shop' => 'reinstall-store.myshopify.com',
        'access_token' => '',
        'is_active' => 0,
        'shop_name' => null,
        'email' => null,
        'shopify_connection_status' => 'uninstalled',
    ]);

    // 2. checkShopStatus returns false/null for inactive shop
    $statusResponse = $this->getJson('/api/shop-status?shop=reinstall-store.myshopify.com');
    $statusResponse->assertStatus(200);
    $statusResponse->assertJson([
        'shop_name' => null,
        'email' => null,
        'is_active' => false,
    ]);

    // 3. OAuth callback happens on reinstall
    $apiSecret = 'test-api-secret';
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_SECRET'], ['option_value' => $apiSecret]);
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_KEY'], ['option_value' => 'test-api-key']);

    $state = base64_encode(json_encode(['shop' => 'reinstall-store.myshopify.com', 'time' => time()]));
    $params = [
        'code' => 'auth_code_123',
        'shop' => 'reinstall-store.myshopify.com',
        'state' => $state,
        'timestamp' => (string) time(),
    ];
    ksort($params);
    $queryString = http_build_query($params);
    $hmac = hash_hmac('sha256', $queryString, $apiSecret);
    $params['hmac'] = $hmac;

    $callbackResponse = $this->get('/callback?' . http_build_query($params));
    $callbackResponse->assertStatus(200);

    // 4. Shop is active but activation is required (shop_name & email null)
    $shop->refresh();
    expect((int) $shop->is_active)->toBe(1);
    expect($shop->shop_name)->toBeNull();
    expect($shop->email)->toBeNull();

    // 5. AJAX request to a protected route returns 403 SHOP_ACTIVATION_REQUIRED with JSON
    $ajaxProtected = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/dashboard?shop=' . $shop->shop);

    $ajaxProtected->assertStatus(403);
    $ajaxProtected->assertJson([
        'success' => false,
        'requires_activation' => true,
        'code' => 'SHOP_ACTIVATION_REQUIRED',
    ]);
    expect($ajaxProtected->json('redirect_url'))->toContain('/activate');

    // 6. User submits activation form
    $activateResponse = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
    ])->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->post('/activate?shop=' . $shop->shop, [
        'shop_url' => $shop->shop,
        'shop_name' => 'Reinstalled Store',
        'email' => 'owner@reinstall.com',
    ]);

    $activateResponse->assertStatus(200);
    $activateResponse->assertJson([
        'success' => true,
    ]);
    expect($activateResponse->json('redirect_url'))->toContain('/dashboard');

    $shop->refresh();
    expect($shop->shop_name)->toBe('Reinstalled Store');
    expect($shop->email)->toBe('owner@reinstall.com');
});

it('5. Unauthenticated AJAX request receives 401 JSON with requires_reauth', function () {
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

it('6. Inactive subscription on protected route returns 403 JSON with requires_subscription for AJAX', function () {
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

it('7. Validation failure on setup.store returns 422 JSON', function () {
    $shop = Shop::create([
        'shop' => 'validation-store.myshopify.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->post('/activate?shop=' . $shop->shop, [
        'shop_url' => $shop->shop,
        'shop_name' => '',
        'email' => 'invalid-email',
    ]);

    $response->assertStatus(422);
    $response->assertHeader('content-type', 'application/json');
    $response->assertJsonValidationErrors(['shop_name', 'email']);
});

it('8. Uninstall flow preserves latest name and email in previous_activation_details and clears active fields', function () {
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

it('9. Uninstall webhook is idempotent and does not wipe previous_activation_details if already null', function () {
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

it('10. Reinstall preserves previous_activation_details, requires activation, and new values take priority over old JSON', function () {
    // 1. Initial shop that was uninstalled
    $shop = Shop::create([
        'shop' => 'reinstall-flow.myshopify.com',
        'shop_name' => null,
        'email' => null,
        'access_token' => null,
        'is_active' => 0,
        'shopify_connection_status' => 'uninstalled',
        'previous_activation_details' => [
            'shop_name' => 'Old Legacy Name',
            'email' => 'old@legacy.com',
            'saved_at' => '2026-01-01T00:00:00+00:00',
        ],
    ]);

    // 2. Reinstall via OAuth callback
    $apiSecret = 'test-api-secret';
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_SECRET'], ['option_value' => $apiSecret]);
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_KEY'], ['option_value' => 'test-api-key']);

    $state = base64_encode(json_encode(['shop' => $shop->shop, 'time' => time()]));
    $params = [
        'code' => 'auth_code_reinstall',
        'shop' => $shop->shop,
        'state' => $state,
        'timestamp' => (string) time(),
    ];
    ksort($params);
    $queryString = http_build_query($params);
    $hmac = hash_hmac('sha256', $queryString, $apiSecret);
    $params['hmac'] = $hmac;

    $callbackResponse = $this->get('/callback?' . http_build_query($params));
    $callbackResponse->assertStatus(200);

    // 3. Verify record was not duplicated
    expect(Shop::where('shop', $shop->shop)->count())->toBe(1);

    $shop->refresh();
    expect($shop->is_active)->toBe(1);
    expect($shop->access_token)->not->toBeNull();
    // Verify previous_activation_details was not destroyed
    expect($shop->previous_activation_details['shop_name'])->toBe('Old Legacy Name');
    expect($shop->previous_activation_details['email'])->toBe('old@legacy.com');

    // 4. Verify previous_activation_details does NOT bypass activation
    $dashResponse = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/dashboard?shop=' . $shop->shop);

    $dashResponse->assertStatus(403);
    $dashResponse->assertJson([
        'success' => false,
        'requires_activation' => true,
    ]);

    // 5. Activate with NEW user name and email
    $activateResponse = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->post('/activate?shop=' . $shop->shop, [
        'shop_url' => $shop->shop,
        'shop_name' => 'New Brand Name',
        'email' => 'new@brand.com',
    ]);

    $activateResponse->assertStatus(200);
    $activateResponse->assertJson(['success' => true]);

    $shop->refresh();
    // New values take priority in main columns
    expect($shop->shop_name)->toBe('New Brand Name');
    expect($shop->email)->toBe('new@brand.com');
    // Old JSON details remain preserved
    expect($shop->previous_activation_details['shop_name'])->toBe('Old Legacy Name');
});

it('11. Repeated uninstall overwrites previous_activation_details with latest name and email', function () {
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

it('12. Activation page renders the success container, postMessage support, popup closing logic, and iframe safety checks', function () {
    $shop = Shop::create([
        'shop' => 'view-test.myshopify.com',
        'access_token' => 'shpat_tok_view',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/activate?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertSee('Store activated successfully.');
    $response->assertSee('activationSuccessAlert');
    $response->assertSee('shopify_activated');
    $response->assertSee('isInsideIframe');
    $response->assertSee('window.close()');
});

it('13. Activation submission returns JSON success with redirect URL for both popup and embedded flows', function () {
    $shop = Shop::create([
        'shop' => 'popup-flow.myshopify.com',
        'access_token' => 'shpat_tok_popup',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->post('/activate?shop=' . $shop->shop, [
        'shop_url' => $shop->shop,
        'shop_name' => 'Popup Store',
        'email' => 'popup@store.com',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);
    expect($response->json('redirect_url'))->toContain('/dashboard');
    expect($response->json('poll_url'))->toContain('/setup/activation-status');

    $shop->refresh();
    expect($shop->shop_name)->toBe('Popup Store');
    expect($shop->email)->toBe('popup@store.com');
});

it('14. Activation status endpoint returns activated: false when either field is missing', function () {
    $shop = Shop::create([
        'shop' => 'unactivated-status.myshopify.com',
        'access_token' => 'shpat_tok_unactivated',
        'is_active' => 1,
        'shop_name' => null,
        'email' => null,
    ]);

    $response = $this->getJson('/setup/activation-status?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertJson([
        'activated' => false,
        'shop' => $shop->shop,
        'shop_name' => null,
        'email' => null,
        'message' => 'Store activation pending.',
    ]);
});

it('15. Activation status endpoint returns activated: true only when both fields exist in DB', function () {
    $shop = Shop::create([
        'shop' => 'activated-status.myshopify.com',
        'access_token' => 'shpat_tok_activated',
        'is_active' => 1,
        'shop_name' => 'Confirmed Name',
        'email' => 'confirmed@email.com',
    ]);

    $response = $this->getJson('/setup/activation-status?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertJson([
        'activated' => true,
        'shop' => $shop->shop,
        'shop_name' => 'Confirmed Name',
        'email' => 'confirmed@email.com',
        'message' => 'Store activated successfully.',
    ]);
});

it('16. Activation status endpoint returns 404 JSON for unknown shop domain', function () {
    $response = $this->getJson('/setup/activation-status?shop=unknown-store.myshopify.com');

    $response->assertStatus(404);
    $response->assertJson([
        'activated' => false,
        'shop' => 'unknown-store.myshopify.com',
        'message' => 'Shop not found.',
    ]);
});

it('17. Activation page blade contains polling loop, DB confirmation logic, timeout handling, and stopPolling controls', function () {
    $shop = Shop::create([
        'shop' => 'polling-blade.myshopify.com',
        'access_token' => 'shpat_tok_poll_blade',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/activate?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertSee('pollUrl');
    $response->assertSee('checkActivationStatus');
    $response->assertSee('stopPolling');
    $response->assertSee('maxPollDuration');
    $response->assertSee('Activation is still processing. Please refresh and try again.');
    $response->assertSee('statusData.activated === true');
});

it('18. Activation page blade contains structured debug logging and reliable popup detection', function () {
    $shop = Shop::create([
        'shop' => 'logs-blade.myshopify.com',
        'access_token' => 'shpat_tok_logs_blade',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => $shop->shop,
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/activate?shop=' . $shop->shop);

    $response->assertStatus(200);
    $response->assertSee('[Activation Context]');
    $response->assertSee('[Activation Polling Status]');
    $response->assertSee('[Activation Action]');
    $response->assertSee('hasPopupQuery');
    $response->assertSee('isNamedPopup');
    $response->assertSee('popup_fallback_shown');
    $response->assertSee('Store activated successfully. You can close this window or <a href=', false);
});

it('19. Parent auth popup and layout blades manage window.activeActivationPopup and close it on shopify_activated event', function () {
    $response = $this->view('shopify.auth-popup', [
        'shop' => 'parent-view.myshopify.com',
        'redirectUrl' => 'https://parent-view.myshopify.com/activate?shop=parent-view.myshopify.com&popup=1',
    ]);

    $response->assertSee('window.activeActivationPopup = popup;');
    $response->assertSee('window.activeActivationPopup.close()');
    $response->assertSee('shopify_activated');
});
