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
            'seller_id' => $shop->amazon_seller_id,
        ]);

        if (empty($shop->amazon_seller_id)) {
            Log::warning('Amazon inventory cache skipped: seller ID missing', [
                'shop_id' => $shop->id,
                'marketplace' => $marketplaceId,
            ]);

            return [
                'products' => [],
                'status' => [
                    'refreshing' => false,
                    'sync_completed' => false,
                    'error' => 'seller_id_missing',
                ],
            ];
        }

        $cacheKey = $this->getInventoryCacheKey($shop, $marketplaceId);
        $status = $this->getStatus($shop, $marketplaceId);
        $hasCache = Cache::has($cacheKey);
        $syncCompleted = $status['sync_completed'] ?? false;

        Log::info('[AMAZON_DEBUG] Initial state calculated', [
            'shop_id' => $shop->id,
            'shop_domain' => $shop->shop,
            'seller_id' => $shop->amazon_seller_id,
            'marketplace_id' => $marketplaceId,
            'cache_key' => $cacheKey,
            'has_cache' => $hasCache,
            'sync_completed' => $syncCompleted,
            'status' => $status,
            'cache_count' => is_array(Cache::get($cacheKey, [])) ? count(Cache::get($cacheKey, [])) : 0,
            'request_url' => request()->fullUrl(),
            'request_query' => request()->all(),
        ]);

        // State 1: No usable cache exists at all
        if (!$hasCache) {
            if (!($status['refreshing'] ?? false)) {
                $this->updateStatus($shop, $marketplaceId, [
                    'refreshing' => true,
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

                SyncAmazonInventoryJob::dispatch($shop->id)->afterResponse();
            }

            $currentStatus = $this->getStatus($shop, $marketplaceId);

            return [
                'products' => [],
                'status' => $currentStatus,
            ];
        }

        // State 2 & 3: Cache exists — return cached products immediately
        $inventory = Cache::get($cacheKey, []);

        Log::info('Inventory cache loaded', [
            'count' => is_array($inventory) ? count($inventory) : 0,
            'status' => $status,
            'cache_key' => $cacheKey,
        ]);

        $expired = $this->isExpired($shop, $marketplaceId);

        Log::info('Cache expiry check', [
            'expired' => $expired,
            'status' => $status,
        ]);

        if ($expired && !($status['refreshing'] ?? false)) {
            Log::info('Triggering background Amazon refresh after response');

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

            SyncAmazonInventoryJob::dispatch($shop->id)->afterResponse();
        }

        $currentStatus = $this->getStatus($shop, $marketplaceId);

        Log::info('[AMAZON_DEBUG] Returning cache-hit response', [
            'products_count' => is_array($inventory) ? count($inventory) : 0,
            'status' => $currentStatus,
            'cache_key' => $cacheKey,
            'has_cache' => $hasCache,
            'sync_completed' => $syncCompleted,
        ]);

        return [
            'products' => is_array($inventory) ? $inventory : [],
            'status' => $currentStatus,
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

        if (empty($shop->amazon_seller_id)) {
            Log::warning('Amazon inventory refresh skipped: seller ID missing', [
                'shop_id' => $shop->id,
                'marketplace' => $marketplaceId,
            ]);

            return [];
        }

        $lock = Cache::lock(
            $this->getLockCacheKey($shop, $marketplaceId),
            300
        );

        if (!$lock->get()) {
            Log::info('Amazon inventory refresh already running.', [
                'shop_id' => $shop->id,
                'seller_id' => $shop->amazon_seller_id,
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

            $inventory = $this
                ->amazonInventoryReportService
                ->syncInventory($shop, $marketplaceId);

            $status = $this->getStatus($shop, $marketplaceId);

            $this->updateStatus($shop, $marketplaceId, [
                'refreshing' => false,
                'sync_completed' => true,
                'last_synced_at' => now()->toDateTimeString(),
                'cache_version' => ($status['cache_version'] ?? 0) + 1,
            ]);

            return $inventory;
        } catch (\Throwable $exception) {
            Log::error('Amazon inventory refresh failed.', [
                'shop_id' => $shop->id,
                'seller_id' => $shop->amazon_seller_id,
                'message' => $exception->getMessage(),
            ]);

            $hasCache = Cache::has($this->getInventoryCacheKey($shop, $marketplaceId));

            $this->updateStatus($shop, $marketplaceId, [
                'refreshing' => false,
                'sync_completed' => $hasCache ? true : false,
                'last_error' => $exception->getMessage(),
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

        if (empty($shop->amazon_seller_id)) {
            Log::warning('Amazon inventory dispatchRefresh skipped: seller ID missing', [
                'shop_id' => $shop->id,
                'marketplace' => $marketplaceId,
            ]);

            return;
        }

        Log::info('dispatchRefresh ENTERED', [
            'shop_id' => $shop->id,
            'seller_id' => $shop->amazon_seller_id,
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
            'seller_id' => $shop->amazon_seller_id,
        ]);

        SyncAmazonInventoryJob::dispatch($shop->id);

        Log::info('SyncAmazonInventoryJob dispatched successfully');
    }

    /**
     * Check whether cache is expired.
     */
    public function isExpired(Shop $shop, ?string $marketplaceId = null): bool
    {
        if (empty($shop->amazon_seller_id)) {
            return true;
        }

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
        if (empty($shop->amazon_seller_id)) {
            return [
                'refreshing' => false,
                'sync_completed' => false,
                'cache_version' => 0,
                'last_synced_at' => null,
            ];
        }

        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');
        return Cache::get(
            $this->getStatusCacheKey($shop, $marketplaceId),
            [
                'refreshing' => false,
                'sync_completed' => false,
                'cache_version' => 0,
                'last_synced_at' => null,
            ]
        );
    }

    /**
     * Update cache metadata.
     */
    public function updateStatus(
        Shop $shop,
        ?string $marketplaceId,
        array $data
    ): void {
        if (empty($shop->amazon_seller_id)) {
            Log::warning('Amazon inventory updateStatus skipped: seller ID missing', [
                'shop_id' => $shop->id,
            ]);

            return;
        }

        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');

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
        ?string $marketplaceId = null
    ): string {
        return self::INVENTORY_CACHE_PREFIX . "_{$shop->id}_{$shop->amazon_seller_id}";
    }

    protected function getStatusCacheKey(
        Shop $shop,
        ?string $marketplaceId = null
    ): string {
        return self::STATUS_CACHE_PREFIX . "_{$shop->id}_{$shop->amazon_seller_id}";
    }

    protected function getLockCacheKey(
        Shop $shop,
        ?string $marketplaceId = null
    ): string {
        return self::LOCK_CACHE_PREFIX . "_{$shop->id}_{$shop->amazon_seller_id}";
    }
}
