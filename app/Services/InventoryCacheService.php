<?php

namespace App\Services;

use App\Jobs\SyncAmazonInventoryJob;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InventoryCacheService
{
    private const CACHE_TTL = 25;

    private const INVENTORY_CACHE_PREFIX = 'amazon_inventory';

    private const STATUS_CACHE_PREFIX = 'amazon_inventory_status';

    private const LOCK_CACHE_PREFIX = 'amazon_inventory_lock';

    protected AmazonInventoryReportService $amazonInventoryReportService;

    public function __construct()
    {
        $this->amazonInventoryReportService = app(AmazonInventoryReportService::class);
    }

    /**
     * Get inventory from cache.
     * If cache is expired (>25 mins), trigger background refresh while returning cached data immediately.
     */
    public function getAmazonInventory(
        Shop $shop,
        ?string $marketplaceId = null
    ): array {
        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');

        Log::info('getAmazonInventory START', [
            'shop_id' => $shop->id,
            'marketplace' => $marketplaceId,
        ]);

        $cacheKey = $this->getInventoryCacheKey($shop, $marketplaceId);
        $status = $this->getStatus($shop, $marketplaceId);
        $hasCache = Cache::has($cacheKey);
        $syncCompleted = $status['sync_completed'] ?? false;

        // State 1: No usable cache, never synced, or previous sync invalid
        if (!$hasCache || !$syncCompleted) {
            if (!($status['refreshing'] ?? false)) {
                $this->updateStatus($shop, $marketplaceId, [
                    'refreshing'     => true,
                    'sync_completed' => false,
                ]);

                Cache::put(
                    "amazon_progress_{$shop->shop}",
                    [
                        'percent' => 0,
                        'message' => 'Preparing...',
                    ],
                    now()->addMinutes(5)
                );

                SyncAmazonInventoryJob::dispatch($shop->id);
            }

            return [
                'products' => [],
                'status'   => $this->getStatus($shop, $marketplaceId),
            ];
        }

        // Cache exists and sync was completed successfully (State 2 or State 3)
        $inventory = Cache::get($cacheKey, []);

        Log::info('Inventory cache loaded', [
            'count' => is_array($inventory) ? count($inventory) : 0,
            'status' => $status,
        ]);

        $expired = $this->isExpired($shop, $marketplaceId);

        Log::info('Cache expiry check', [
            'expired' => $expired,
            'status'  => $status,
        ]);

        if ($expired) {
            Log::info('Calling dispatchRefresh');
            $this->dispatchRefresh($shop, $marketplaceId);
        }

        $status = $this->getStatus($shop, $marketplaceId);

        Log::info('Returning response', [
            'products' => is_array($inventory) ? count($inventory) : 0,
            'status'   => $status,
        ]);

        return [
            'products' => $inventory,
            'status'   => $status,
        ];
    }

    /**
     * Refresh inventory from Amazon and update cache.
     */
    public function refreshAmazonInventory(
        Shop $shop,
        ?string $marketplaceId = null

    ): array {
        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');


        $lock = Cache::lock(
            $this->getLockCacheKey($shop, $marketplaceId),
            300
        );

        if (!$lock->get()) {

            Log::info('Amazon inventory refresh already running.', [
                'shop_id' => $shop->id,
            ]);

            return Cache::get(
                $this->getInventoryCacheKey($shop, $marketplaceId),
                []
            );
        }

        try {

            $this->updateStatus($shop, $marketplaceId, [
                'refreshing' => true,
            ]);

            $inventory = $this->amazonInventoryReportService
                ->syncInventory($shop, $marketplaceId);

            $status = $this->getStatus($shop, $marketplaceId);

            $this->updateStatus($shop, $marketplaceId, [
                'refreshing'     => false,
                'sync_completed' => true,
                'last_synced_at' => now()->toDateTimeString(),
                'cache_version'  => ($status['cache_version'] ?? 0) + 1,
            ]);

            return $inventory;
        } catch (\Throwable $exception) {

            Log::error('Amazon inventory refresh failed.', [
                'shop_id' => $shop->id,
                'message' => $exception->getMessage(),
            ]);

            $this->updateStatus($shop, $marketplaceId, [
                'refreshing'     => false,
                'sync_completed' => false,
            ]);

            throw $exception;
        } finally {

            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Dispatch background refresh.
     */
    public function dispatchRefresh(Shop $shop, ?string $marketplaceId = null): void
    {
        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');

        Log::info('dispatchRefresh ENTERED', [
            'shop_id' => $shop->id,
            'marketplace' => $marketplaceId,
        ]);

        $status = $this->getStatus($shop, $marketplaceId);

        Log::info('Current Refresh Status', $status);

        if ($status['refreshing'] ?? false) {
            Log::info('Refresh already in progress. Dispatch skipped.');
            return;
        };

        $this->updateStatus($shop, $marketplaceId, [
            'refreshing' => true,
        ]);

        Cache::put(
            "amazon_progress_{$shop->shop}",
            [
                'percent' => 0,
                'message' => 'Preparing...',
            ],
            now()->addMinutes(5)
        );

        Log::info('Dispatching SyncAmazonInventoryJob', [
            'shop_id' => $shop->id,
        ]);

        SyncAmazonInventoryJob::dispatch($shop->id);

        Log::info('SyncAmazonInventoryJob dispatched successfully');
    }

    /**
     * Check whether cache is expired.
     */
    public function isExpired(Shop $shop, ?string $marketplaceId = null): bool
    {
        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');
        $status = $this->getStatus($shop, $marketplaceId);
        if (empty($status['last_synced_at']) || !($status['sync_completed'] ?? false)) {
            return true;
        }

        $lastSynced = \Carbon\Carbon::parse($status['last_synced_at']);
        $minutes = $lastSynced->diffInMinutes(now());
        return $minutes >= self::CACHE_TTL;
    }

    /**
     * Get cache metadata.
     */
    public function getStatus(Shop $shop, ?string $marketplaceId = null): array
    {

        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');
        return Cache::get(
            $this->getStatusCacheKey($shop, $marketplaceId),
            [
                'refreshing'     => false,
                'sync_completed' => false,
                'cache_version'  => 0,
                'last_synced_at' => null,
            ]
        );
    }

    /**
     * Update cache metadata.
     */
    public function updateStatus(
        Shop $shop,
        string $marketplaceId,
        array $data
    ): void {

        $status = array_merge(
            $this->getStatus($shop, $marketplaceId),
            $data
        );

        Cache::forever(
            $this->getStatusCacheKey($shop, $marketplaceId),
            $status
        );
    }

    /**
     * Inventory cache key.
     */
    protected function getInventoryCacheKey(
        Shop $shop,
        string $marketplaceId
    ): string {
        return self::INVENTORY_CACHE_PREFIX . "_{$shop->id}_{$marketplaceId}";
    }

    protected function getStatusCacheKey(
        Shop $shop,
        string $marketplaceId
    ): string {
        return self::STATUS_CACHE_PREFIX . "_{$shop->id}_{$marketplaceId}";
    }

    protected function getLockCacheKey(
        Shop $shop,
        string $marketplaceId
    ): string {
        return self::LOCK_CACHE_PREFIX . "_{$shop->id}_{$marketplaceId}";
    }
}
