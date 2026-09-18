<?php

use App\Http\Controllers\AdminController;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\ShopifyBillingService;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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
            $table->string('shopify_connection_status')->nullable();
            $table->string('store_status')->nullable();
            $table->timestamp('last_status_check_at')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('shopify_subscription_gid')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('billing_cycle_months')->default(1);
            $table->string('currency_code')->default('USD');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    Shop::query()->delete();
    ShopSubscription::query()->delete();
});

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

    expect(session('success'))->toContain('Shop status changed to inactive');
    expect($response->status())->toBe(302);
});
