<?php

namespace App\Services;

use App\Models\ShopSubscription;

class SubscriptionService
{
    public function isActive($shopId): bool
    {
        $subscription = ShopSubscription::where('shop_id', $shopId)
            ->latest()
            ->first();

        if (!$subscription) {
            return false;
        }

        //  REAL-TIME + NO EXTRA DB
        return $subscription->status === 'active'
            && $subscription->current_period_end > now();
    }
}
