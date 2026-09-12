<?php

use App\Models\Shop;
use App\Services\ShopifyService;
use App\Services\StoreStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // The shops table (including the Amazon/encryption columns) is already
    // created by the real migrations via RefreshDatabase; only create it if
    // it does not exist.
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
            $table->text('amazon_refresh_token')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->default('na');
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_endpoint')->nullable();
            $table->string('hmac')->nullable();
            $table->string('amazon_oauth_state')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }
});

it('encrypts access_token at rest in the database and decrypts it on Eloquent access', function () {
    $plainToken = 'shpat_secret_access_token_abc123';

    $shop = Shop::create([
        'shop' => 'shop-access-test.myshopify.com',
        'access_token' => $plainToken,
        'is_active' => 1,
    ]);

    // 1. Raw database value must be encrypted and NOT equal to plaintext
    $rawToken = DB::table('shops')->where('id', $shop->id)->value('access_token');
    expect($rawToken)->not->toBeNull()
        ->and($rawToken)->not->toBe($plainToken)
        ->and(Crypt::decryptString($rawToken))->toBe($plainToken);

    // 2. Eloquent model must return the decrypted plaintext value
    $loadedShop = Shop::find($shop->id);
    expect($loadedShop->access_token)->toBe($plainToken);
});

it('encrypts refresh_token at rest in the database and decrypts it on Eloquent access', function () {
    $plainRefreshToken = 'shpat_secret_refresh_token_xyz789';

    $shop = Shop::create([
        'shop' => 'shop-refresh-test.myshopify.com',
        'refresh_token' => $plainRefreshToken,
        'is_active' => 1,
    ]);

    // 1. Raw database value must be ciphertext
    $rawRefreshToken = DB::table('shops')->where('id', $shop->id)->value('refresh_token');
    expect($rawRefreshToken)->not->toBeNull()
        ->and($rawRefreshToken)->not->toBe($plainRefreshToken)
        ->and(Crypt::decryptString($rawRefreshToken))->toBe($plainRefreshToken);

    // 2. Eloquent model must return plaintext
    $loadedShop = Shop::find($shop->id);
    expect($loadedShop->refresh_token)->toBe($plainRefreshToken);
});

it('encrypts amazon_refresh_token at rest in the database and decrypts it on Eloquent access', function () {
    $plainAmazonToken = 'Atzr|amazon_secret_refresh_token_qwerty456';

    $shop = Shop::create([
        'shop' => 'shop-amazon-test.myshopify.com',
        'amazon_refresh_token' => $plainAmazonToken,
        'is_active' => 1,
    ]);

    // 1. Raw database value must be ciphertext
    $rawAmazonToken = DB::table('shops')->where('id', $shop->id)->value('amazon_refresh_token');
    expect($rawAmazonToken)->not->toBeNull()
        ->and($rawAmazonToken)->not->toBe($plainAmazonToken)
        ->and(Crypt::decryptString($rawAmazonToken))->toBe($plainAmazonToken);

    // 2. Eloquent model must return plaintext
    $loadedShop = Shop::find($shop->id);
    expect($loadedShop->amazon_refresh_token)->toBe($plainAmazonToken);
});

it('preserves NULL values for all token fields', function () {
    $shop = Shop::create([
        'shop' => 'shop-null-test.myshopify.com',
        'access_token' => null,
        'refresh_token' => null,
        'amazon_refresh_token' => null,
        'is_active' => 1,
    ]);

    $raw = DB::table('shops')->where('id', $shop->id)->first();
    expect($raw->access_token)->toBeNull()
        ->and($raw->refresh_token)->toBeNull()
        ->and($raw->amazon_refresh_token)->toBeNull();

    $loadedShop = Shop::find($shop->id);
    expect($loadedShop->access_token)->toBeNull()
        ->and($loadedShop->refresh_token)->toBeNull()
        ->and($loadedShop->amazon_refresh_token)->toBeNull();
});

it('allows all three token fields to coexist, encrypt, and decrypt independently', function () {
    $shop = Shop::create([
        'shop' => 'shop-all-tokens.myshopify.com',
        'access_token' => 'access-111',
        'refresh_token' => 'refresh-222',
        'amazon_refresh_token' => 'amazon-333',
        'is_active' => 1,
    ]);

    $raw = DB::table('shops')->where('id', $shop->id)->first();
    expect($raw->access_token)->not->toBe('access-111')
        ->and($raw->refresh_token)->not->toBe('refresh-222')
        ->and($raw->amazon_refresh_token)->not->toBe('amazon-333')
        ->and(Crypt::decryptString($raw->access_token))->toBe('access-111')
        ->and(Crypt::decryptString($raw->refresh_token))->toBe('refresh-222')
        ->and(Crypt::decryptString($raw->amazon_refresh_token))->toBe('amazon-333');

    $loaded = Shop::find($shop->id);
    expect($loaded->access_token)->toBe('access-111')
        ->and($loaded->refresh_token)->toBe('refresh-222')
        ->and($loaded->amazon_refresh_token)->toBe('amazon-333');
});

it('provides decrypted tokens to API services like ShopifyService transparently', function () {
    $plainToken = 'shpat_valid_test_token_999';

    Http::fake([
        'https://example.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'data' => [
                'shop' => ['name' => 'Test Shop']
            ]
        ], 200),
    ]);

    $shop = Shop::create([
        'shop' => 'example.myshopify.com',
        'access_token' => $plainToken,
        'is_active' => 1,
    ]);

    $shopifyService = new ShopifyService($shop->shop, $shop->access_token);
    $response = $shopifyService->graphql('{ shop { name } }');

    expect($response)->toHaveKey('data');

    Http::assertSent(function ($request) use ($plainToken) {
        return $request->hasHeader('X-Shopify-Access-Token', $plainToken);
    });
});

it('migrates legacy plaintext records using shops:encrypt-tokens command', function () {
    // 1. Seed raw database with legacy plaintext tokens (bypassing Eloquent casts)
    $id = DB::table('shops')->insertGetId([
        'shop' => 'legacy-shop.myshopify.com',
        'access_token' => 'legacy_access_plain',
        'refresh_token' => 'legacy_refresh_plain',
        'amazon_refresh_token' => 'legacy_amazon_plain',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verify they are initially stored as raw plaintext
    $rawBefore = DB::table('shops')->where('id', $id)->first();
    expect($rawBefore->access_token)->toBe('legacy_access_plain')
        ->and($rawBefore->refresh_token)->toBe('legacy_refresh_plain')
        ->and($rawBefore->amazon_refresh_token)->toBe('legacy_amazon_plain');

    // 2. Run the encryption migration command
    $this->artisan('shops:encrypt-tokens')->assertSuccessful();

    // 3. Verify raw database is now encrypted ciphertext
    $rawAfter = DB::table('shops')->where('id', $id)->first();
    expect($rawAfter->access_token)->not->toBe('legacy_access_plain')
        ->and($rawAfter->refresh_token)->not->toBe('legacy_refresh_plain')
        ->and($rawAfter->amazon_refresh_token)->not->toBe('legacy_amazon_plain')
        ->and(Crypt::decryptString($rawAfter->access_token))->toBe('legacy_access_plain')
        ->and(Crypt::decryptString($rawAfter->refresh_token))->toBe('legacy_refresh_plain')
        ->and(Crypt::decryptString($rawAfter->amazon_refresh_token))->toBe('legacy_amazon_plain');

    // 4. Verify Eloquent model transparently loads the decrypted values
    $loadedShop = Shop::find($id);
    expect($loadedShop->access_token)->toBe('legacy_access_plain')
        ->and($loadedShop->refresh_token)->toBe('legacy_refresh_plain')
        ->and($loadedShop->amazon_refresh_token)->toBe('legacy_amazon_plain');
});

it('supports --dry-run on shops:encrypt-tokens without modifying records', function () {
    $id = DB::table('shops')->insertGetId([
        'shop' => 'dry-run-shop.myshopify.com',
        'access_token' => 'plain_dry_run_token',
        'refresh_token' => null,
        'amazon_refresh_token' => null,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('shops:encrypt-tokens --dry-run')
        ->expectsOutputToContain('DRY RUN mode')
        ->assertSuccessful();

    $raw = DB::table('shops')->where('id', $id)->first();
    expect($raw->access_token)->toBe('plain_dry_run_token');
});

it('prevents double encryption when shops:encrypt-tokens is run multiple times (idempotency)', function () {
    $id = DB::table('shops')->insertGetId([
        'shop' => 'idempotent-shop.myshopify.com',
        'access_token' => 'plain_test_token',
        'refresh_token' => 'plain_refresh_token',
        'amazon_refresh_token' => null,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // First run
    $this->artisan('shops:encrypt-tokens')->assertSuccessful();

    $rawFirstRun = DB::table('shops')->where('id', $id)->first();
    $firstEncryptedAccess = $rawFirstRun->access_token;
    $firstEncryptedRefresh = $rawFirstRun->refresh_token;

    // Second run
    $this->artisan('shops:encrypt-tokens')
        ->expectsOutputToContain('Already encrypted values skipped:')
        ->assertSuccessful();

    $rawSecondRun = DB::table('shops')->where('id', $id)->first();

    // The raw ciphertext must remain identical (skipped re-encryption)
    expect($rawSecondRun->access_token)->toBe($firstEncryptedAccess)
        ->and($rawSecondRun->refresh_token)->toBe($firstEncryptedRefresh);

    // And Eloquent must still correctly decrypt the original plaintext
    $shop = Shop::find($id);
    expect($shop->access_token)->toBe('plain_test_token')
        ->and($shop->refresh_token)->toBe('plain_refresh_token');
});

it('hides sensitive token fields and security attributes from model serialization', function () {
    $shop = Shop::create([
        'shop' => 'shop-serialization.myshopify.com',
        'access_token' => 'super_secret_access',
        'refresh_token' => 'super_secret_refresh',
        'amazon_refresh_token' => 'super_secret_amazon',
        'hmac' => 'secret_hmac_hash',
        'amazon_oauth_state' => 'oauth_state_123',
        'is_active' => 1,
    ]);

    $array = $shop->toArray();
    expect($array)->not->toHaveKey('access_token')
        ->and($array)->not->toHaveKey('refresh_token')
        ->and($array)->not->toHaveKey('amazon_refresh_token')
        ->and($array)->not->toHaveKey('hmac')
        ->and($array)->not->toHaveKey('amazon_oauth_state')
        ->and($array)->toHaveKey('shop');

    $json = $shop->toJson();
    expect($json)->not->toContain('super_secret_access')
        ->and($json)->not->toContain('super_secret_refresh')
        ->and($json)->not->toContain('super_secret_amazon')
        ->and($json)->not->toContain('secret_hmac_hash')
        ->and($json)->not->toContain('oauth_state_123');
});
