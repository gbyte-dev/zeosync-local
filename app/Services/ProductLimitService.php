<?php

namespace App\Services;

use App\Models\AllProduct;
use App\Models\ShopSubscription;
use Illuminate\Support\Facades\Log;

class ProductLimitService
{
    public function canCreateProduct(int $shopId): array
    {
        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return [
                'allowed' => false,
                'message' => 'No active subscription found.',
                'used' => 0,
                'limit' => 0,
                'remaining' => 0,
            ];
        }

        if (!$subscription->plan) {
            return [
                'allowed' => false,
                'message' => 'No plan found for the active subscription.',
                'used' => 0,
                'limit' => 0,
                'remaining' => 0,
            ];
        }

        $limit = (int) ($subscription->plan->product_limit ?? 0);

        $used = AllProduct::where('user_id', $shopId)
            ->whereBetween('created_at', [
                $subscription->activated_at,
                $subscription->current_period_end,
            ])
            ->count();

        Log::info('PRODUCT LIMIT CHECK', [
            'shop_id'            => $shopId,
            'plan_id'            => $subscription->plan_id,
            'product_limit'      => $limit,
            'product_used'       => $used,
            'remaining'          => max(0, $limit - $used),
            'activated_at'       => $subscription->activated_at,
            'current_period_end' => $subscription->current_period_end,
        ]);

        return [
            'allowed'   => $used < $limit,
            'used'      => $used,
            'limit'     => $limit,
            'remaining' => max(0, $limit - $used),
            'message'   => $used >= $limit
                ? "Product limit reached. You have already used {$used} of {$limit} products for the current billing cycle."
                : 'OK',
        ];
    }
}
