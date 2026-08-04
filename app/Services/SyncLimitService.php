<?php

namespace App\Services;

use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;

class SyncLimitService
{
    public function canMap(Shop $shop): array
    {
        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shop->id)
            ->whereIn('status', [
                'active',
                'trialing',
            ])
            ->latest('started_at')
            ->first();

        if (!$subscription) {
            return [
                'allowed' => false,
                'message' => 'No active subscription found.',
            ];
        }

        // Subscription expired
        if (
            $subscription->current_period_end &&
            now()->gt($subscription->current_period_end)
        ) {
            return [
                'allowed' => false,
                'message' => 'Your subscription has expired. Please renew your plan.',
            ];
        }

        if (!$subscription->plan) {
            return [
                'allowed' => false,
                'message' => 'Subscribed plan not found.',
            ];
        }

        $limit = (int) $subscription->plan->sync_limit;

        // 0 = Unlimited
        if ($limit === 0) {
            return [
                'allowed' => true,
                'used' => 0,
                'remaining' => 0,
                'limit' => 0,
                'message' => null,
            ];
        }

        $used = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->whereNotNull('shopify_variant_id')
            ->whereNotNull('amazon_sku')
            ->whereBetween('created_at', [
                $subscription->started_at,
                $subscription->current_period_end,
            ])
            ->count();

        return [
            'allowed'   => $used < $limit,
            'used'      => $used,
            'remaining' => max(0, $limit - $used),
            'limit'     => $limit,
            'plan_name' => $subscription->plan->name,
            'message'   => $used >= $limit
                ? "You have used {$used} of {$limit} product mappings for your current billing cycle. Please upgrade your plan to map more products."
                : null,
        ];
    }
}
