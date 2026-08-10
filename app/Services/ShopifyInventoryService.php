<?php

namespace App\Services;

use App\Services\ShopifyService;
use App\Models\Shop;
use App\Models\ProductMarketplaceMapping;
use Illuminate\Support\Facades\Cache;

class ShopifyInventoryService
{
    private const CACHE_TTL = 25;

    public function getInventory(Shop $shop): array
    {
        $cacheKey = "shopify_inventory_{$shop->shop}";

        if (!Cache::has($cacheKey)) {
            // First time sync for shop: refresh directly to populate initial cache
            return $this->refreshShopifyInventory($shop);
        }

        $inventory = Cache::get($cacheKey, []);

        if ($this->isExpired($shop)) {
            $this->dispatchRefresh($shop);
        }

        return $inventory;
    }

    public function refreshShopifyInventory(Shop $shop): array
    {
        $lock = Cache::lock("shopify_inventory_lock_{$shop->id}", 300);

        if (!$lock->get()) {
            return Cache::get("shopify_inventory_{$shop->shop}", []);
        }

        try {
            $this->updateStatus($shop, ['refreshing' => true]);

            $shopify = new ShopifyService(
                $shop->shop,
                $shop->access_token
            );

            // GraphQL Structure
            $structure = [
                'id',
                'title',
                'featuredImage' => [
                    'url'
                ],
                'variants(first: 50)' => [
                    'nodes' => [
                        'id',
                        'title',
                        'sku',
                        'image' => ['url'],
                        'inventoryQuantity',
                        'inventoryItem' => [
                            'id',
                            'inventoryLevels(first: 1)' => [
                                'nodes' => [
                                    'quantities(names: ["available", "committed", "incoming", "on_hand"])' => [
                                        'name',
                                        'quantity'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // Fetch Shopify Products
            $allProducts = [];
            $cursor = null;

            do {
                $response = $shopify->paginate(
                    $structure,
                    50,
                    $cursor
                );

                $allProducts = array_merge(
                    $allProducts,
                    $response['data'] ?? []
                );

                $cursor = $response['next_cursor'] ?? null;
            } while (!empty($response['has_next']));

            // Convert Product → Variant Inventory
            $result = $this->flattenVariants(
                $allProducts,
                $shop
            );

            Cache::forever("shopify_inventory_{$shop->shop}", $result);

            $this->updateStatus($shop, [
                'refreshing' => false,
                'last_synced_at' => now()->toDateTimeString(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->updateStatus($shop, ['refreshing' => false]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function dispatchRefresh(Shop $shop): void
    {
        $status = $this->getStatus($shop);
        if ($status['refreshing'] ?? false) {
            return;
        }

        $this->updateStatus($shop, ['refreshing' => true]);
        \App\Jobs\SyncShopifyInventoryJob::dispatch($shop->id);
    }

    public function isExpired(Shop $shop): bool
    {
        $status = $this->getStatus($shop);
        if (empty($status['last_synced_at'])) {
            return true;
        }

        $lastSynced = \Carbon\Carbon::parse($status['last_synced_at']);
        return $lastSynced->diffInMinutes(now()) >= self::CACHE_TTL;
    }

    public function getStatus(Shop $shop): array
    {
        return Cache::get("shopify_inventory_status_{$shop->shop}", [
            'refreshing' => false,
            'last_synced_at' => null,
        ]);
    }

    public function updateStatus(Shop $shop, array $data): void
    {
        $status = array_merge($this->getStatus($shop), $data);
        Cache::forever("shopify_inventory_status_{$shop->shop}", $status);
    }

    private function flattenVariants(
        array $allProducts,
        Shop $shop
    ): array {

        $result = [];

        $mappings = ProductMarketplaceMapping::where(
            'shop_id',
            $shop->id
        )
            ->get()
            ->keyBy('shopify_variant_id');

        foreach ($allProducts as $product) {

            foreach ($product['variants']['nodes'] ?? [] as $variant) {

                $qty = $variant['inventoryQuantity'] ?? 0;

                $available = 0;
                $committed = 0;
                $onHand = 0;
                $unavailable = 0;

                $levels = $variant['inventoryItem']['inventoryLevels']['nodes'] ?? [];

                if (!empty($levels)) {

                    foreach ($levels[0]['quantities'] ?? [] as $q) {

                        if ($q['name'] === 'available') {
                            $available = $q['quantity'];
                        }

                        if ($q['name'] === 'committed') {
                            $committed = $q['quantity'];
                        }

                        if ($q['name'] === 'on_hand') {
                            $onHand = $q['quantity'];
                        }
                    }

                    $unavailable = $onHand - $available;
                }

                $productId = str_replace(
                    'gid://shopify/Product/',
                    '',
                    $product['id']
                );

                $variantId = str_replace(
                    'gid://shopify/ProductVariant/',
                    '',
                    $variant['id']
                );

                $mapping = $mappings[$variantId] ?? null;

                $isMapped = $mapping
                    && !empty($mapping->shopify_variant_id)
                    && !empty($mapping->amazon_sku);

                $inventoryItemId = isset($variant['inventoryItem']['id'])
                    ? str_replace(
                        'gid://shopify/InventoryItem/',
                        '',
                        $variant['inventoryItem']['id']
                    )
                    : null;

                $result[] = [
                    'pid' => $productId,
                    'vid' => $variantId,
                    'inventory_item_id' => $inventoryItemId,
                    'product' => $product['title'] ?? '',
                    'variant' => $variant['title'] ?? '',
                    'sku' => $variant['sku'] ?? 'No SKU',
                    'available' => $available,
                    'committed' => $committed,
                    'on_hand' => $onHand,
                    'unavailable' => max(0, $unavailable),
                    'qty' => $qty,
                    'status' => $available > 0 ? 'synced' : 'pending',
                    'image' => $variant['image']['url']
                        ?? $product['featuredImage']['url']
                        ?? null,

                    'is_mapped' => $isMapped,
                    'mapped_sku' => $isMapped
                        ? $mapping->amazon_sku
                        : null,
                    'mapping_id' => $isMapped
                        ? $mapping->id
                        : null,
                ];
            }
        }

        return $result;
    }
}
