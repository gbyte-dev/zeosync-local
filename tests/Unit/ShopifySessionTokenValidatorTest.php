<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use App\Services\ShopifySessionTokenValidator;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-client-id',
        'services.shopify.api_secret' => 'test-client-secret',
    ]);
    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');
});

function createMockToken(
    string $shop = 'test-store.myshopify.com',
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
    $header = ['alg' => $alg, 'typ' => 'JWT'];
    $payload = [
        'iss'  => $iss ?? "https://{$shop}/admin",
        'dest' => $dest ?? "https://{$shop}",
        'aud'  => $aud ?? $apiKey,
        'sub'  => '998877',
        'exp'  => time() + $expOffset,
        'nbf'  => time() + $nbfOffset,
        'iat'  => time(),
    ];

    $b64 = function ($data) {
        return rtrim(strtr(base64_encode(is_string($data) ? $data : json_encode($data)), '+/', '-_'), '=');
    };

    $h = $b64($header);
    $p = $b64($payload);
    $secret = $customSecret ?? $apiSecret;
    $s = $b64(hash_hmac('sha256', "{$h}.{$p}", $secret, true));

    return "{$h}.{$p}.{$s}";
}

it('normalizes shop domains correctly', function () {
    $validator = new ShopifySessionTokenValidator();

    expect($validator->normalizeShopDomain('MY-STORE.myshopify.com'))->toBe('my-store.myshopify.com');
    expect($validator->normalizeShopDomain('https://my-store.myshopify.com/admin'))->toBe('my-store.myshopify.com');
    expect($validator->normalizeShopDomain('my-store'))->toBe('my-store.myshopify.com');
    expect($validator->normalizeShopDomain(''))->toBeNull();
    expect($validator->normalizeShopDomain('invalid domain!'))->toBeNull();
});

it('rejects tokens with forged signature', function () {
    $validator = new ShopifySessionTokenValidator();
    $token = createMockToken('valid.myshopify.com', 'test-client-id', 'test-client-secret', 300, -60, null, null, null, 'wrong-secret');

    expect($validator->validate($token))->toBeNull();
});

it('rejects expired tokens', function () {
    $validator = new ShopifySessionTokenValidator();
    $token = createMockToken('valid.myshopify.com', 'test-client-id', 'test-client-secret', -100);

    expect($validator->validate($token))->toBeNull();
});

it('rejects tokens with wrong audience', function () {
    $validator = new ShopifySessionTokenValidator();
    $token = createMockToken('valid.myshopify.com', 'test-client-id', 'test-client-secret', 300, -60, 'different-client-id');

    expect($validator->validate($token))->toBeNull();
});

it('rejects tokens with non-HS256 algorithm', function () {
    $validator = new ShopifySessionTokenValidator();
    $token = createMockToken('valid.myshopify.com', 'test-client-id', 'test-client-secret', 300, -60, null, null, null, null, 'none');

    expect($validator->validate($token))->toBeNull();
});
