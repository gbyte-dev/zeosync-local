<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\StoreStatusService;
use Illuminate\Console\Command;

class RefreshShopAccessTokenCommand extends Command
{
    protected $signature = 'shops:refresh-access-token';

    protected $description = 'Refresh Shopify access tokens for active stores nearing expiration';

    public function handle(): int
    {
        $processed = 0;
        $refreshed = 0;
        $failed = 0;

        Shop::query()
            ->whereNotNull('refresh_token')
            ->where(function ($query) {
                $query->whereNull('access_token_expires_at')
                    ->orWhere('access_token_expires_at', '<=', now()->addHour());
            })
            ->chunkById(100, function ($shops) use (&$processed, &$refreshed, &$failed) {
                foreach ($shops as $shop) {
                    $processed++;

                    $result = app(StoreStatusService::class)->ensureFreshAccessToken($shop);

                    if ($result['success'] ?? false) {
                        $refreshed++;
                        $this->line("Refreshed token for {$shop->shop}");
                        continue;
                    }

                    $failed++;
                    $this->warn("Token refresh skipped/failed for {$shop->shop}: {$result['message'] ?? 'Unknown error'}");
                }
            });

        $this->info("Processed {$processed} shops. Refreshed {$refreshed}. Failed/blocked {$failed}.");

        return self::SUCCESS;
    }
}
