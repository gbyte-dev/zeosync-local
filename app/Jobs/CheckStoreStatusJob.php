<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\StoreStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckStoreStatusJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected Shop $shop;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    public function handle(): void
    {
        Log::info('CHECK STORE STATUS JOB STARTED', [
            'shop_id' => $this->shop->id,
            'shop' => $this->shop->shop,
        ]);

        app(StoreStatusService::class)->check($this->shop);

        Log::info('CHECK STORE STATUS JOB COMPLETED', [
            'shop_id' => $this->shop->id,
            'shop' => $this->shop->shop,
        ]);
    }
}