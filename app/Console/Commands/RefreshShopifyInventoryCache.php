<?php

namespace App\Console\Commands;

use App\Jobs\SyncShopifyInventoryJob;
use App\Models\Shop;
use App\Services\ShopifyInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshShopifyInventoryCache extends Command
{
    protected $signature = 'shopify:refresh-inventory-cache';
    protected $description = 'Refresh expired Shopify inventory cache';
    public function handle(): int
    {
        $shopifyInventoryService = app(ShopifyInventoryService::class);
        $shops = Shop::query()
            ->where('is_active', 1)
            ->where('store_status', 'active')
            ->whereNotNull('access_token')
            ->get();
        foreach ($shops as $shop) {
            if (!$shopifyInventoryService->isExpired($shop)) {
                continue;
            }
            Log::info('Scheduling background Shopify inventory refresh from cron', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
            ]);
            SyncShopifyInventoryJob::dispatch($shop->id);
        }
        return self::SUCCESS;
    }
}
