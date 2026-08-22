<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Support\Facades\Log;
use App\Services\Billing\BillingManager;
use App\Services\Billing\BillingProvider;

class SubscriptionCancellationService
{
    protected BillingManager $billingManager;

    public function __construct()
    {
        $this->billingManager = app(BillingManager::class);
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

        Log::info('CANCEL AT PERIOD END VIA BILLING MANAGER', [
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->id,
            'billing_provider' => app(BillingProvider::class)->provider(),
        ]);

        // Delegate to the active billing provider via BillingManager
        $cancelled = $this->billingManager->cancel($shop);

        if (!$cancelled) {
            Log::error('FAILED TO CANCEL SUBSCRIPTION VIA BILLING PROVIDER', [
                'shop_id' => $shop->id,
                'subscription_id' => $subscription->id,
                'billing_provider' => app(BillingProvider::class)->provider(),
            ]);

            return false;
        }

        $subscription->update([
            'status' => 'pending_cancel',
        ]);

        Log::info('SUBSCRIPTION MARKED AS PENDING CANCEL', [
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->id,
        ]);

        return true;
    }
}