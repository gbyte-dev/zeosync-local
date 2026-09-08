<?php

use App\Http\Middleware\ResolveActiveShop;
use App\Http\Middleware\VerifyShopifyAuthentication;
use App\Models\AdminSetting;
use App\Models\Shop;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function generateShopifyTestJwt(
    string $shop = 'store-a.myshopify.com',
    string $apiKey = 'test-api-key',
    string $apiSecret = 'test-api-secret',
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
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
    ]);

    // Clear cached settings
    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

    if (!\Illuminate\Support\Facades\Schema::hasTable('shops')) {
        \Illuminate\Support\Facades\Schema::create('shops', function (\Illuminate\Database\Schema\Blueprint $table) {
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

    Http::fake([
        '*graphql.json*' => Http::response(['data' => ['shop' => ['id' => '1', 'name' => 'Store']]], 200),
    ]);

    // Register a test route protected by the web middleware pipeline (including VerifyShopifyAuthentication & ResolveActiveShop)
    Route::middleware('web')->get('/shopify-auth-test-endpoint', function (Request $request) {
        return response()->json([
            'active_shop'       => $request->attributes->get('active_shop'),
            'active_shop_id'    => $request->attributes->get('active_shop_model')?->id,
            'verified_shop'     => $request->attributes->get('shopify_verified_shop'),
            'auth_source'       => $request->attributes->get('shopify_auth_source'),
        ]);
    });
});

it('Test 1: Valid Store A token resolves Store A correctly', function () {
    $shopA = new Shop();
    $shopA->id = 101;
    $shopA->shop = 'store-a.myshopify.com';
    $shopA->shop_name = 'Store A';
    $shopA->email = 'store@example.com';
    $shopA->is_active = 1;
    $shopA->access_token = 'token-a';

    // Mock validator / DB lookup
    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => 'store-a.myshopify.com',
        'shop_model' => $shopA,
        'payload'    => ['dest' => 'https://store-a.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $token = generateShopifyTestJwt('store-a.myshopify.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept'        => 'application/json',
    ])->get('/shopify-auth-test-endpoint');

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['active_shop'])->toBe('store-a.myshopify.com');
    expect($data['verified_shop'])->toBe('store-a.myshopify.com');
    expect($data['auth_source'])->toBe('bearer_token');
});

it('Test 2: Valid Store A token + ?shop=Store B query parameter maintains Store A', function () {
    $shopA = new Shop();
    $shopA->id = 101;
    $shopA->shop = 'store-a.myshopify.com';
    $shopA->shop_name = 'Store A';
    $shopA->email = 'store@example.com';
    $shopA->is_active = 1;
    $shopA->access_token = 'token-a';

    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => 'store-a.myshopify.com',
        'shop_model' => $shopA,
        'payload'    => ['dest' => 'https://store-a.myshopify.com'],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    $token = generateShopifyTestJwt('store-a.myshopify.com');

    // Attempt parameter tampering with ?shop=store-b.myshopify.com
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept'        => 'application/json',
    ])->get('/shopify-auth-test-endpoint?shop=store-b.myshopify.com');

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['active_shop'])->toBe('store-a.myshopify.com');
    expect($data['verified_shop'])->toBe('store-a.myshopify.com');
    expect($data['active_shop'])->not->toBe('store-b.myshopify.com');
});

it('Test 3: Forged JWT is rejected with 401', function () {
    $forgedToken = generateShopifyTestJwt('store-a.myshopify.com', 'test-api-key', 'test-api-secret', 300, -60, null, null, null, 'wrong-secret');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$forgedToken}",
        'Accept'        => 'application/json',
    ])->get('/shopify-auth-test-endpoint');

    $response->assertStatus(401);
});

it('Test 4: Expired JWT is rejected with 401', function () {
    $expiredToken = generateShopifyTestJwt('store-a.myshopify.com', 'test-api-key', 'test-api-secret', -100);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$expiredToken}",
        'Accept'        => 'application/json',
    ])->get('/shopify-auth-test-endpoint');

    $response->assertStatus(401);
});

it('Test 5: Invalid audience (aud) is rejected with 401', function () {
    $invalidAudToken = generateShopifyTestJwt('store-a.myshopify.com', 'test-api-key', 'test-api-secret', 300, -60, 'wrong-api-key');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$invalidAudToken}",
        'Accept'        => 'application/json',
    ])->get('/shopify-auth-test-endpoint');

    $response->assertStatus(401);
});

it('Test 6: Invalid destination / issuer is rejected with 401', function () {
    $mismatchedToken = generateShopifyTestJwt('store-a.myshopify.com', 'test-api-key', 'test-api-secret', 300, -60, null, 'https://store-a.myshopify.com', 'https://store-b.myshopify.com/admin');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$mismatchedToken}",
        'Accept'        => 'application/json',
    ])->get('/shopify-auth-test-endpoint');

    $response->assertStatus(401);
});

it('Test 7: Unauthenticated protected request is rejected with 401', function () {
    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->get('/shopify-auth-test-endpoint?shop=store-b.myshopify.com');

    $response->assertStatus(401);
});

it('Test 8: Inactive or nonexistent Shopify shop is rejected with 401', function () {
    // Real validator without mock will query DB, where shop does not exist
    $token = generateShopifyTestJwt('nonexistent-store.myshopify.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept'        => 'application/json',
    ])->get('/shopify-auth-test-endpoint');

    $response->assertStatus(401);
});

it('Test 9: Valid Shopify launch HMAC on entry creates verified session', function () {
    $shopA = new Shop();
    $shopA->id = 101;
    $shopA->shop = 'store-a.myshopify.com';
    $shopA->shop_name = 'Store A';
    $shopA->email = 'store@example.com';
    $shopA->is_active = 1;
    $shopA->access_token = 'token-a';

    $params = [
        'shop'      => 'store-a.myshopify.com',
        'timestamp' => (string) time(),
        'host'      => base64_encode('admin.shopify.com/store/store-a'),
    ];
    ksort($params);
    $hmac = hash_hmac('sha256', urldecode(http_build_query($params)), 'test-api-secret');
    $params['hmac'] = $hmac;
    Shop::create([
        'shop'         => 'store-a.myshopify.com',
        'shop_name'    => 'Store A',
        'email'        => 'store@example.com',
        'access_token' => 'token-a',
        'is_active'    => 1,
    ]);

    $response = $this->get('/?' . http_build_query($params));

    // Entry redirects to dashboard with verified shop
    $response->assertRedirect();
    expect(session('_shopify_verified_shop'))->toBe('store-a.myshopify.com');
    expect(session('active_shop'))->toBe('store-a.myshopify.com');
});

it('Test 10: Valid Store A launch + /dashboard?shop=Store-B maintains Store A', function () {
    Shop::create([
        'shop'                    => 'store-a.myshopify.com',
        'shop_name'               => 'Store A',
        'email'                   => 'store@example.com',
        'access_token'            => 'token-a',
        'access_token_expires_at' => now()->addDays(1),
        'is_active'               => 1,
    ]);
    Shop::create([
        'shop'                    => 'store-b.myshopify.com',
        'shop_name'               => 'Store B',
        'email'                   => 'store-b@example.com',
        'access_token'            => 'token-b',
        'access_token_expires_at' => now()->addDays(1),
        'is_active'               => 1,
    ]);

    $params = [
        'shop'      => 'store-a.myshopify.com',
        'timestamp' => (string) time(),
    ];
    ksort($params);
    $params['hmac'] = hash_hmac('sha256', urldecode(http_build_query($params)), 'test-api-secret');

    // 1. Launch with valid HMAC for Store A
    $launchResponse = $this->get('/?' . http_build_query($params));
    $launchResponse->assertRedirect();
    expect(session('_shopify_verified_shop'))->toBe('store-a.myshopify.com');

    // 2. Follow to protected endpoint with ?shop=store-b.myshopify.com
    $response = $this->withSession([
        '_shopify_verified_shop' => 'store-a.myshopify.com',
    ])->get('/shopify-auth-test-endpoint?shop=store-b.myshopify.com');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['active_shop'])->toBe('store-a.myshopify.com');
    expect($data['verified_shop'])->toBe('store-a.myshopify.com');
    expect($data['active_shop'])->not->toBe('store-b.myshopify.com');
});

it('Test 11: Forged launch HMAC is rejected without authenticating', function () {
    Shop::create([
        'shop'                    => 'store-a.myshopify.com',
        'shop_name'               => 'Store A',
        'email'                   => 'store@example.com',
        'access_token'            => 'token-a',
        'access_token_expires_at' => now()->addDays(1),
        'is_active'               => 1,
    ]);

    $params = [
        'shop'      => 'store-a.myshopify.com',
        'timestamp' => (string) time(),
        'hmac'      => 'forged-invalid-hmac-signature',
    ];

    // On protected route with forged HMAC
    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->get('/shopify-auth-test-endpoint?' . http_build_query($params));

    $response->assertStatus(401);
});

it('Test 12: Expired launch HMAC is rejected without authenticating', function () {
    Shop::create([
        'shop'                    => 'store-a.myshopify.com',
        'shop_name'               => 'Store A',
        'email'                   => 'store@example.com',
        'access_token'            => 'token-a',
        'access_token_expires_at' => now()->addDays(1),
        'is_active'               => 1,
    ]);

    $params = [
        'shop'      => 'store-a.myshopify.com',
        'timestamp' => (string) (time() - 100000), // > 24 hours old
    ];
    ksort($params);
    $params['hmac'] = hash_hmac('sha256', urldecode(http_build_query($params)), 'test-api-secret');

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->get('/shopify-auth-test-endpoint?' . http_build_query($params));

    $response->assertStatus(401);
});

it('Test 13: Direct /?shop=victim.myshopify.com without HMAC redirects to install and does not authenticate victim', function () {
    Shop::create([
        'shop'         => 'victim.myshopify.com',
        'shop_name'    => 'Victim Store',
        'email'        => 'victim@example.com',
        'access_token' => 'victim-token',
        'is_active'    => 1,
    ]);

    // Attacker visits /?shop=victim.myshopify.com without HMAC
    $response = $this->get('/?shop=victim.myshopify.com');

    // Must redirect to install (OAuth), NOT dashboard
    $response->assertRedirect(route('shopify.install', ['shop' => 'victim.myshopify.com']));

    // Must NOT have set verified session for victim
    expect(session('_shopify_verified_shop'))->toBeNull();
});
