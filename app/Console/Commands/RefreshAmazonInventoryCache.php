<?php

namespace App\Console\Commands;

use App\Jobs\SyncAmazonInventoryJob;
use App\Models\Shop;
use App\Services\InventoryCacheService;
use Illuminate\Console\Command;

class RefreshAmazonInventoryCache extends Command
{
    protected $signature = 'amazon:refresh-inventory-cache';

    protected $description = 'Refresh expired Amazon inventory cache';

    public function handle(): int
    {
        $inventoryCacheService = app(InventoryCacheService::class);

        $shops = Shop::query()
            ->where('is_active', 1)
            ->where('store_status', 'active')
            ->whereNotNull('amazon_refresh_token')
            ->whereNotNull('amazon_marketplace_id')
            ->get();

        foreach ($shops as $shop) {

            if (! $inventoryCacheService->isExpired(
                $shop,
                $shop->amazon_marketplace_id
            )) {
                continue;
            }

            SyncAmazonInventoryJob::dispatch($shop->id);
        }

        return self::SUCCESS;
    }
}