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

    // Register a test route protected by the authentication & resolve middleware
    Route::middleware([
        \Illuminate\Session\Middleware\StartSession::class,
        VerifyShopifyAuthentication::class,
        ResolveActiveShop::class,
    ])->get('/shopify-auth-test-endpoint', function (Request $request) {
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
