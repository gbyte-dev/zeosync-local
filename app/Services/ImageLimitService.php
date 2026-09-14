<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Shop;
use App\Models\ShopSubscription;

class ImageLimitService
{
    /**
     * Retrieve image limit and usage calculation for a given shop.
     *
     * @param Shop|int $shop
     * @return array{limit: int, used: int, remaining: int|null, unlimited: bool, has_active_plan: bool, plan_name: string|null}
     */
    public function getImageLimitInfo(Shop|int $shop): array
    {
        $shopId = $shop instanceof Shop ? $shop->id : (int) $shop;

        $used = Image::where('shop_id', $shopId)->count();

        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopId)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('started_at')
            ->first();

        // If no active subscription or no plan attached
        if (!$subscription || !$subscription->plan) {
            return [
                'limit'           => 0,
                'used'            => $used,
                'remaining'       => 0,
                'unlimited'       => false,
                'has_active_plan' => false,
                'plan_name'       => null,
            ];
        }

        // Check if subscription has expired
        if (
            $subscription->current_period_end &&
            now()->gt($subscription->current_period_end)
        ) {
            return [
                'limit'           => 0,
                'used'            => $used,
                'remaining'       => 0,
                'unlimited'       => false,
                'has_active_plan' => false,
                'plan_name'       => $subscription->plan->name ?? null,
            ];
        }

        $limit = (int) ($subscription->plan->image_limit ?? 0);

        // 0 represents Unlimited
        if ($limit === 0) {
            return [
                'limit'           => 0,
                'used'            => $used,
                'remaining'       => null,
                'unlimited'       => true,
                'has_active_plan' => true,
                'plan_name'       => $subscription->plan->name ?? null,
            ];
        }

        $remaining = max(0, $limit - $used);

        return [
            'limit'           => $limit,
            'used'            => $used,
            'remaining'       => $remaining,
            'unlimited'       => false,
            'has_active_plan' => true,
            'plan_name'       => $subscription->plan->name ?? null,
        ];
    }
}
