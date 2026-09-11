<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function generateZeosyncTestJwt(
    string $shop = 'store-zeosync.myshopify.com',
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

    if (!Schema::hasTable('admins')) {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('super_admin');
            $table->rememberToken();
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
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    } else {
        Shop::truncate();
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

    Cache::flush();
    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

    Http::fake([
        '*graphql.json*' => Http::response(['data' => ['shop' => ['id' => '1', 'name' => 'Store']]], 200),
        '*oauth/access_token*' => Http::response(['access_token' => 'fresh_token', 'expires_in' => 3600], 200),
    ]);
});

it('1. Valid encrypted/signed Zeosync token in query param (id_token) authenticates and establishes session', function () {
    $shop = Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('store-zeosync.myshopify.com');

    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $token);

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('/dashboard');
    expect(session('_shopify_verified_shop'))->toBe('store-zeosync.myshopify.com');
    expect(session('active_shop'))->toBe('store-zeosync.myshopify.com');
    expect(session('active_shop_id'))->toBe($shop->id);
});

it('2. Invalid encrypted token with forged signature is rejected without authenticating', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $forgedToken = generateZeosyncTestJwt('store-zeosync.myshopify.com', 'test-client-id', 'test-client-secret', 300, -60, null, null, null, 'wrong-secret');

    // Browser entry with forged token fails closed to install
    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $forgedToken);
    $response->assertRedirect(route('shopify.install', ['shop' => 'store-zeosync.myshopify.com']));
    expect(session('_shopify_verified_shop'))->toBeNull();

    // AJAX API with forged token returns 401
    $ajaxResponse = $this->withHeaders([
        'Accept' => 'application/json',
    ])->get('/orders?id_token=' . $forgedToken);

    $ajaxResponse->assertStatus(401);
});

it('3. Tampered token payload is rejected and fails closed', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('store-zeosync.myshopify.com');
    $parts = explode('.', $token);
    // Tamper the payload part
    $tamperedPayload = rtrim(strtr(base64_encode(json_encode(['iss' => 'https://hacker.com'])), '+/', '-_'), '=');
    $tamperedToken = "{$parts[0]}.{$tamperedPayload}.{$parts[2]}";

    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $tamperedToken);
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('4. Expired token is rejected', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $expiredToken = generateZeosyncTestJwt('store-zeosync.myshopify.com', 'test-client-id', 'test-client-secret', -100);

    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $expiredToken);
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('5. Missing token on entry falls back to install without authenticating', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $response = $this->get('/?shop=store-zeosync.myshopify.com');
    $response->assertRedirect(route('shopify.install', ['shop' => 'store-zeosync.myshopify.com']));
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('6. Token with wrong audience (aud) is rejected', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $wrongAudToken = generateZeosyncTestJwt('store-zeosync.myshopify.com', 'test-client-id', 'test-client-secret', 300, -60, 'wrong-client-id');

    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $wrongAudToken);
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('7. Correct Admin Settings configuration decrypts secret and validates token seamlessly', function () {
    AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_KEY'],
        ['option_value' => 'custom-admin-api-key']
    );
    AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_SECRET'],
        ['option_value' => 'custom-admin-secret-key']
    );

    Shop::create([
        'shop'                    => 'admin-configured-store.myshopify.com',
        'shop_name'               => 'Admin Configured',
        'email'                   => 'admin@zeosync.app',
        'access_token'            => 'shp_access_token_custom',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('admin-configured-store.myshopify.com', 'custom-admin-api-key', 'custom-admin-secret-key');

    $response = $this->get('/?shop=admin-configured-store.myshopify.com&id_token=' . $token);
    expect(session('_shopify_verified_shop'))->toBe('admin-configured-store.myshopify.com');
});

it('8. Missing/disabled Admin Settings configuration fails safely', function () {
    config([
        'services.shopify.api_key'    => null,
        'services.shopify.api_secret' => null,
    ]);
    AdminSetting::where('option_key', 'SHOPIFY_API_KEY')->delete();
    AdminSetting::where('option_key', 'SHOPIFY_API_SECRET')->delete();
    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('store-zeosync.myshopify.com');

    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $token);
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('9. Successful authentication established from token query candidates (token, session_token, session, shopify_token)', function () {
    $shop = Shop::create([
        'shop'                    => 'multi-param-store.myshopify.com',
        'shop_name'               => 'Multi Param Store',
        'email'                   => 'multi@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('multi-param-store.myshopify.com');

    foreach (['token', 'session_token', 'session', 'shopify_token'] as $paramName) {
        session()->flush();
        $response = $this->get("/?shop=multi-param-store.myshopify.com&{$paramName}=" . $token);
        expect(session('_shopify_verified_shop'))->toBe('multi-param-store.myshopify.com');
    }
});

it('10. Encrypted/signed token is consumed server-side and stripped from redirect URL', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('store-zeosync.myshopify.com');

    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $token . '&host=sample-host-123&embedded=1');

    $targetUrl = $response->headers->get('Location');

    // Ensure the token was stripped from redirect URL
    expect($targetUrl)->not->toContain($token);
    expect($targetUrl)->not->toContain('id_token');
    expect($targetUrl)->toContain('shop=store-zeosync.myshopify.com');
    expect($targetUrl)->toContain('host=sample-host-123');
    expect($targetUrl)->toContain('embedded=1');
});

it('11. Custom X-Shopify-Session-Token and Authorization Bearer headers authenticate correctly', function () {
    Shop::create([
        'shop'                    => 'header-store.myshopify.com',
        'shop_name'               => 'Header Store',
        'email'                   => 'header@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('header-store.myshopify.com');

    // Test X-Shopify-Session-Token header
    session()->flush();
    $responseHeader = $this->withHeaders([
        'X-Shopify-Session-Token' => $token,
    ])->get('/?shop=header-store.myshopify.com');

    expect(session('_shopify_verified_shop'))->toBe('header-store.myshopify.com');

    // Test Bearer header
    session()->flush();
    $responseBearer = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->get('/?shop=header-store.myshopify.com');

    expect(session('_shopify_verified_shop'))->toBe('header-store.myshopify.com');
});

it('12. Existing normal login and public pages without Zeosync continue working', function () {
    $responseAbout = $this->get('/about');
    $responseAbout->assertStatus(200);

    $responsePricing = $this->get('/pricing');
    $responsePricing->assertStatus(200);

    $responseContact = $this->get('/contact');
    $responseContact->assertStatus(200);

    $responseTerms = $this->get('/terms');
    $responseTerms->assertStatus(200);

    $responsePrivacy = $this->get('/privacy');
    $responsePrivacy->assertStatus(200);

    $responseAdminLogin = $this->get('/admin/login');
    $responseAdminLogin->assertStatus(200);
});

it('13. Valid encrypted token in path (/apps/{encrypted-value}/dashboard) decrypts, establishes session, and redirects cleanly', function () {
    $shop = Shop::create([
        'shop'                    => 'path-store.myshopify.com',
        'shop_name'               => 'Path Store',
        'email'                   => 'path@zeosync.app',
        'access_token'            => 'shp_access_token_path',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $encryptedValue = Crypt::encryptString('path-store.myshopify.com');

    session()->flush();
    $response = $this->get("/apps/{$encryptedValue}/dashboard");

    $response->assertStatus(302);
    $response->assertRedirect();

    $redirectUrl = $response->headers->get('Location');
    // Ensure sensitive encrypted token is not in redirect URL
    expect($redirectUrl)->not->toContain($encryptedValue);
    expect($redirectUrl)->toContain('dashboard');

    // Verify session
    expect(session('_shopify_verified_shop'))->toBe('path-store.myshopify.com');
    expect(session('active_shop'))->toBe('path-store.myshopify.com');
    expect(session('active_shop_id'))->toBe($shop->id);
});

it('14. Valid JSON encrypted token in path (/apps/{encrypted-value}/dashboard) decrypts and establishes session', function () {
    $shop = Shop::create([
        'shop'                    => 'json-store.myshopify.com',
        'shop_name'               => 'JSON Store',
        'email'                   => 'json@zeosync.app',
        'access_token'            => 'shp_access_token_json',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $payload = json_encode([
        'shop' => 'json-store.myshopify.com',
        'time' => time(),
    ]);
    $encryptedValue = Crypt::encryptString($payload);

    session()->flush();
    $response = $this->get("/apps/{$encryptedValue}/dashboard");

    $response->assertStatus(302);
    expect(session('_shopify_verified_shop'))->toBe('json-store.myshopify.com');
    expect(session('active_shop'))->toBe('json-store.myshopify.com');
    expect(session('active_shop_id'))->toBe($shop->id);
});

it('15. Invalid encrypted value in path fails safely without authenticating', function () {
    session()->flush();
    $response = $this->get('/apps/invalid-garbage-encrypted-string/dashboard');

    expect(session('_shopify_verified_shop'))->toBeNull();
    expect(session('active_shop'))->toBeNull();
    // Browser request should redirect to crm.entry or install, never crash
    $response->assertStatus(302);
});

it('16. Tampered encrypted value (modified ciphertext/mac) fails closed without authenticating', function () {
    $encryptedValue = Crypt::encryptString('tampered-store.myshopify.com');
    $tampered = substr($encryptedValue, 0, -4) . 'AAAA';

    session()->flush();
    $response = $this->get("/apps/{$tampered}/dashboard");

    expect(session('_shopify_verified_shop'))->toBeNull();
    expect(session('active_shop'))->toBeNull();
    $response->assertStatus(302);
});

it('17. Expired launch state in encrypted payload is rejected', function () {
    Shop::create([
        'shop'                    => 'expired-store.myshopify.com',
        'shop_name'               => 'Expired Store',
        'email'                   => 'expired@zeosync.app',
        'access_token'            => 'shp_access_token_expired',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $payload = json_encode([
        'shop' => 'expired-store.myshopify.com',
        'exp'  => time() - 3600, // expired 1 hour ago
    ]);
    $encryptedValue = Crypt::encryptString($payload);

    session()->flush();
    $response = $this->get("/apps/{$encryptedValue}/dashboard");

    expect(session('_shopify_verified_shop'))->toBeNull();
    expect(session('active_shop'))->toBeNull();
    $response->assertStatus(302);
});

it('18. Non-existent shop domain in encrypted payload redirects to install without session', function () {
    $encryptedValue = Crypt::encryptString('non-existent-store.myshopify.com');

    session()->flush();
    $response = $this->get("/apps/{$encryptedValue}/dashboard");

    expect(session('_shopify_verified_shop'))->toBeNull();
    $response->assertStatus(302);
});

it('19. Store handle URL (/store/{shop}/apps/{token}/dashboard) decrypts and establishes session', function () {
    $shop = Shop::create([
        'shop'                    => 'handle-store.myshopify.com',
        'shop_name'               => 'Handle Store',
        'email'                   => 'handle@zeosync.app',
        'access_token'            => 'shp_access_token_handle',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $encryptedValue = Crypt::encryptString('handle-store.myshopify.com');

    session()->flush();
    $response = $this->get("/store/handle-store/apps/{$encryptedValue}/dashboard");

    $response->assertStatus(302);
    expect(session('_shopify_verified_shop'))->toBe('handle-store.myshopify.com');
    expect(session('active_shop'))->toBe('handle-store.myshopify.com');
    expect(session('active_shop_id'))->toBe($shop->id);

    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->not->toContain($encryptedValue);
});

it('20. Existing query-token authentication continues working alongside path token decryption', function () {
    $shop = Shop::create([
        'shop'                    => 'coexist-store.myshopify.com',
        'shop_name'               => 'Coexist Store',
        'email'                   => 'coexist@zeosync.app',
        'access_token'            => 'shp_access_token_coexist',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $jwt = generateZeosyncTestJwt('coexist-store.myshopify.com');
    $crypt = Crypt::encryptString('coexist-store.myshopify.com');

    // Query JWT
    session()->flush();
    $resJwt = $this->get("/?id_token={$jwt}&shop=coexist-store.myshopify.com");
    expect(session('_shopify_verified_shop'))->toBe('coexist-store.myshopify.com');

    // Path Crypt
    session()->flush();
    $resCrypt = $this->get("/apps/{$crypt}/dashboard");
    expect(session('_shopify_verified_shop'))->toBe('coexist-store.myshopify.com');
});

it('21. Existing Shopify OAuth install flow continues working', function () {
    $response = $this->get('/install?shop=new-install-store.myshopify.com');
    $response->assertStatus(200);
    $response->assertViewIs('shopify.auth-popup');
    $response->assertViewHas('shop', 'new-install-store.myshopify.com');
});
