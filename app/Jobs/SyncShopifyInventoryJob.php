<?php

namespace App\Jobs;

use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\ShopifyInventoryService;
use Illuminate\Support\Facades\Log;

class SyncShopifyInventoryJob implements ShouldQueue
{
    use Queueable;
    public function __construct(
        private readonly int $shopId
    ) {}
    public function handle(
        ShopifyInventoryService $shopifyInventoryService
    ): void {
        $shop = Shop::find($this->shopId);
        if (!$shop) {
            Log::warning('Shopify sync job: Shop not found.', [
                'shop_id' => $this->shopId,
            ]);
            return;
        }
        Log::info('Shopify inventory sync started.', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
        ]);
        try {
            $shopifyInventoryService->refreshShopifyInventory($shop);
            Log::info('Shopify inventory sync completed.', [
                'shop_id' => $shop->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopify inventory sync failed.', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
