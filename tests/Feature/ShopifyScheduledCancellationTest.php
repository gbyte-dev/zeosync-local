<?php

use App\Http\Middleware\CheckSubscription;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AIFeatureService;
use App\Services\ImageLimitService;
use App\Services\ProductLimitService;
use App\Services\SubscriptionService;
use App\Services\SyncLimitService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
            $table->json('previous_activation_details')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('mail_templates')) {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable();
            $table->string('name')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->text('features')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->json('prices')->nullable();
            $table->integer('sync_limit')->default(100);
            $table->integer('product_limit')->default(100);
            $table->integer('image_limit')->default(100);
            $table->integer('trial_days')->default(0);
            $table->boolean('is_trial')->default(0);
            $table->boolean('is_custom')->default(0);
            $table->boolean('is_highlighted')->default(0);
            $table->boolean('is_active')->default(1);
            $table->boolean('is_enterprise')->default(0);
            $table->boolean('ai_autofill')->default(1);
            $table->boolean('ai_single_field')->default(1);
            $table->string('contact_button_text')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('shopify_subscription_gid')->nullable();
            $table->string('shopify_confirmation_url')->nullable();
            $table->string('shopify_return_url')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('billing_cycle_months')->default(1);
            $table->string('billing_interval')->nullable();
            $table->string('currency_code')->default('USD');
            $table->integer('trial_days')->default(0);
            $table->boolean('is_trial')->default(0);
            $table->boolean('trial_used')->default(0);
            $table->boolean('is_test')->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('requested_plan_id')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('allproducts')) {
        Schema::create('allproducts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_variant_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('images')) {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->nullable();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    Shop::query()->delete();
    ShopSubscription::query()->delete();
    Plan::query()->delete();
});

test('1. active paid subscription grants entitlement and allows access', function () {
    $shop = Shop::create(['shop' => 'active-shop.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00, 'product_limit' => 500, 'sync_limit' => 500]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(20),
        'is_trial' => 0,
    ]);

    $service = app(SubscriptionService::class);
    expect($service->hasActiveEntitlement($shop->id))->toBeTrue();
    expect(isSubscriptionActive($shop->id))->toBeTrue();
    expect($service->isCancellationScheduled($service->getActiveSubscription($shop->id)))->toBeFalse();
});

test('2. active paid subscription with scheduled cancellation in future grants entitlement', function () {
    $shop = Shop::create(['shop' => 'cancel-scheduled.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00, 'product_limit' => 500, 'sync_limit' => 500]);

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subDay(),
        'current_period_end' => now()->addDays(15),
        'is_trial' => 0,
    ]);

    $service = app(SubscriptionService::class);
    expect($service->hasActiveEntitlement($shop->id))->toBeTrue();
    expect(isSubscriptionActive($shop->id))->toBeTrue();
    expect($service->isCancellationScheduled($sub))->toBeTrue();
});

test('3. pending_cancel status with future period end grants entitlement', function () {
    $shop = Shop::create(['shop' => 'pending-cancel.myshopify.com']);
    $plan = Plan::create(['name' => 'Starter Plan', 'price' => 19.00]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'pending_cancel',
        'cancelled_at' => now(),
        'current_period_end' => now()->addDays(10),
        'is_trial' => 0,
    ]);

    $service = app(SubscriptionService::class);
    expect($service->hasActiveEntitlement($shop->id))->toBeTrue();
    expect(isSubscriptionActive($shop->id))->toBeTrue();
});

test('4. expired current_period_end denies entitlement and marks status expired', function () {
    $shop = Shop::create(['shop' => 'expired-shop.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00]);

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_end' => now()->subMinutes(5),
        'is_trial' => 0,
    ]);

    $service = app(SubscriptionService::class);
    expect($service->hasActiveEntitlement($shop->id))->toBeFalse();
    expect(isSubscriptionActive($shop->id))->toBeFalse();

    $sub->refresh();
    expect($sub->status)->toBe('expired');
    expect($sub->ended_at)->not->toBeNull();
});

test('5. cancelled subscription with past current_period_end denies entitlement', function () {
    $shop = Shop::create(['shop' => 'cancelled-past.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00]);

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subMonth(),
        'current_period_end' => now()->subDay(),
        'is_trial' => 0,
    ]);

    $service = app(SubscriptionService::class);
    expect($service->hasActiveEntitlement($shop->id))->toBeFalse();
    expect(isSubscriptionActive($shop->id))->toBeFalse();

    $sub->refresh();
    expect($sub->status)->toBe('expired');
});

test('6. active trial with future trial_ends_at grants entitlement', function () {
    $shop = Shop::create(['shop' => 'trial-active.myshopify.com']);
    $plan = Plan::create(['name' => 'Trial Plan', 'is_trial' => 1, 'trial_days' => 4]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
        'is_trial' => 1,
        'trial_used' => 0,
        'trial_ends_at' => now()->addDays(3),
    ]);

    $service = app(SubscriptionService::class);
    expect($service->hasActiveEntitlement($shop->id))->toBeTrue();
    expect(isSubscriptionActive($shop->id))->toBeTrue();
});

test('7. expired trial denies entitlement and sets trial_used = 1', function () {
    $shop = Shop::create(['shop' => 'trial-expired.myshopify.com']);
    $plan = Plan::create(['name' => 'Trial Plan', 'is_trial' => 1, 'trial_days' => 4]);

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
        'is_trial' => 1,
        'trial_used' => 0,
        'trial_ends_at' => now()->subHour(),
    ]);

    $service = app(SubscriptionService::class);
    expect($service->hasActiveEntitlement($shop->id))->toBeFalse();
    expect(isSubscriptionActive($shop->id))->toBeFalse();

    $sub->refresh();
    expect($sub->status)->toBe('expired');
    expect((int) $sub->trial_used)->toBe(1);
    expect((int) $sub->is_trial)->toBe(0);
});

test('8. paid subscription cancellation does not modify trial_used flag', function () {
    $shop = Shop::create(['shop' => 'paid-cancel-trial-check.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00]);

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'current_period_end' => now()->addDays(20),
        'is_trial' => 0,
        'trial_used' => 1,
    ]);

    $service = app(SubscriptionService::class);
    $service->hasActiveEntitlement($shop->id);

    $sub->refresh();
    expect((int) $sub->trial_used)->toBe(1);
    expect((int) $sub->is_trial)->toBe(0);
});

test('9. ProductLimitService allows creation during scheduled cancellation', function () {
    $shop = Shop::create(['shop' => 'product-limit-check.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00, 'product_limit' => 100]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'activated_at' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
        'is_trial' => 0,
    ]);

    $productLimitService = app(ProductLimitService::class);
    $result = $productLimitService->canCreateProduct($shop->id);

    expect($result['allowed'])->toBeTrue();
    expect($result['limit'])->toBe(100);
});

test('10. ProductLimitService denies creation when subscription is expired', function () {
    $shop = Shop::create(['shop' => 'product-limit-expired.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00, 'product_limit' => 100]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subMonth(),
        'activated_at' => now()->subMonth(),
        'current_period_end' => now()->subDay(),
        'is_trial' => 0,
    ]);

    $productLimitService = app(ProductLimitService::class);
    $result = $productLimitService->canCreateProduct($shop->id);

    expect($result['allowed'])->toBeFalse();
    expect($result['message'])->toBe('No active subscription found.');
});

test('11. SyncLimitService allows mapping during scheduled cancellation', function () {
    $shop = Shop::create(['shop' => 'sync-limit-check.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00, 'sync_limit' => 250]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'started_at' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
        'is_trial' => 0,
    ]);

    $syncLimitService = app(SyncLimitService::class);
    $result = $syncLimitService->canMap($shop);

    expect($result['allowed'])->toBeTrue();
    expect($result['limit'])->toBe(250);
});

test('12. ImageLimitService returns plan limit info during scheduled cancellation', function () {
    $shop = Shop::create(['shop' => 'image-limit-check.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00, 'image_limit' => 50]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'started_at' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
        'is_trial' => 0,
    ]);

    $imageLimitService = app(ImageLimitService::class);
    $result = $imageLimitService->getImageLimitInfo($shop);

    expect($result['has_active_plan'])->toBeTrue();
    expect($result['limit'])->toBe(50);
});

test('13. AIFeatureService allows autofill during scheduled cancellation', function () {
    $shop = Shop::create(['shop' => 'ai-feature-check.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00, 'ai_autofill' => 1, 'ai_single_field' => 1]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'current_period_end' => now()->addDays(20),
        'is_trial' => 0,
    ]);

    $aiService = app(AIFeatureService::class);
    expect($aiService->canUseAutoFill($shop->id))->toBeTrue();
    expect($aiService->canUseSingleField($shop->id))->toBeTrue();
});

test('14. CheckSubscription middleware allows request during scheduled cancellation', function () {
    $shop = Shop::create(['shop' => 'middleware-check.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'current_period_end' => now()->addDays(15),
        'is_trial' => 0,
    ]);

    $request = Request::create('/inventory', 'GET');
    $request->attributes->set('active_shop_model', $shop);

    $middleware = new CheckSubscription();
    $response = $middleware->handle($request, function ($req) {
        return response('OK', 200);
    });

    expect($response->getStatusCode())->toBe(200);
});

test('15. CheckSubscription middleware denies request when period end is past', function () {
    $shop = Shop::create(['shop' => 'middleware-deny.myshopify.com']);
    $plan = Plan::create(['name' => 'Growth Plan', 'price' => 29.00]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subMonth(),
        'current_period_end' => now()->subDay(),
        'is_trial' => 0,
    ]);

    $request = Request::create('/inventory', 'GET');
    $request->attributes->set('active_shop_model', $shop);

    $middleware = new CheckSubscription();
    $response = $middleware->handle($request, function ($req) {
        return response('OK', 200);
    });

    expect($response->isRedirect())->toBeTrue();
});

test('16. Plan reactivation or new subscription clears cancelled_at and sets active status', function () {
    $shop = Shop::create(['shop' => 'reactivate.myshopify.com']);
    $oldPlan = Plan::create(['name' => 'Starter Plan', 'price' => 19.00]);
    $newPlan = Plan::create(['name' => 'Scale Plan', 'price' => 49.00]);

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $oldPlan->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subDays(5),
        'current_period_end' => now()->addDays(10),
        'is_trial' => 0,
    ]);

    // Simulate new approval confirmation / sync
    $sub->update([
        'plan_id' => $newPlan->id,
        'status' => 'active',
        'cancelled_at' => null,
        'current_period_end' => now()->addDays(30),
    ]);

    $service = app(SubscriptionService::class);
    $activeSub = $service->getActiveSubscription($shop->id);

    expect($activeSub)->not->toBeNull();
    expect($activeSub->plan_id)->toBe($newPlan->id);
    expect($activeSub->cancelled_at)->toBeNull();
    expect($service->isCancellationScheduled($activeSub))->toBeFalse();
});

test('17. isCancellationScheduled correctly separates scheduled cancellation from trial and plain active', function () {
    $service = app(SubscriptionService::class);

    $trialSub = new ShopSubscription([
        'is_trial' => 1,
        'status' => 'trialing',
        'trial_ends_at' => now()->addDays(3),
    ]);
    expect($service->isCancellationScheduled($trialSub))->toBeFalse();

    $activeSub = new ShopSubscription([
        'is_trial' => 0,
        'status' => 'active',
        'cancelled_at' => null,
        'current_period_end' => now()->addDays(20),
    ]);
    expect($service->isCancellationScheduled($activeSub))->toBeFalse();

    $scheduledSub = new ShopSubscription([
        'is_trial' => 0,
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'current_period_end' => now()->addDays(20),
    ]);
    expect($service->isCancellationScheduled($scheduledSub))->toBeTrue();
});

test('18. TEST 1: sync Shopify CANCELLED with null currentPeriodEnd preserves future current_period_end and grants entitlement', function () {
    $shop = Shop::create([
        'shop' => 'test1-sync.myshopify.com',
        'access_token' => 'shpat_test_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
        'product_limit' => 500,
        'sync_limit' => 500,
    ]);

    $futureDate = now()->addDays(20)->startOfSecond();

    $localSub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/111',
        'status' => 'active',
        'current_period_end' => $futureDate,
        'is_trial' => 0,
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*/admin/api/*/graphql.json' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                'currentAppInstallation' => [
                    'allSubscriptions' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/AppSubscription/111',
                                    'name' => 'Growth Plan',
                                    'status' => 'CANCELLED',
                                    'test' => false,
                                    'createdAt' => now()->subDays(10)->toIso8601String(),
                                    'currentPeriodEnd' => null,
                                    'lineItems' => [
                                        [
                                            'id' => 'gid://shopify/AppSubscriptionLineItem/1',
                                            'plan' => [
                                                'pricingDetails' => [
                                                    '__typename' => 'AppRecurringPricing',
                                                    'interval' => 'EVERY_30_DAYS',
                                                    'price' => [
                                                        'amount' => 29.00,
                                                        'currencyCode' => 'USD',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $billingService = app(\App\Services\ShopifyBillingService::class);
    $synced = $billingService->syncSubscription($shop, $localSub);

    expect($synced)->not->toBeNull();
    expect($synced->status)->toBe('cancelled');
    expect($synced->current_period_end)->not->toBeNull();
    expect($synced->current_period_end->toIso8601String())->toBe($futureDate->toIso8601String());

    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeTrue();
});

test('19. TEST 2: same cancelled subscription after current_period_end denies entitlement', function () {
    $shop = Shop::create([
        'shop' => 'test2-expired.myshopify.com',
        'access_token' => 'shpat_test_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    $localSub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/222',
        'status' => 'cancelled',
        'cancelled_at' => now()->subDays(31),
        'current_period_end' => now()->subMinute(),
        'is_trial' => 0,
    ]);

    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeFalse();

    $localSub->refresh();
    expect($localSub->status)->toBe('expired');
});

test('20. TEST 3: plans page after cancellation displays current plan and cancellation scheduled banner', function () {
    $shop = Shop::create([
        'shop' => 'test3-plans-ui.myshopify.com',
        'access_token' => 'shpat_test_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
        'prices' => ['EVERY_30_DAYS' => 29.00, 'ANNUAL' => 290.00],
    ]);

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/333',
        'status' => 'cancelled',
        'cancelled_at' => now()->subDay(),
        'current_period_end' => now()->addDays(20),
        'is_trial' => 0,
    ]);

    $viewHtml = view('plans', [
        'plans' => [$plan],
        'customPlan' => null,
        'subscription' => $sub,
        'activeShop' => $shop->shop,
        'billingOptions' => [],
        'cspNonce' => 'test-csp-nonce',
        'errors' => new \Illuminate\Support\ViewErrorBag(),
    ])->render();

    expect($viewHtml)->toContain('Cancellation scheduled');
    expect($viewHtml)->toContain('Growth Plan');
    expect($viewHtml)->not->toContain('No active plan');
});

test('21. TEST 4: normal ACTIVE sync with non-null currentPeriodEnd persists Shopify value', function () {
    $shop = Shop::create([
        'shop' => 'test4-active-sync.myshopify.com',
        'access_token' => 'shpat_test_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    $shopifyPeriodEnd = now()->addDays(28)->startOfSecond();

    $localSub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/444',
        'status' => 'active',
        'current_period_end' => now()->addDays(5),
        'is_trial' => 0,
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*/admin/api/*/graphql.json' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                'currentAppInstallation' => [
                    'allSubscriptions' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/AppSubscription/444',
                                    'name' => 'Growth Plan',
                                    'status' => 'ACTIVE',
                                    'test' => false,
                                    'createdAt' => now()->subDays(2)->toIso8601String(),
                                    'currentPeriodEnd' => $shopifyPeriodEnd->toIso8601String(),
                                    'lineItems' => [
                                        [
                                            'id' => 'gid://shopify/AppSubscriptionLineItem/4',
                                            'plan' => [
                                                'pricingDetails' => [
                                                    '__typename' => 'AppRecurringPricing',
                                                    'interval' => 'EVERY_30_DAYS',
                                                    'price' => [
                                                        'amount' => 29.00,
                                                        'currencyCode' => 'USD',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $billingService = app(\App\Services\ShopifyBillingService::class);
    $synced = $billingService->syncSubscription($shop, $localSub);

    expect($synced->status)->toBe('active');
    expect($synced->current_period_end->toIso8601String())->toBe($shopifyPeriodEnd->toIso8601String());
});

test('22. TEST 5: immediate cancellation or genuinely expired subscription revokes access', function () {
    $shop = Shop::create([
        'shop' => 'test5-revoked.myshopify.com',
        'access_token' => 'shpat_test_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'expired',
        'current_period_end' => now()->subDays(5),
        'ended_at' => now()->subDays(5),
        'is_trial' => 0,
    ]);

    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeFalse();
    expect(isSubscriptionActive($shop->id))->toBeFalse();
});

test('23. TEST 6: trial subscription preserves existing trial behavior unchanged', function () {
    $shop = Shop::create(['shop' => 'test6-trial.myshopify.com']);
    $plan = Plan::create([
        'name' => 'Trial Plan',
        'is_trial' => 1,
        'trial_days' => 4,
        'price' => 0,
    ]);

    $activeTrial = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
        'is_trial' => 1,
        'trial_used' => 0,
        'trial_ends_at' => now()->addDays(2),
    ]);

    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeTrue();

    // Now advance trial to expired
    $activeTrial->update([
        'trial_ends_at' => now()->subHour(),
    ]);

    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeFalse();

    $activeTrial->refresh();
    expect($activeTrial->status)->toBe('expired');
    expect((int) $activeTrial->trial_used)->toBe(1);
    expect((int) $activeTrial->is_trial)->toBe(0);
});

test('24. TEST 7: repeated Plans page sync after cancellation never nulls current_period_end', function () {
    $shop = Shop::create([
        'shop' => 'test7-repeated-sync.myshopify.com',
        'access_token' => 'shpat_test_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    $futurePeriodEnd = now()->addDays(18)->startOfSecond();

    $localSub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/777',
        'status' => 'cancelled',
        'cancelled_at' => now()->subHours(2),
        'current_period_end' => $futurePeriodEnd,
        'is_trial' => 0,
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*/admin/api/*/graphql.json' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                'currentAppInstallation' => [
                    'allSubscriptions' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/AppSubscription/777',
                                    'name' => 'Growth Plan',
                                    'status' => 'CANCELLED',
                                    'test' => false,
                                    'createdAt' => now()->subDays(12)->toIso8601String(),
                                    'currentPeriodEnd' => null,
                                    'lineItems' => [
                                        [
                                            'id' => 'gid://shopify/AppSubscriptionLineItem/7',
                                            'plan' => [
                                                'pricingDetails' => [
                                                    '__typename' => 'AppRecurringPricing',
                                                    'interval' => 'EVERY_30_DAYS',
                                                    'price' => [
                                                        'amount' => 29.00,
                                                        'currencyCode' => 'USD',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $billingService = app(\App\Services\ShopifyBillingService::class);

    // Sync 1
    $synced1 = $billingService->syncSubscription($shop, $localSub);
    expect($synced1->current_period_end)->not->toBeNull();
    expect($synced1->current_period_end->toIso8601String())->toBe($futurePeriodEnd->toIso8601String());

    // Sync 2
    $synced2 = $billingService->syncSubscription($shop, $synced1);
    expect($synced2->current_period_end)->not->toBeNull();
    expect($synced2->current_period_end->toIso8601String())->toBe($futurePeriodEnd->toIso8601String());

    // Sync 3
    $synced3 = $billingService->syncSubscription($shop, $synced2);
    expect($synced3->current_period_end)->not->toBeNull();
    expect($synced3->current_period_end->toIso8601String())->toBe($futurePeriodEnd->toIso8601String());

    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeTrue();
});

test('25. TEST 8: existing future current_period_end + Shopify CANCELLED/null produces no destructive overwrite', function () {
    $shop = Shop::create([
        'shop' => 'test8-no-overwrite.myshopify.com',
        'access_token' => 'shpat_test_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    $futurePeriodEnd = now()->addDays(25)->startOfSecond();

    $localSub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/888',
        'status' => 'active',
        'current_period_end' => $futurePeriodEnd,
        'is_trial' => 0,
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*/admin/api/*/graphql.json' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                'currentAppInstallation' => [
                    'allSubscriptions' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/AppSubscription/888',
                                    'name' => 'Growth Plan',
                                    'status' => 'CANCELLED',
                                    'test' => false,
                                    'createdAt' => now()->subDays(5)->toIso8601String(),
                                    'currentPeriodEnd' => null,
                                    'lineItems' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $billingService = app(\App\Services\ShopifyBillingService::class);
    $synced = $billingService->syncSubscription($shop, $localSub);

    expect($synced->status)->toBe('cancelled');
    expect($synced->current_period_end)->not->toBeNull();
    expect($synced->current_period_end->toIso8601String())->toBe($futurePeriodEnd->toIso8601String());
});

test('26. APP_UNINSTALLED webhook transitions active subscription to cancelled and preserves current_period_end', function () {
    $shop = Shop::create([
        'shop' => 'uninstall-active.myshopify.com',
        'is_active' => 1,
        'access_token' => 'shpat_uninstall_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    $futurePeriodEnd = now()->addDays(20)->startOfSecond();

    $sub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/999',
        'status' => 'active',
        'current_period_end' => $futurePeriodEnd,
        'is_trial' => 0,
    ]);

    $webhookMock = Mockery::mock(\App\Services\ShopifyWebhookService::class);
    $webhookMock->shouldReceive('isValidWebhook')->andReturn(true);
    app()->instance(\App\Services\ShopifyWebhookService::class, $webhookMock);

    $request = Request::create('/webhooks/app-uninstalled', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'uninstall-active.myshopify.com',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => 'valid_hmac',
    ], json_encode(['id' => 12345]));

    $controller = app(\App\Http\Controllers\ShopifyController::class);
    $response = $controller->handleAppUninstalledWebhook($request);

    expect($response->getStatusCode())->toBe(200);

    $sub->refresh();
    expect($sub->status)->toBe('cancelled');
    expect($sub->cancelled_at)->not->toBeNull();
    expect($sub->current_period_end->toIso8601String())->toBe($futurePeriodEnd->toIso8601String());
    expect($sub->plan_id)->toBe($plan->id);

    // Subscription entitlement is still valid before current_period_end
    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeTrue();
});

test('27. APP_UNINSTALLED webhook without active subscription leaves subscriptions empty', function () {
    $shop = Shop::create([
        'shop' => 'uninstall-nosub.myshopify.com',
        'is_active' => 1,
        'access_token' => 'shpat_uninstall_token',
    ]);

    $webhookMock = Mockery::mock(\App\Services\ShopifyWebhookService::class);
    $webhookMock->shouldReceive('isValidWebhook')->andReturn(true);
    app()->instance(\App\Services\ShopifyWebhookService::class, $webhookMock);

    $request = Request::create('/webhooks/app-uninstalled', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'uninstall-nosub.myshopify.com',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => 'valid_hmac',
    ], json_encode(['id' => 12345]));

    $controller = app(\App\Http\Controllers\ShopifyController::class);
    $response = $controller->handleAppUninstalledWebhook($request);

    expect($response->getStatusCode())->toBe(200);
    expect(ShopSubscription::where('shop_id', $shop->id)->count())->toBe(0);
});

test('28. Reinstall with active Shopify subscription synchronizes local DB back to active', function () {
    $shop = Shop::create([
        'shop' => 'reinstall-active.myshopify.com',
        'is_active' => 1,
        'access_token' => 'shpat_reinstall_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    // Local DB previously in cancelled state from uninstall
    $localSub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/555',
        'status' => 'cancelled',
        'cancelled_at' => now()->subDays(5),
        'current_period_end' => now()->addDays(10)->startOfSecond(),
        'is_trial' => 0,
    ]);

    $newShopifyPeriodEnd = now()->addDays(25)->startOfSecond();

    \Illuminate\Support\Facades\Http::fake([
        '*/admin/api/*/graphql.json' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                'currentAppInstallation' => [
                    'allSubscriptions' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/AppSubscription/555',
                                    'name' => 'Growth Plan',
                                    'status' => 'ACTIVE',
                                    'test' => false,
                                    'createdAt' => now()->subDays(1)->toIso8601String(),
                                    'currentPeriodEnd' => $newShopifyPeriodEnd->toIso8601String(),
                                    'lineItems' => [
                                        [
                                            'id' => 'gid://shopify/AppSubscriptionLineItem/555',
                                            'plan' => [
                                                'pricingDetails' => [
                                                    '__typename' => 'AppRecurringPricing',
                                                    'interval' => 'EVERY_30_DAYS',
                                                    'price' => [
                                                        'amount' => 29.00,
                                                        'currencyCode' => 'USD',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $billingService = app(\App\Services\ShopifyBillingService::class);
    $synced = $billingService->syncSubscription($shop);

    expect($synced->status)->toBe('active');
    expect($synced->cancelled_at)->toBeNull();
    expect($synced->current_period_end->toIso8601String())->toBe($newShopifyPeriodEnd->toIso8601String());

    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeTrue();
});

test('29. Reinstall when Shopify has no active subscription does NOT reactivate old scheduled subscription', function () {
    $shop = Shop::create([
        'shop' => 'reinstall-nosub.myshopify.com',
        'is_active' => 1,
        'access_token' => 'shpat_reinstall_token',
    ]);
    $plan = Plan::create([
        'name' => 'Growth Plan',
        'price' => 29.00,
    ]);

    // Local DB previously cancelled from uninstall
    $localSub = ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'shopify_subscription_gid' => 'gid://shopify/AppSubscription/666',
        'status' => 'cancelled',
        'cancelled_at' => now()->subDays(30),
        'current_period_end' => now()->subDay(), // already ended
        'is_trial' => 0,
    ]);

    // Shopify returns no subscriptions
    \Illuminate\Support\Facades\Http::fake([
        '*/admin/api/*/graphql.json' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                'currentAppInstallation' => [
                    'allSubscriptions' => [
                        'edges' => [],
                    ],
                ],
            ],
        ], 200),
    ]);

    $billingService = app(\App\Services\ShopifyBillingService::class);
    $synced = $billingService->syncSubscription($shop);

    // Old subscription status should not become active
    $localSub->refresh();
    expect($localSub->status)->not->toBe('active');

    $subscriptionService = app(\App\Services\SubscriptionService::class);
    expect($subscriptionService->hasActiveEntitlement($shop->id))->toBeFalse();
});



