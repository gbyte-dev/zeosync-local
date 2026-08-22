<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\ShopifyBillingService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BillingManager
{
    public function __construct(
        private readonly BillingProvider $provider,
        private readonly ShopifyBillingService $shopifyBilling,
        private readonly StripeService $stripeService,
    ) {}

    /**
     * Subscribe a shop to a plan using the active billing provider.
     *
     * @return array{redirect: string, message?: string}
     */
    public function subscribe(Shop $shop, Plan $plan, string $billingInterval): array
    {
        if ($this->provider->isShopify()) {
            return $this->subscribeViaShopify($shop, $plan, $billingInterval);
        }

        return $this->subscribeViaStripe($shop, $plan, $billingInterval);
    }

    /**
     * Cancel a shop's subscription using the active billing provider.
     */
    public function cancel(Shop $shop): bool
    {
        if ($this->provider->isShopify()) {
            return $this->cancelViaShopify($shop);
        }

        return $this->cancelViaStripe($shop);
    }

    /**
     * Sync the local subscription state from the active billing provider.
     */
    public function sync(Shop $shop, ?ShopSubscription $subscription = null): ?ShopSubscription
    {
        if ($this->provider->isShopify()) {
            return $this->shopifyBilling->syncSubscription($shop, $subscription);
        }

        // Stripe sync is handled by the Stripe webhook; no-op here.
        return $subscription;
    }

    /**
     * Check the current subscription status for the active provider.
     */
    public function checkStatus(Shop $shop): array
    {
        if ($this->provider->isShopify()) {
            $subscription = ShopSubscription::where('shop_id', $shop->id)->first();

            return [
                'status' => $subscription?->status ?? 'not_found',
                'payment_status' => $subscription?->status ?? 'not_found',
            ];
        }

        $subscription = \App\Models\ShopifySubscription::where('shop_id', $shop->id)
            ->latest()
            ->first();

        return [
            'status' => $subscription?->status ?? 'not_found',
            'payment_status' => $subscription?->payment_status ?? 'not_found',
        ];
    }

    private function subscribeViaShopify(Shop $shop, Plan $plan, string $billingInterval): array
    {
        $billingInterval = strtoupper($billingInterval);
        $billingCycleMonths = $billingInterval === 'ANNUAL' ? 12 : 1;

        $existingSubscription = ShopSubscription::with('plan')
            ->where('shop_id', $shop->id)
            ->first();

        try {
            $existingSubscription = $this->shopifyBilling->syncSubscription($shop, $existingSubscription);
        } catch (RuntimeException $exception) {
            Log::warning('Unable to sync Shopify subscription before creating a new billing request.', [
                'shop' => $shop->shop,
                'error' => $exception->getMessage(),
            ]);
        }

        if (
            $existingSubscription &&
            $this->shopifyBilling->isActivatedStatus($existingSubscription->status) &&
            (int) $existingSubscription->plan_id === (int) $plan->id &&
            (string) $existingSubscription->billing_interval === $billingInterval
        ) {
            return [
                'redirect' => $this->shopAwareUrl('/plans', $shop->shop),
                'message' => "{$plan->name} is already active for {$shop->shop}.",
            ];
        }

        $returnUrl = $this->shopifyBilling->buildReturnUrl($shop);

        try {
            $createdSubscription = $this->shopifyBilling->createSubscription(
                $shop,
                $plan,
                $billingInterval,
                $returnUrl
            );
        } catch (RuntimeException $exception) {
            Log::error('Failed to create Shopify app subscription.', [
                'shop' => $shop->shop,
                'plan_id' => $plan->id,
                'billing_interval' => $billingInterval,
                'error' => $exception->getMessage(),
            ]);

            return [
                'redirect' => $this->shopAwareUrl('/plans', $shop->shop),
                'error' => $exception->getMessage(),
            ];
        }

        ShopSubscription::updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'plan_id' => $plan->id,
                'shopify_subscription_gid' => $createdSubscription['subscription_gid'],
                'shopify_confirmation_url' => $createdSubscription['confirmation_url'],
                'shopify_return_url' => $returnUrl,
                'status' => 'pending',
                'price' => $createdSubscription['amount'],
                'billing_cycle_months' => $billingCycleMonths,
                'billing_interval' => $billingInterval,
                'currency_code' => $createdSubscription['currency_code'],
                'trial_days' => (int) config('services.shopify.billing.trial_days', 0),
                'is_test' => (bool) config('services.shopify.billing.test', false),
                'trial_ends_at' => null,
                'started_at' => null,
                'activated_at' => null,
                'current_period_end' => null,
                'ended_at' => null,
                'cancelled_at' => null,
            ]
        );

        return [
            'redirect' => $createdSubscription['confirmation_url'],
        ];
    }

    private function subscribeViaStripe(Shop $shop, Plan $plan, string $billingInterval): array
    {
        $billingCycleMonths = $billingInterval === 'ANNUAL' ? 12 : 1;
        $startedAt = now();
        $endedAt = $startedAt->copy()->addMonths($billingCycleMonths);
        $trialDays = (int) ($plan->trial_days ?? 0);

        if ($billingCycleMonths == 1) {
            $price = $plan->prices['EVERY_30_DAYS'] ?? 0;
            $priceid = $plan->stripe_price_ids['EVERY_30_DAYS'] ?? '';
            $interval = 'month';
        } else {
            $price = $plan->prices['ANNUAL'] ?? 0;
            $priceid = $plan->stripe_price_ids['ANNUAL'] ?? '';
            $interval = 'year';
        }

        $result = sendPaymentLink($shop, [
            'name' => $plan->name,
            'description' => $plan->description,
            'amount' => $price,
            'currency' => 'usd',
            'mode' => 'subscription',
            'interval' => $interval,
            'price_id' => $priceid,
            'trialdays' => $trialDays,
            'success_url' => route('payment.success', ['shop' => $shop->shop]),
            'cancel_url' => route('payment.cancel', ['shop' => $shop->shop]),
        ], $shop->email, 'Payment Reminder');

        $subscription = ShopSubscription::firstOrCreate(
            ['shop_id' => $shop->id],
            ['plan_id' => $plan->id]
        );

        if ($subscription->status === 'active') {
            $subscription->update([
                'requested_plan_id' => $plan->id,
            ]);
        } else {
            $subscription->update([
                'plan_id' => $plan->id,
                'requested_plan_id' => 0,
                'status' => 'pending',
                'price' => $price,
                'billing_cycle_months' => $billingCycleMonths,
                'started_at' => $startedAt,
                'activated_at' => null,
                'trial_ends_at' => null,
                'trial_days' => 0,
                'is_trial' => 0,
                'trial_used' => 1,
                'current_period_end' => $endedAt,
                'ended_at' => $endedAt,
                'shopify_return_url' => route('payment.cancel'),
                'shopify_confirmation_url' => route('payment.success'),
            ]);
        }

        if (!isset($result['url']) || !$result['url']) {
            return [
                'redirect' => $this->shopAwareUrl('/plans', $shop->shop),
                'error' => 'Failed to generate payment link. Please try again.',
            ];
        }

        return [
            'redirect' => $this->shopAwareUrl('/plans', $shop->shop),
            'message' => "{$plan->name} plan activation initiated for {$shop->shop}",
        ];
    }

    private function cancelViaShopify(Shop $shop): bool
    {
        $subscription = ShopSubscription::where('shop_id', $shop->id)->first();

        if (!$subscription || empty($subscription->shopify_subscription_gid)) {
            Log::warning('No active Shopify subscription found for cancellation.', [
                'shop_id' => $shop->id,
            ]);

            return false;
        }

        $cancelled = $this->shopifyBilling->cancelSubscription(
            $shop,
            $subscription->shopify_subscription_gid
        );

        if (!$cancelled) {
            Log::error('Failed to cancel Shopify subscription.', [
                'shop_id' => $shop->id,
                'subscription_gid' => $subscription->shopify_subscription_gid,
            ]);

            return false;
        }

        $subscription->update([
            'status' => 'pending_cancel',
        ]);

        Log::info('Shopify subscription marked as pending cancel.', [
            'shop_id' => $shop->id,
            'subscription_gid' => $subscription->shopify_subscription_gid,
        ]);

        return true;
    }

    private function cancelViaStripe(Shop $shop): bool
    {
        $subscription = \App\Models\ShopifySubscription::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->latest('id')
            ->first();

        if (!$subscription) {
            Log::warning('No active Stripe subscription found for cancellation.', [
                'shop_id' => $shop->id,
            ]);

            return false;
        }

        $cancelled = $this->stripeService->cancelSubscription(
            $subscription->stripe_subscription_id
        );

        if (!$cancelled) {
            Log::error('Failed to cancel Stripe subscription.', [
                'shop_id' => $shop->id,
                'subscription_id' => $subscription->stripe_subscription_id,
            ]);

            return false;
        }

        Log::info('Stripe subscription cancellation initiated.', [
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->stripe_subscription_id,
        ]);

        return true;
    }

    private function shopAwareUrl(string $path, ?string $shop = null): string
    {
        return $shop ? url($path . '?shop=' . $shop) : url($path);
    }
}