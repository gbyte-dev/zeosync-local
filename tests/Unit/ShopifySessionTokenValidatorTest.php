<?php

use App\Models\AdminSetting;
use App\Models\Shop;
use App\Services\ShopifySessionTokenValidator;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
    }

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('domain')->nullable();
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

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

it('validates authentic Shopify App Bridge JWT issued from admin.shopify.com unified admin', function () {
    $shop = Shop::create([
        'shop'         => 'unified-store.myshopify.com',
        'domain'       => 'unified-store.myshopify.com',
        'access_token' => 'shpat_unified_test',
        'is_active'    => 1,
    ]);

    $token = createMockToken(
        'unified-store.myshopify.com',
        'test-client-id',
        'test-client-secret',
        300,
        -60,
        'test-client-id',
        'https://unified-store.myshopify.com',
        'https://admin.shopify.com/store/unified-store'
    );

    $validator = new ShopifySessionTokenValidator();
    $result = $validator->validate($token);

    expect($result)->not->toBeNull()
        ->and($result['shop'])->toBe('unified-store.myshopify.com')
        ->and($result['shop_model']->id)->toBe($shop->id);
});

it('validates authentic Shopify App Bridge JWT issued from legacy myshopify.com admin', function () {
    $shop = Shop::create([
        'shop'         => 'legacy-iss-store.myshopify.com',
        'domain'       => 'legacy-iss-store.myshopify.com',
        'access_token' => 'shpat_legacy_test',
        'is_active'    => 1,
    ]);

    $token = createMockToken(
        'legacy-iss-store.myshopify.com',
        'test-client-id',
        'test-client-secret',
        300,
        -60,
        'test-client-id',
        'https://legacy-iss-store.myshopify.com',
        'https://legacy-iss-store.myshopify.com/admin'
    );

    $validator = new ShopifySessionTokenValidator();
    $result = $validator->validate($token);

    expect($result)->not->toBeNull()
        ->and($result['shop'])->toBe('legacy-iss-store.myshopify.com')
        ->and($result['shop_model']->id)->toBe($shop->id);
});

it('rejects admin.shopify.com token with missing /store/{slug} path', function () {
    $shop = Shop::create([
        'shop'         => 'no-slug.myshopify.com',
        'domain'       => 'no-slug.myshopify.com',
        'access_token' => 'shpat_no_slug',
        'is_active'    => 1,
    ]);

    $token = createMockToken(
        'no-slug.myshopify.com',
        'test-client-id',
        'test-client-secret',
        300,
        -60,
        'test-client-id',
        'https://no-slug.myshopify.com',
        'https://admin.shopify.com'
    );

    $validator = new ShopifySessionTokenValidator();
    expect($validator->validate($token))->toBeNull();
});

it('rejects admin.shopify.com token with mismatched store slug vs dest', function () {
    $shop = Shop::create([
        'shop'         => 'victim-store.myshopify.com',
        'domain'       => 'victim-store.myshopify.com',
        'access_token' => 'shpat_victim',
        'is_active'    => 1,
    ]);

    $token = createMockToken(
        'victim-store.myshopify.com',
        'test-client-id',
        'test-client-secret',
        300,
        -60,
        'test-client-id',
        'https://victim-store.myshopify.com',
        'https://admin.shopify.com/store/attacker-store'
    );

    $validator = new ShopifySessionTokenValidator();
    expect($validator->validate($token))->toBeNull();
});

it('rejects tokens with malformed issuer URL', function () {
    $shop = Shop::create([
        'shop'         => 'malformed-iss.myshopify.com',
        'domain'       => 'malformed-iss.myshopify.com',
        'access_token' => 'shpat_malformed',
        'is_active'    => 1,
    ]);

    $token = createMockToken(
        'malformed-iss.myshopify.com',
        'test-client-id',
        'test-client-secret',
        300,
        -60,
        'test-client-id',
        'https://malformed-iss.myshopify.com',
        'not a valid url ///'
    );

    $validator = new ShopifySessionTokenValidator();
    expect($validator->validate($token))->toBeNull();
});
