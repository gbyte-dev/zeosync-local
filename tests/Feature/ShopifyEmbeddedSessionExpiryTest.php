<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function generateShopifyEmbeddedTestJwt(
    string $shop = 'store-test.myshopify.com',
    string $apiKey = 'test-client-id',
    string $apiSecret = 'test-client-secret',
    int $expOffset = 300,
    int $nbfOffset = -60,
    ?string $aud = null,
    ?string $dest = null,
    ?string $iss = null,
    ?string $customSecret = null,
    string $alg = 'HS256'
): string {
    $header = [
        'alg' => $alg,
        'typ' => 'JWT',
    ];

    $payload = [
        'iss'  => $iss ?? "https://{$shop}/admin",
        'dest' => $dest ?? "https://{$shop}",
        'aud'  => $aud ?? $apiKey,
        'sub'  => '12345678',
        'exp'  => time() + $expOffset,
        'nbf'  => time() + $nbfOffset,
        'iat'  => time(),
        'jti'  => uniqid('jwt_', true),
        'sid'  => uniqid('sess_', true),
    ];

    $base64UrlEncode = function ($data) {
        return rtrim(strtr(base64_encode(is_string($data) ? $data : json_encode($data)), '+/', '-_'), '=');
    };

    $headerB64 = $base64UrlEncode($header);
    $payloadB64 = $base64UrlEncode($payload);
    $secret = $customSecret ?? $apiSecret;
    $signature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
    $signatureB64 = $base64UrlEncode($signature);

    return "{$headerB64}.{$payloadB64}.{$signatureB64}";
}

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-client-id',
        'services.shopify.api_secret' => 'test-client-secret',
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
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    } else {
        Shop::truncate();
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('sku')->nullable();
            $table->string('title')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->bigInteger('shopify_order_id')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('sku')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_sync_logs')) {
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('status')->nullable();
            $table->string('type')->nullable();
            $table->string('platform')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('logs')) {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    Cache::flush();
    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

    Http::fake([
        '*graphql.json*' => Http::response(['data' => ['shop' => ['id' => '1', 'name' => 'Store']]], 200),
    ]);
});

it('1. Embedded session expiry on /dashboard returns reauth bounce view instead of welcomemain', function () {
    Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    // Request /dashboard without active session cookie in embedded context
    $response = $this->get('/dashboard?shop=store-test.myshopify.com&embedded=1');

    // Must return 200 OK with shopify.reauth view
    $response->assertStatus(200);
    $response->assertViewIs('shopify.reauth');
    $response->assertSee('Connecting to Shopify');
    $response->assertSee('app-bridge.js', false);
    $response->assertDontSee('Two sales channels'); // Must NOT see welcomemain marketing page
});

it('2. Embedded session expiry on /inventory preserves original destination', function () {
    Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $response = $this->get('/inventory?shop=store-test.myshopify.com&embedded=1');

    $response->assertStatus(200);
    $response->assertViewIs('shopify.reauth');
    $response->assertViewHas('targetUrl');
    $targetUrl = $response->viewData('targetUrl');
    expect($targetUrl)->toContain('/inventory');
    expect($targetUrl)->toContain('shop=store-test.myshopify.com');
});

it('3. Fresh valid ID token authenticates correct Shop, establishes session, and redirects cleanly', function () {
    $shop = Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $token = generateShopifyEmbeddedTestJwt('store-test.myshopify.com');

    $response = $this->get('/inventory?id_token=' . urlencode($token) . '&shop=store-test.myshopify.com');

    // Must redirect cleanly without id_token
    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    expect($location)->toContain('/inventory');
    expect($location)->not->toContain('id_token');

    // Session must be established
    expect(session('_shopify_verified_shop'))->toBe('store-test.myshopify.com');
    expect(session('active_shop'))->toBe('store-test.myshopify.com');
    expect(session('active_shop_id'))->toBe($shop->id);
});

it('4. Invalid or forged ID token is rejected without creating session', function () {
    Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $forgedToken = generateShopifyEmbeddedTestJwt(
        shop: 'store-test.myshopify.com',
        customSecret: 'wrong-secret-key-123'
    );

    $response = $this->get('/inventory?id_token=' . urlencode($forgedToken) . '&shop=store-test.myshopify.com');

    // Should return reauth view or reject, but NEVER create a session
    expect(session('_shopify_verified_shop'))->toBeNull();
    expect(session('active_shop'))->toBeNull();
});

it('5. Untrusted shop parameter without valid token does NOT authenticate', function () {
    Shop::create([
        'shop'         => 'victim-store.myshopify.com',
        'shop_name'    => 'Victim Store',
        'email'        => 'victim@test.com',
        'access_token' => 'victim_secret_token',
        'is_active'    => 1,
    ]);

    $response = $this->get('/inventory?shop=victim-store.myshopify.com');

    // Returns reauth view but creates no session
    $response->assertStatus(200);
    $response->assertViewIs('shopify.reauth');
    expect(session('_shopify_verified_shop'))->toBeNull();
    expect(session('active_shop'))->toBeNull();
});

it('6. External redirect target is sanitized safely', function () {
    Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $response = $this->get('/dashboard?shop=store-test.myshopify.com');
    $response->assertStatus(200);
    $response->assertViewIs('shopify.reauth');
    $response->assertSee('sanitizeDestination', false);
});

it('7. Unauthenticated AJAX request returns 401 with retry header', function () {
    Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $response = $this->getJson('/inventory?shop=store-test.myshopify.com');

    $response->assertStatus(401);
    $response->assertHeader('X-Shopify-Retry-Invalid-Session-Request', '1');
    $response->assertJson([
        'error'   => 'Unauthorized',
        'message' => 'Shopify authentication required.',
    ]);
});

it('8. Standalone visit to / outside Shopify Admin renders welcomemain', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
    $response->assertSee('Connect Store');
});

it('8b. Standalone visit to /dashboard outside Shopify Admin redirects to crm.entry', function () {
    $response = $this->get('/dashboard');

    $response->assertStatus(302);
    $response->assertRedirect('/');

    $followResponse = $this->followRedirects($response);
    $followResponse->assertStatus(200);
    $followResponse->assertViewIs('welcomemain');
});

it('9. Active verified session renders page directly without reauth bounce', function () {
    $shop = Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => 'store-test.myshopify.com',
        '_shopify_verified_at'   => time(),
        'active_shop'            => 'store-test.myshopify.com',
        'active_shop_id'         => $shop->id,
    ])->get('/dashboard?shop=store-test.myshopify.com');

    $response->assertStatus(200);
    $response->assertViewIs('dashboard');
    $response->assertDontSee('shopify.reauth');
});

it('10. Explicit logout clears session and redirects to crm.entry', function () {
    $shop = Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $response = $this->withSession([
        '_shopify_verified_shop' => 'store-test.myshopify.com',
        '_shopify_verified_at'   => time(),
        'active_shop'            => 'store-test.myshopify.com',
        'active_shop_id'         => $shop->id,
    ])->get('/logout');

    $response->assertStatus(302);
    $response->assertRedirect('/');

    $followResponse = $this->followRedirects($response);
    $followResponse->assertStatus(200);
    $followResponse->assertViewIs('welcomemain');
});

it('11. Cold launch with valid HMAC authenticates and reaches dashboard', function () {
    $shop = Shop::create([
        'shop'         => 'store-test.myshopify.com',
        'shop_name'    => 'Test Store',
        'email'        => 'merchant@test.com',
        'access_token' => 'valid_token_123',
        'is_active'    => 1,
    ]);

    $params = [
        'shop'      => 'store-test.myshopify.com',
        'timestamp' => (string) time(),
        'embedded'  => '1',
    ];
    ksort($params);
    $hmac = hash_hmac('sha256', urldecode(http_build_query($params)), 'test-client-secret');
    $params['hmac'] = $hmac;

    $response = $this->get('/?' . http_build_query($params));

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toContain('/dashboard');
    expect(session('_shopify_verified_shop'))->toBe('store-test.myshopify.com');
});

it('12. Cross-tenant isolation: Token for Shop A cannot authenticate Shop B', function () {
    $shopA = Shop::create([
        'shop'         => 'store-a.myshopify.com',
        'shop_name'    => 'Store A',
        'email'        => 'a@test.com',
        'access_token' => 'token_a',
        'is_active'    => 1,
    ]);

    $shopB = Shop::create([
        'shop'         => 'store-b.myshopify.com',
        'shop_name'    => 'Store B',
        'email'        => 'b@test.com',
        'access_token' => 'token_b',
        'is_active'    => 1,
    ]);

    // Token for Shop A passed while requesting Shop B's URL
    $tokenA = generateShopifyEmbeddedTestJwt('store-a.myshopify.com');

    $response = $this->get('/inventory?id_token=' . urlencode($tokenA) . '&shop=store-b.myshopify.com');

    // Must authenticate Shop A, NEVER Shop B
    expect(session('_shopify_verified_shop'))->toBe('store-a.myshopify.com');
    expect(session('active_shop'))->toBe('store-a.myshopify.com');
    expect(session('active_shop_id'))->toBe($shopA->id);
    expect(session('active_shop_id'))->not->toBe($shopB->id);
});

it('13. zeosync.blade.php contains App Bridge meta tag and iframe recovery safety net', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('shopify-api-key', false);
    $response->assertSee('app-bridge.js', false);
    $response->assertSee('isInIframe', false);
    $response->assertSee('recoverEmbeddedShopifySession', false);
    $response->assertSee('zeosync_iframe_reauth_ts', false);
});

it('14. zeosync.blade.php honors explicit logout without triggering auto-reauth loop', function () {
    $response = $this->get('/?logged_out=1');

    $response->assertStatus(200);
    $response->assertSee('logged_out', false);
    $response->assertSee('zeosync_explicit_logout', false);
});
