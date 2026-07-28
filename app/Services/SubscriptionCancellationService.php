<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Support\Facades\Log;
use App\Models\ShopifySubscription;

class SubscriptionCancellationService
{
    protected StripeService $stripeService;

    public function __construct()
    {
        $this->stripeService = app(StripeService::class);
    }

    public function cancelAtPeriodEnd(Shop $shop): bool
    {
        $subscription = ShopSubscription::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['active', 'pending_cancel'])
            ->latest('id')
            ->first();

        if (!$subscription) {

            Log::info('NO ACTIVE SUBSCRIPTION FOUND', [
                'shop_id' => $shop->id,
            ]);

            return false;
        }

        // Already cancellation requested
        if ($subscription->status === 'pending_cancel') {

            Log::info('SUBSCRIPTION ALREADY PENDING CANCEL', [
                'shop_id' => $shop->id,
                'subscription_id' => $subscription->id,
            ]);

            return true;
        }

        $shopifySubscription = ShopifySubscription::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$shopifySubscription) {

            Log::warning('ACTIVE STRIPE SUBSCRIPTION NOT FOUND', [
                'shop_id' => $shop->id,
            ]);

            return false;
        }

        if (empty($shopifySubscription->stripe_subscription_id)) {

            Log::warning('STRIPE SUBSCRIPTION ID MISSING', [
                'shop_id' => $shop->id,
                'shopify_subscription_id' => $shopifySubscription->id,
            ]);

            return false;
        }

        $cancelled = $this->stripeService->cancelSubscriptionAtPeriodEnd(
            $shopifySubscription->stripe_subscription_id
        );

        if (!$cancelled) {

            Log::error('FAILED TO MARK STRIPE SUBSCRIPTION FOR CANCELLATION', [
                'shop_id' => $shop->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $shopifySubscription->stripe_subscription_id,
            ]);

            return false;
        }

        $subscription->update([
            'status' => 'pending_cancel',
        ]);

        Log::info('SUBSCRIPTION MARKED AS PENDING CANCEL', [
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $shopifySubscription->stripe_subscription_id,
        ]);

        return true;
    }
}
