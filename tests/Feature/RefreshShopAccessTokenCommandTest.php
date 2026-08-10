<?php

use App\Models\Shop;
use Illuminate\Support\Facades\Http;

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
