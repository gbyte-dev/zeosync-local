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

function generateLaunchHmacQuery(array $params, string $secret): string
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

    if (!Schema::hasTable('notification_settings')) {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
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

it('1. Anonymous GET / renders the public ZeoSync landing page (welcomemain)', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
    $response->assertSee('Connect Store');
    $response->assertDontSee('Exception');
});

it('2. Anonymous GET / does NOT redirect to Shopify Admin or /dashboard', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    expect($response->headers->get('Location'))->toBeNull();
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('3. Opening / without shop query does not start OAuth automatically', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
});

it('4. Opening / with only ?shop=store.myshopify.com does not automatically authenticate or redirect', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $response = $this->get('/?shop=store-zeosync.myshopify.com');

    // Must remain on public landing page, not redirect to dashboard or install
    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('5. Opening / with an existing session cookie remains on public landing page', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    // Simulate old session in browser
    $response = $this->withSession([
        'active_shop'            => 'store-zeosync.myshopify.com',
        'active_shop_id'         => 1,
        '_shopify_verified_shop' => 'store-zeosync.myshopify.com',
    ])->get('/');

    // Public / visit must NOT redirect to dashboard automatically
    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
});

it('6. Store Name + Connect form submission starts the Shopify connection flow', function () {
    $response = $this->get('/install?shop=demo-store');

    $response->assertStatus(200);
    $response->assertViewIs('shopify.auth-popup');
    $response->assertViewHas('shop', 'demo-store.myshopify.com');
});

it('7. Valid encrypted/signed Zeosync token in query param (id_token) authenticates and establishes session', function () {
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

it('8. Invalid encrypted token with forged signature is rejected without authenticating', function () {
    Shop::create([
        'shop'                    => 'store-zeosync.myshopify.com',
        'shop_name'               => 'Zeosync Store',
        'email'                   => 'merchant@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $forgedToken = generateZeosyncTestJwt('store-zeosync.myshopify.com', 'test-client-id', 'test-client-secret', 300, -60, null, null, null, 'wrong-secret');

    // Public / entry with forged token remains on landing page
    $response = $this->get('/?shop=store-zeosync.myshopify.com&id_token=' . $forgedToken);
    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
    expect(session('_shopify_verified_shop'))->toBeNull();

    // Protected AJAX API with forged token returns 401
    $ajaxResponse = $this->withHeaders([
        'Accept' => 'application/json',
    ])->get('/orders?id_token=' . $forgedToken);

    $ajaxResponse->assertStatus(401);
});

it('9. Tampered token payload is rejected and stays on landing page', function () {
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
    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('10. Expired token is rejected and stays on landing page', function () {
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
    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('11. Existing Shopify Admin embedded launch with valid HMAC reaches dashboard', function () {
    $shop = Shop::create([
        'shop'                    => 'embedded-store.myshopify.com',
        'shop_name'               => 'Embedded Store',
        'email'                   => 'embedded@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $queryString = generateLaunchHmacQuery([
        'shop'      => 'embedded-store.myshopify.com',
        'host'      => base64_encode('admin.shopify.com/store/embedded-store'),
        'timestamp' => (string) time(),
        'embedded'  => '1',
    ], 'test-client-secret');

    $response = $this->get('/?' . $queryString);

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('/dashboard');
    expect(session('_shopify_verified_shop'))->toBe('embedded-store.myshopify.com');
    expect(session('active_shop'))->toBe('embedded-store.myshopify.com');
    expect(session('active_shop_id'))->toBe($shop->id);
});

it('12. Invalid launch HMAC cannot bypass the public landing page', function () {
    Shop::create([
        'shop'                    => 'embedded-store.myshopify.com',
        'shop_name'               => 'Embedded Store',
        'email'                   => 'embedded@zeosync.app',
        'access_token'            => 'shp_access_token_valid',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $response = $this->get('/?shop=embedded-store.myshopify.com&hmac=invalidhmac123&timestamp=' . time());

    $response->assertStatus(200);
    $response->assertViewIs('welcomemain');
    expect(session('_shopify_verified_shop'))->toBeNull();
});

it('13. Custom X-Shopify-Session-Token and Authorization Bearer headers authenticate correctly', function () {
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

it('14. Valid encrypted token in path (/apps/{encrypted-value}/dashboard) decrypts, establishes session, and redirects cleanly', function () {
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
    expect($redirectUrl)->not->toContain($encryptedValue);
    expect($redirectUrl)->toContain('dashboard');

    expect(session('_shopify_verified_shop'))->toBe('path-store.myshopify.com');
    expect(session('active_shop'))->toBe('path-store.myshopify.com');
    expect(session('active_shop_id'))->toBe($shop->id);
});

it('15. Valid JSON encrypted token in path (/apps/{encrypted-value}/dashboard) decrypts and establishes session', function () {
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

it('16. Invalid encrypted value in path fails safely without authenticating', function () {
    session()->flush();
    $response = $this->get('/apps/invalid-garbage-encrypted-string/dashboard');

    expect(session('_shopify_verified_shop'))->toBeNull();
    expect(session('active_shop'))->toBeNull();
    $response->assertStatus(302);
});

it('17. Existing normal login and public pages without Zeosync continue working', function () {
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

it('18. Successful OAuth callback establishes shop session and completes installation', function () {
    $state = base64_encode(json_encode([
        'shop' => 'new-store.myshopify.com',
        'time' => time(),
    ]));

    $callbackParams = [
        'code'  => 'auth_code_123',
        'shop'  => 'new-store.myshopify.com',
        'state' => $state,
    ];

    $queryString = generateLaunchHmacQuery($callbackParams, 'test-client-secret');

    $response = $this->get('/callback?' . $queryString);

    $response->assertStatus(200);
    $response->assertViewIs('shopify.auth-callback');
    $response->assertViewHas('shop', 'new-store.myshopify.com');

    $createdShop = Shop::where('shop', 'new-store.myshopify.com')->first();
    expect($createdShop)->not->toBeNull();
    expect($createdShop->access_token)->not->toBeEmpty();
    expect(session('_shopify_verified_shop'))->toBe('new-store.myshopify.com');
    expect(session('active_shop'))->toBe('new-store.myshopify.com');
});

it('19. Dashboard HTML contains decrypted plaintext shopify-api-key meta tag and never ciphertext', function () {
    $plainApiKey = 'test_dashboard_client_id_445566';
    $plainApiSecret = 'test_dashboard_secret_shpss_778899';

    AdminSetting::create([
        'option_key'   => 'SHOPIFY_API_KEY',
        'option_value' => $plainApiKey,
    ]);

    AdminSetting::create([
        'option_key'   => 'SHOPIFY_API_SECRET',
        'option_value' => $plainApiSecret,
    ]);

    $shop = Shop::create([
        'shop'                    => 'dash-store.myshopify.com',
        'shop_name'               => 'Dash Store',
        'email'                   => 'merchant@dash.app',
        'access_token'            => 'shp_access_token_dash',
        'access_token_expires_at' => now()->addHour(),
        'is_active'               => 1,
    ]);

    $token = generateZeosyncTestJwt('dash-store.myshopify.com', $plainApiKey, $plainApiSecret);

    $viewData = ['errors' => new \Illuminate\Support\ViewErrorBag(), 'shopModel' => $shop];
    $content = view('layouts.app', $viewData)->render();

    // Plain API key must be in the meta tag
    expect($content)->toContain('<meta name="shopify-api-key" content="' . $plainApiKey . '">');

    // Raw encrypted database string must NOT be in the HTML
    $rawDbValue = DB::table('admin_settings')->where('option_key', 'SHOPIFY_API_KEY')->value('option_value');
    expect($content)->not->toContain($rawDbValue);
    expect($content)->not->toContain('eyJpdiI6');

    // Secret keys must NOT be in the HTML
    expect($content)->not->toContain($plainApiSecret);
    expect($content)->not->toContain(config('app.key'));
});
