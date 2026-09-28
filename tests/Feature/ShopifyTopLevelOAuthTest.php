<?php

use App\Models\AdminSetting;
use App\Models\MailTemplate;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Models\Plan;
use App\Services\EmailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'services.shopify.redirect_uri' => 'https://zeosync.app/callback',
    ]);

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');
    AdminSetting::forget('SHOPIFY_REDIRECT_URI');

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

    view()->share('cspNonce', 'test-csp-nonce');

    Http::fake([
        'https://preserve-email.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'data' => [
                'shop' => [
                    'name' => 'New Name From GQL',
                    'email' => null,
                ],
            ],
        ], 200),
        'https://fallback-store.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'errors' => [['message' => 'GraphQL Rate Limited']],
        ], 500),
        '*graphql.json*' => Http::response([
            'data' => [
                'shop' => [
                    'name' => 'GraphQL Store Name',
                    'email' => 'owner@graphqlstore.com',
                ],
            ],
        ], 200),
        '*/admin/api/*/locations.json' => Http::response(['locations' => [['id' => 12345, 'name' => 'Primary']]], 200),
        '*/admin/oauth/access_token' => Http::response([
            'access_token' => 'shpat_test_token_123',
            'expires_in' => 3600,
        ], 200),
    ]);
});

function generateValidCallbackParams(string $shop = 'test-store.myshopify.com', ?string $apiSecret = 'test-api-secret', array $extra = []): array
{
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_SECRET'], ['option_value' => $apiSecret ?? 'test-api-secret']);
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_KEY'], ['option_value' => 'test-api-key']);

    $state = base64_encode(json_encode(['shop' => $shop, 'time' => time()]));
    $params = array_merge([
        'code' => 'auth_code_xyz',
        'shop' => $shop,
        'state' => $state,
        'timestamp' => (string) time(),
    ], $extra);

    ksort($params);
    $queryString = http_build_query($params);
    $hmac = hash_hmac('sha256', $queryString, $apiSecret ?? 'test-api-secret');
    $params['hmac'] = $hmac;

    return $params;
}

it('1. Top-level /install redirects directly (302) to Shopify OAuth', function () {
    $response = $this->get('/install?shop=demo-store.myshopify.com');

    $response->assertStatus(302);
    $redirectLocation = (string) $response->headers->get('Location');
    expect($redirectLocation)->toContain('https://demo-store.myshopify.com/admin/oauth/authorize?');
    expect($redirectLocation)->toContain('client_id=test-api-key');
    expect($redirectLocation)->toContain('redirect_uri=');
    expect($redirectLocation)->toContain('state=');
});

it('2. Embedded /install returns iframe breakout navigation', function () {
    $response = $this->get('/install?shop=demo-store.myshopify.com&embedded=1');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/html; charset=UTF-8');
    $content = $response->getContent();
    expect($content)->toContain('window.top.location.href');
    expect($content)->toContain('https://demo-store.myshopify.com/admin/oauth/authorize?');
    expect($content)->toContain('<script nonce=');
});

it('3. Invalid shop domain fails with 400', function () {
    $response = $this->get('/install?shop=invalid_domain!@#');

    $response->assertStatus(400);
    expect($response->getContent())->toContain('Invalid shop domain');
});

it('4. Invalid HMAC on callback fails with 403', function () {
    $params = generateValidCallbackParams();
    $params['hmac'] = 'invalid_hmac_signature';

    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(403);
});

it('5. Invalid/tampered state on callback fails with 403', function () {
    $apiSecret = 'test-api-secret';
    AdminSetting::updateOrCreate(['option_key' => 'SHOPIFY_API_SECRET'], ['option_value' => $apiSecret]);
    $tamperedState = base64_encode(json_encode(['shop' => 'attacker.myshopify.com', 'time' => time()]));
    $params = [
        'code' => 'auth_code_xyz',
        'shop' => 'victim-store.myshopify.com',
        'state' => $tamperedState,
        'timestamp' => (string) time(),
    ];
    ksort($params);
    $params['hmac'] = hash_hmac('sha256', http_build_query($params), $apiSecret);

    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(403);
});

it('6. OAuth token exchange occurs and token is stored encrypted', function () {
    $params = generateValidCallbackParams('new-install.myshopify.com');

    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(302);

    $shop = Shop::where('shop', 'new-install.myshopify.com')->first();
    expect($shop)->not->toBeNull();
    expect($shop->access_token)->toBe('shpat_test_token_123');
    expect((int) $shop->is_active)->toBe(1);
});

it('7. GraphQL shop query occurs and populates shop_name and email', function () {
    $params = generateValidCallbackParams('graphql-store.myshopify.com');

    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(302);

    $shop = Shop::where('shop', 'graphql-store.myshopify.com')->first();
    expect($shop->shop_name)->toBe('GraphQL Store Name');
    expect($shop->email)->toBe('owner@graphqlstore.com');
});

it('8. Existing valid email is preserved when GraphQL returns null email on reinstall', function () {
    $existing = Shop::create([
        'shop' => 'preserve-email.myshopify.com',
        'shop_name' => 'Old Name',
        'email' => 'keepme@original.com',
        'access_token' => 'old_token',
        'is_active' => 0,
    ]);

    $params = generateValidCallbackParams('preserve-email.myshopify.com');
    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(302);

    $existing->refresh();
    expect($existing->shop_name)->toBe('New Name From GQL');
    expect($existing->email)->toBe('keepme@original.com');
});

it('9. GraphQL failure does not break installation and uses fallback store name', function () {
    $params = generateValidCallbackParams('fallback-store.myshopify.com');
    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(302);

    $shop = Shop::where('shop', 'fallback-store.myshopify.com')->first();
    expect($shop)->not->toBeNull();
    expect($shop->shop_name)->toBe('Fallback Store');
    expect((int) $shop->is_active)->toBe(1);
});

it('10. New installation triggers welcome email once', function () {
    MailTemplate::create([
        'slug' => 'welcome-email',
        'subject' => 'Welcome to ZeoSync',
        'body' => 'Hello {{name}}',
        'is_active' => true,
    ]);

    $emailMock = Mockery::mock(EmailService::class);
    $emailMock->shouldReceive('sendDynamicEmail')
        ->once()
        ->withArgs(function ($template, $recipient) {
            return $recipient->email === 'owner@graphqlstore.com';
        })
        ->andReturn(true);

    $this->app->instance(EmailService::class, $emailMock);

    $params = generateValidCallbackParams('welcome-mail.myshopify.com');
    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(302);
});

it('11. Reinstallation does not trigger duplicate welcome email', function () {
    $existing = Shop::create([
        'shop' => 'reinstall-no-mail.myshopify.com',
        'shop_name' => 'Existing Store',
        'email' => 'owner@reinstall.com',
        'access_token' => 'old_token',
        'is_active' => 0,
    ]);

    MailTemplate::create([
        'slug' => 'welcome-email',
        'subject' => 'Welcome to ZeoSync',
        'body' => 'Hello',
        'is_active' => true,
    ]);

    $emailMock = Mockery::mock(EmailService::class);
    $emailMock->shouldNotReceive('sendDynamicEmail');
    $this->app->instance(EmailService::class, $emailMock);

    $params = generateValidCallbackParams('reinstall-no-mail.myshopify.com');
    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(302);
});

it('12. Callback redirects directly to dashboard preserving host and embedded', function () {
    $params = generateValidCallbackParams('dashboard-redirect.myshopify.com', 'test-api-secret', [
        'host' => 'dGVzdC1ob3N0',
        'embedded' => '1',
    ]);

    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertStatus(302);

    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('/dashboard');
    expect($location)->toContain('shop=dashboard-redirect.myshopify.com');
    expect($location)->toContain('host=dGVzdC1ob3N0');
    expect($location)->toContain('embedded=1');
    expect($location)->not->toContain('/activate');
    expect($location)->not->toContain('shpat_');
});

it('13. Established session values are set upon callback', function () {
    $params = generateValidCallbackParams('session-store.myshopify.com');

    $response = $this->get('/callback?' . http_build_query($params));
    $response->assertSessionHas('active_shop', 'session-store.myshopify.com');
    $response->assertSessionHas('_shopify_verified_shop', 'session-store.myshopify.com');
    $response->assertSessionHas('active_shop_id');
});

it('14. No access token is exposed in callback response or redirect URL', function () {
    $params = generateValidCallbackParams('safe-token.myshopify.com');

    $response = $this->get('/callback?' . http_build_query($params));
    $location = (string) $response->headers->get('Location');
    $content = (string) $response->getContent();

    expect($location)->not->toContain('shpat_test_token_123');
    expect($content)->not->toContain('shpat_test_token_123');
});
