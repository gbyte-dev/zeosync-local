<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Support\Facades\Log;

class ShopifyPlanSyncService
{
    public function __construct(
        private ShopifyBillingService $shopifyBilling
    ) {
    }

    public function sync(Shop $shop): ?ShopSubscription
    {
        try {
            $subscription = ShopSubscription::with('plan')
                ->where('shop_id', $shop->id)
                ->first();

            return $this->shopifyBilling->syncSubscription(
                $shop,
                $subscription
            );
        } catch (\Throwable $e) {
            Log::error('SHOPIFY PLAN SYNC FAILED', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}