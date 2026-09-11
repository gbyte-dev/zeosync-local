<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\ShopifyWebhookService;
use Illuminate\Console\Command;
use Throwable;

class SyncShopifyWebhooksCommand extends Command
{
    protected $signature = 'shopify:webhooks:sync {--shop= : Specific shop domain to register webhooks for}';

    protected $description = 'Ensure required Shopify webhooks (ORDERS_CREATE, ORDERS_UPDATED, APP_UNINSTALLED) are registered for active shops';

    public function handle(ShopifyWebhookService $webhookService): int
    {
        $shopFilter = $this->option('shop');

        $query = Shop::query()
            ->whereNotNull('access_token')
            ->where('is_active', 1);

        if ($shopFilter) {
            $query->where('shop', $shopFilter);
        }

        $shops = $query->get();

        if ($shops->isEmpty()) {
            $this->info('No matching active shops found.');
            return self::SUCCESS;
        }

        $this->info("Starting Shopify webhook synchronization for {$shops->count()} shop(s)...");

        $successCount = 0;
        $failureCount = 0;

        foreach ($shops as $shop) {
            $this->line("Syncing webhooks for shop: {$shop->shop}");

            try {
                $webhookService->ensureOrdersCreateWebhook($shop);
                $webhookService->ensureOrdersUpdateWebhook($shop);
                $webhookService->ensureAppUninstalledWebhook($shop);

                $this->info("Successfully ensured webhooks for {$shop->shop}");
                $successCount++;
            } catch (Throwable $e) {
                $this->error("Failed to sync webhooks for {$shop->shop}: {$e->getMessage()}");
                $failureCount++;
            }
        }

        $this->info("Webhook sync completed. Success: {$successCount}, Failed: {$failureCount}");

        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
