<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function generateActivateTestCallbackQuery(array $params, string $secret): string
{
    unset($params['hmac'], $params['signature']);
    ksort($params);
    $computedHmac = hash_hmac('sha256', urldecode(http_build_query($params)), $secret);
    $params['hmac'] = $computedHmac;
    return http_build_query($params);
}

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-client-id',
        'services.shopify.api_secret' => 'test-client-secret',
        'app.disable_subscription'    => false,
    ]);

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    } else {
        DB::table('admin_settings')->truncate();
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
            $table->string('domain')->nullable();
            $table->string('plan')->nullable();
            $table->string('plan_expires_at')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->default('na');
            $table->text('amazon_refresh_token')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_endpoint')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('hmac')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    } else {
        Shop::truncate();
    }

    if (!Schema::hasTable('mail_templates')) {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->unique();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('notification_settings')) {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->unique();
            $table->boolean('in_app_enabled')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_notifications')) {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->text('prices')->nullable();
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->text('stripe_price_ids')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_status')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

    AdminSetting::create([
        'option_key'   => 'SHOPIFY_API_KEY',
        'option_value' => 'test-client-id',
    ]);

    AdminSetting::create([
        'option_key'   => 'SHOPIFY_API_SECRET',
        'option_value' => 'test-client-secret',
    ]);

    Http::fake([
        '*/admin/oauth/access_token' => Http::response([
            'access_token' => 'shp_access_token_mock_123',
            'refresh_token' => 'shp_refresh_token_mock_123',
            'expires_in' => 3600,
            'refresh_token_expires_in' => 7776000,
        ], 200),
        '*/admin/api/*/locations.json' => Http::response([
            'locations' => [
                ['id' => 101, 'name' => 'Primary Location']
            ]
        ], 200),
        '*/admin/api/*/webhooks.json' => Http::response(['webhook' => ['id' => 1]], 200),
        '*' => Http::response(['success' => true], 200),
    ]);
});

it('TEST 1: New shop OAuth callback routes to /activate and activation completes to dashboard', function () {
    Shop::where('shop', 'new-merchant.myshopify.com')->forceDelete();

    $state = base64_encode(json_encode([
        'shop' => 'new-merchant.myshopify.com',
        'time' => time(),
    ]));

    $queryString = generateActivateTestCallbackQuery([
        'code'  => 'auth_code_new_123',
        'shop'  => 'new-merchant.myshopify.com',
        'state' => $state,
    ], 'test-client-secret');

    $response = $this->get('/callback?' . $queryString);

    $response->assertStatus(200);
    $response->assertViewIs('shopify.auth-callback');
    $response->assertViewHas('shop', 'new-merchant.myshopify.com');

    // Destination MUST be /activate for new/incomplete shop
    $redirectUrl = $response->viewData('redirectUrl');
    expect($redirectUrl)->toContain('/activate');
    expect($redirectUrl)->not->toContain('/dashboard');

    $createdShop = Shop::where('shop', 'new-merchant.myshopify.com')->first();
    expect($createdShop)->not->toBeNull();
    expect($createdShop->shop_name)->toBeNull();
    expect($createdShop->email)->toBeNull();

    // Now complete the activation form
    $activateResponse = $this->post('/activate', [
        'shop_url'  => 'new-merchant.myshopify.com',
        'shop_name' => 'New Merchant Store',
        'email'     => 'owner@newmerchant.com',
    ]);

    $activateResponse->assertRedirect();
    expect($activateResponse->headers->get('Location'))->toContain('/dashboard');

    $createdShop->refresh();
    expect($createdShop->shop_name)->toBe('New Merchant Store');
    expect($createdShop->email)->toBe('owner@newmerchant.com');
});

it('TEST 2: Existing fully activated shop OAuth callback routes directly to /dashboard and skips /activate', function () {
    $shop = Shop::create([
        'shop'                    => 'existing-active.myshopify.com',
        'shop_name'               => 'Active Store Name',
        'email'                   => 'active@store.com',
        'access_token'            => 'shp_old_access_token',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $state = base64_encode(json_encode([
        'shop' => 'existing-active.myshopify.com',
        'time' => time(),
    ]));

    $queryString = generateActivateTestCallbackQuery([
        'code'  => 'auth_code_existing_456',
        'shop'  => 'existing-active.myshopify.com',
        'state' => $state,
    ], 'test-client-secret');

    $response = $this->get('/callback?' . $queryString);

    $response->assertStatus(200);
    $response->assertViewIs('shopify.auth-callback');
    $response->assertViewHas('shop', 'existing-active.myshopify.com');

    // Destination MUST be /dashboard for already-activated shop
    $redirectUrl = $response->viewData('redirectUrl');
    expect($redirectUrl)->toContain('/dashboard');
    expect($redirectUrl)->not->toContain('/activate');

    // Tokens were updated
    $shop->refresh();
    expect($shop->access_token)->toBe('shp_access_token_mock_123');
    expect($shop->shop_name)->toBe('Active Store Name');
    expect($shop->email)->toBe('active@store.com');
});

it('TEST 3: Existing shop missing shop_name OAuth callback routes to /activate', function () {
    $shop = Shop::create([
        'shop'                    => 'missing-name.myshopify.com',
        'shop_name'               => null,
        'email'                   => 'merchant@missingname.com',
        'access_token'            => 'shp_old_access_token',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $state = base64_encode(json_encode([
        'shop' => 'missing-name.myshopify.com',
        'time' => time(),
    ]));

    $queryString = generateActivateTestCallbackQuery([
        'code'  => 'auth_code_missing_name_789',
        'shop'  => 'missing-name.myshopify.com',
        'state' => $state,
    ], 'test-client-secret');

    $response = $this->get('/callback?' . $queryString);

    $response->assertStatus(200);
    $redirectUrl = $response->viewData('redirectUrl');
    expect($redirectUrl)->toContain('/activate');
    expect($redirectUrl)->not->toContain('/dashboard');
});

it('TEST 4: Existing shop missing email OAuth callback routes to /activate', function () {
    $shop = Shop::create([
        'shop'                    => 'missing-email.myshopify.com',
        'shop_name'               => 'Merchant Name Exists',
        'email'                   => null,
        'access_token'            => 'shp_old_access_token',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $state = base64_encode(json_encode([
        'shop' => 'missing-email.myshopify.com',
        'time' => time(),
    ]));

    $queryString = generateActivateTestCallbackQuery([
        'code'  => 'auth_code_missing_email_101',
        'shop'  => 'missing-email.myshopify.com',
        'state' => $state,
    ], 'test-client-secret');

    $response = $this->get('/callback?' . $queryString);

    $response->assertStatus(200);
    $redirectUrl = $response->viewData('redirectUrl');
    expect($redirectUrl)->toContain('/activate');
    expect($redirectUrl)->not->toContain('/dashboard');
});

it('TEST 5: Direct GET /activate for fully configured shop redirects to /dashboard', function () {
    $shop = Shop::create([
        'shop'                    => 'configured-store.myshopify.com',
        'shop_name'               => 'Configured Store',
        'email'                   => 'configured@store.com',
        'access_token'            => 'shp_valid_token_xyz',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    session([
        'active_shop'            => 'configured-store.myshopify.com',
        'active_shop_id'         => $shop->id,
        '_shopify_verified_shop' => 'configured-store.myshopify.com',
    ]);

    $response = $this->get('/activate?shop=configured-store.myshopify.com');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/dashboard');
});

it('TEST 6: Direct GET /activate for incomplete shop renders the activate form', function () {
    $shop = Shop::create([
        'shop'                    => 'incomplete-store.myshopify.com',
        'shop_name'               => null,
        'email'                   => null,
        'access_token'            => 'shp_valid_token_abc',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    session([
        'active_shop'            => 'incomplete-store.myshopify.com',
        'active_shop_id'         => $shop->id,
        '_shopify_verified_shop' => 'incomplete-store.myshopify.com',
    ]);

    $response = $this->get('/activate?shop=incomplete-store.myshopify.com');

    $response->assertStatus(200);
    $response->assertViewIs('setup.activate');
});

it('TEST 7: Subscription check still triggers when existing shop accesses protected routes with inactive subscription', function () {
    $shop = Shop::create([
        'shop'                    => 'sub-inactive-store.myshopify.com',
        'shop_name'               => 'Sub Test Store',
        'email'                   => 'sub@store.com',
        'access_token'            => 'shp_valid_token_sub',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    // Explicitly ensure CheckSubscription is enabled
    config(['app.disable_subscription' => false]);

    // Emulate authenticated session
    session([
        'active_shop'            => 'sub-inactive-store.myshopify.com',
        'active_shop_id'         => $shop->id,
        '_shopify_verified_shop' => 'sub-inactive-store.myshopify.com',
    ]);

    // Protected route under CheckSubscription middleware
    $response = $this->get('/inventory?shop=sub-inactive-store.myshopify.com');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toMatch('/(plans|subscriptions|planview)/');
});

it('TEST 8: Unauthenticated request cannot access dashboard simply because a shop exists in DB', function () {
    Shop::create([
        'shop'                    => 'victim-store.myshopify.com',
        'shop_name'               => 'Victim Store',
        'email'                   => 'victim@store.com',
        'access_token'            => 'shp_valid_token_victim',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    session()->flush();

    $response = $this->get('/dashboard?shop=victim-store.myshopify.com');

    // Unauthenticated request without JWT / HMAC / session must fail closed
    expect($response->status())->toBeIn([401, 302, 403, 404]);
    if ($response->isRedirect()) {
        expect($response->headers->get('Location'))->not->toContain('/inventory');
    }
});
