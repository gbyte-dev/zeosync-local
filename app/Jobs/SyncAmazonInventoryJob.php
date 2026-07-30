<?php

namespace App\Jobs;

use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\InventoryCacheService;
use Illuminate\Support\Facades\Log;

class SyncAmazonInventoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $shopId
    ) {}

    public function handle(
        InventoryCacheService $inventoryCacheService
    ): void {

        $shop = Shop::find($this->shopId);

        if (! $shop) {

            Log::warning('Shop not found.', [
                'shop_id' => $this->shopId,
            ]);

            return;
        }

        Log::info('Amazon inventory sync started.', [
            'shop_id' => $shop->id,
        ]);
        $inventoryCacheService->refreshAmazonInventory(
            $shop,
            $shop->amazon_marketplace_id
        );

        Log::info('Amazon inventory sync completed.', [
            'shop_id' => $shop->id,
        ]);
    }
}
