<?php

use App\Http\Controllers\AdminController;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\ShopifyBillingService;
use Illuminate\Http\Request;
use Mockery;

it('deactivates a shop and cancels its active subscription', function () {
    $shop = Shop::create([
        'shop' => 'demo.myshopify.com',
        'shop_name' => 'Demo Shop',
        'email' => 'demo@example.com',
        'is_active' => 1,
        'shopify_connection_status' => 'connected',
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 1,
        'shopify_subscription_gid' => 'gid://shopify/Subscription/123',
        'status' => 'active',
        'price' => 49.99,
        'billing_cycle_months' => 1,
        'currency_code' => 'USD',
        'started_at' => now(),
        'activated_at' => now(),
    ]);

    $billingService = Mockery::mock(ShopifyBillingService::class);
    $billingService->shouldReceive('cancelSubscription')
        ->once()
        ->with($shop, 'gid://shopify/Subscription/123')
        ->andReturnTrue();

    app()->instance(ShopifyBillingService::class, $billingService);

    $response = (new AdminController())->deactivateShop(new Request(), $shop);

    expect($shop->fresh()->is_active)->toBe(0)
        ->and($shop->fresh()->shopify_connection_status)->toBe('inactive')
        ->and($subscription->fresh()->status)->toBe('cancelled');

    expect(session('success'))->toContain('deactivated');
    expect($response->status())->toBe(302);
});
