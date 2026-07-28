<?php

namespace App\Services;

use App\Models\ShopSubscription;

class AIFeatureService
{
    public function canUseAutoFill(int $shopId): bool
    {
        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->latest()
            ->first();

        return (bool) optional($subscription?->plan)->ai_autofill;
    }

    public function canUseSingleField(int $shopId): bool
    {
        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->latest()
            ->first();

        return (bool) optional($subscription?->plan)->ai_single_field;
    }
}