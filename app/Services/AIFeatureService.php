<?php

namespace App\Services;

use App\Models\ShopSubscription;

class AIFeatureService
{
    public function canUseAutoFill(int $shopId): bool
    {
        $subscription = app(SubscriptionService::class)->getActiveSubscription($shopId);

        return (bool) optional($subscription?->plan)->ai_autofill;
    }

    public function canUseSingleField(int $shopId): bool
    {
        $subscription = app(SubscriptionService::class)->getActiveSubscription($shopId);

        return (bool) optional($subscription?->plan)->ai_single_field;
    }
}