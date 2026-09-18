<?php

use App\Http\Controllers\AdminController;
use App\Models\MailTemplate;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\EmailService;
use App\Services\ShopifyBillingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
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

    if (!Schema::hasTable('mail_templates')) {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->text('plain_text')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    Shop::query()->delete();
    ShopSubscription::query()->delete();
    MailTemplate::query()->delete();
});

it('cancels an active Shopify subscription successfully from admin and updates local DB', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 2,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/12345',
        'status' => 'active',
        'price' => 29.00,
        'started_at' => now(),
        'activated_at' => now(),
    ]);

    Http::fake([
        'https://test-store.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'data' => [
                'appSubscriptionCancel' => [
                    'appSubscription' => [
                        'id' => 'gid://shopify/AppSubscription/12345',
                        'status' => 'CANCELLED',
                    ],
                    'userErrors' => [],
                ],
            ],
        ], 200),
    ]);

    $controller = new AdminController();
    $response = $controller->cancel($shop);

    expect($response->status())->toBe(302);
    expect(session('success'))->toBe('Subscription cancelled successfully.');

    $freshSub = $subscription->fresh();
    expect($freshSub->status)->toBe('cancelled')
        ->and((float) $freshSub->price)->toBe(0.0)
        ->and($freshSub->cancelled_at)->not->toBeNull()
        ->and($freshSub->ended_at)->not->toBeNull();
});

it('does not update local DB when Shopify returns top-level GraphQL errors', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 2,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/12345',
        'status' => 'active',
        'price' => 29.00,
    ]);

    Http::fake([
        'https://test-store.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'errors' => [
                ['message' => 'Access denied for appSubscriptionCancel.'],
            ],
        ], 200),
    ]);

    $controller = new AdminController();
    $response = $controller->cancel($shop);

    expect($response->status())->toBe(302);
    expect(session('error'))->toContain('Shopify subscription cancellation failed');

    $freshSub = $subscription->fresh();
    expect($freshSub->status)->toBe('active')
        ->and($freshSub->cancelled_at)->toBeNull();
});

it('does not update local DB when Shopify returns userErrors', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 2,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/12345',
        'status' => 'active',
        'price' => 29.00,
    ]);

    Http::fake([
        'https://test-store.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'data' => [
                'appSubscriptionCancel' => [
                    'appSubscription' => null,
                    'userErrors' => [
                        ['field' => ['id'], 'message' => 'Subscription is not active.'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $controller = new AdminController();
    $response = $controller->cancel($shop);

    expect($response->status())->toBe(302);
    expect(session('error'))->toContain('Shopify subscription cancellation failed');

    $freshSub = $subscription->fresh();
    expect($freshSub->status)->toBe('active')
        ->and($freshSub->cancelled_at)->toBeNull();
});

it('handles missing access token without updating local DB', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => null,
        'is_active' => 1,
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 2,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/12345',
        'status' => 'active',
        'price' => 29.00,
    ]);

    $controller = new AdminController();
    $response = $controller->cancel($shop);

    expect($response->status())->toBe(302);
    expect(session('error'))->toContain('access token');

    $freshSub = $subscription->fresh();
    expect($freshSub->status)->toBe('active');
});

it('handles HTTP connection exceptions gracefully without updating DB', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 2,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/12345',
        'status' => 'active',
        'price' => 29.00,
    ]);

    Http::fake([
        'https://test-store.myshopify.com/admin/api/*/graphql.json' => function () {
            throw new ConnectionException('Could not resolve host');
        },
    ]);

    $controller = new AdminController();
    $response = $controller->cancel($shop);

    expect($response->status())->toBe(302);
    expect(session('error'))->toContain('Shopify subscription cancellation failed');

    $freshSub = $subscription->fresh();
    expect($freshSub->status)->toBe('active');
});

it('successfully cancels subscription even if email notification fails', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 2,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/12345',
        'status' => 'active',
        'price' => 29.00,
    ]);

    Http::fake([
        'https://test-store.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'data' => [
                'appSubscriptionCancel' => [
                    'appSubscription' => [
                        'id' => 'gid://shopify/AppSubscription/12345',
                        'status' => 'CANCELLED',
                    ],
                    'userErrors' => [],
                ],
            ],
        ], 200),
    ]);

    MailTemplate::create([
        'name' => 'Payment Cancelled',
        'slug' => 'payment-cancelled',
        'subject' => 'Subscription Cancelled',
        'body' => 'Your subscription has been cancelled.',
        'is_active' => 1,
    ]);

    $mockEmailService = Mockery::mock(EmailService::class);
    $mockEmailService->shouldReceive('sendDynamicEmail')
        ->once()
        ->andThrow(new \Exception('SMTP Server connection timed out'));
    app()->instance(EmailService::class, $mockEmailService);

    $controller = new AdminController();
    $response = $controller->cancel($shop);

    expect($response->status())->toBe(302);
    expect(session('success'))->toBe('Subscription cancelled successfully.');

    $freshSub = $subscription->fresh();
    expect($freshSub->status)->toBe('cancelled')
        ->and($freshSub->cancelled_at)->not->toBeNull();
});

it('returns error when shop has no active subscription', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $controller = new AdminController();
    $response = $controller->cancel($shop);

    expect($response->status())->toBe(302);
    expect(session('error'))->toBe('No active Shopify subscription found.');
});

it('handles empty or whitespace GID gracefully without updating DB', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $billingService = app(ShopifyBillingService::class);
    $result = $billingService->cancelSubscription($shop, '   ');

    expect($result)->toBeFalse();
});

it('verifies merchant cancellation flow via PlanController still works', function () {
    $shop = Shop::create([
        'shop' => 'merchant-store.myshopify.com',
        'shop_name' => 'Merchant Store',
        'email' => 'merchant@store.com',
        'access_token' => 'shpat_valid_merchant_token',
        'is_active' => 1,
    ]);

    $subscription = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => 1,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/99988',
        'status' => 'active',
        'price' => 19.00,
    ]);

    Http::fake([
        'https://merchant-store.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'data' => [
                'appSubscriptionCancel' => [
                    'appSubscription' => [
                        'id' => 'gid://shopify/AppSubscription/99988',
                        'status' => 'CANCELLED',
                    ],
                    'userErrors' => [],
                ],
            ],
        ], 200),
    ]);

    $controller = new \App\Http\Controllers\PlanController();
    $request = new \Illuminate\Http\Request(['shop' => 'merchant-store.myshopify.com']);
    $response = $controller->cancel($request);

    expect($response->status())->toBe(302);
    expect(session('success'))->toBe('Subscription cancelled successfully.');

    $freshSub = $subscription->fresh();
    expect($freshSub->status)->toBe('cancelled')
        ->and($freshSub->cancelled_at)->not->toBeNull();
});

it('redirects unauthenticated guest requests to admin login', function () {
    $shop = Shop::create([
        'shop' => 'test-store.myshopify.com',
        'shop_name' => 'Test Store',
        'email' => 'merchant@test.com',
        'access_token' => 'shpat_valid_token',
        'is_active' => 1,
    ]);

    $response = $this->post(route('admin.shops.cancel', $shop->id));

    expect($response->status())->toBe(302);
    $response->assertRedirect(route('admin.login'));
});

