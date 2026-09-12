<?php

use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // The shops table is already created by the real migrations via
    // RefreshDatabase; only create it if it does not exist.
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
    }
});

it('refreshes expiring shop access tokens hourly', function () {
    Http::fake([
        'https://example-shop.myshopify.com/admin/oauth/access_token' => Http::response([
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
            'refresh_token' => 'fresh-refresh-token',
            'refresh_token_expires_in' => 90 * 24 * 60 * 60,
        ], 200),
    ]);

    $shop = Shop::create([
        'shop' => 'example-shop.myshopify.com',
        'access_token' => 'old-access-token',
        'access_token_expires_at' => now()->subMinute(),
        'refresh_token' => 'old-refresh-token',
        'refresh_token_expires_at' => now()->addDays(30),
        'is_active' => 1,
    ]);

    $this->artisan('shops:refresh-access-token')->assertSuccessful();

    $shop->refresh();

    expect($shop->access_token)->toBe('new-access-token')
        ->and($shop->refresh_token)->toBe('fresh-refresh-token')
        ->and($shop->access_token_expires_at->isFuture())->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example-shop.myshopify.com/admin/oauth/access_token'
            && $request['grant_type'] === 'refresh_token';
    });
});
