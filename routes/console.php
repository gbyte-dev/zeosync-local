<?php

use App\Jobs\SyncAmazonInventoryJob;
use App\Models\Shop;
use App\Services\InventoryCacheService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stores:check-status')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule::call(function () {

//     $inventoryCacheService = app(InventoryCacheService::class);

//     $shops = Shop::query()
//         ->where('is_active', 1)
//         ->where('store_status', 'active')
//         ->whereNotNull('amazon_refresh_token')
//         ->whereNotNull('amazon_marketplace_id')
//         ->get();

//     foreach ($shops as $shop) {

//         if (! $inventoryCacheService->isExpired(
//             $shop,
//             $shop->amazon_marketplace_id
//         )) {
//             continue;
//         }

//         SyncAmazonInventoryJob::dispatch($shop->id);
//     }

// })
// ->everyFiveMinutes()
// ->runInBackground();