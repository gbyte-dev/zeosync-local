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

Schedule::command('shops:refresh-access-token')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('amazon:refresh-inventory-cache')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('shopify:refresh-inventory-cache')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('compliance:purge-expired-results')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();
// Schedule::command('operations:recover')->everyMinute()->withoutOverlapping()->onFailure(fn()=>\Illuminate\Support\Facades\Log::error('Durable operations require review; run operations:recover and inspect the inbox.'));
