<?php

namespace App\Services;

use App\Services\ShopifyService;
use App\Models\Shop;
use App\Models\ProductMarketplaceMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopifyInventoryService
{
    public int $maxPages = 250;

    public function getInventory(Shop $shop): array
    {
        $locations = $shop->shopify_locations ?? [];
        $effectiveIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;

        $cacheKey = "shopify_inventory_{$shop->shop}_location_{$effectiveIndex}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($shop) {

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
                                'inventoryLevels(first: 50)' => [
                                    'nodes' => [
                                        'location' => [
                                            'id'
                                        ],
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
                $visitedCursors = [];
                $pageCount = 0;
                $maxPages = $this->maxPages ?? 250;

                do {
                    $pageCount++;

                    $response = $shopify->paginate(
                        $structure,
                        20,
                        $cursor
                    );

                    if (!is_array($response)) {
                        Log::warning('Shopify pagination stopped: invalid page data.', [
                            'shop'       => $shop->shop,
                            'page_count' => $pageCount,
                            'cursor'     => $cursor,
                            'item_count' => count($allProducts),
                        ]);
                        break;
                    }

                    $pageData = is_array($response['data'] ?? null) ? $response['data'] : [];
                    if (!empty($pageData)) {
                        $allProducts = array_merge(
                            $allProducts,
                            $pageData
                        );
                    }

                    $hasNext = (bool) ($response['has_next'] ?? false);
                    $nextCursor = $response['next_cursor'] ?? null;

                    // 1. Normal completion
                    if (!$hasNext) {
                        break;
                    }

                    // 2. Empty page protection
                    if (empty($pageData)) {
                        Log::warning('Shopify pagination stopped: empty page returned while has_next is true.', [
                            'shop'        => $shop->shop,
                            'page_count'  => $pageCount,
                            'cursor'      => $cursor,
                            'next_cursor' => $nextCursor,
                            'item_count'  => count($allProducts),
                        ]);
                        break;
                    }

                    // 3. Missing cursor protection
                    if (empty($nextCursor)) {
                        Log::warning('Shopify pagination stopped: next cursor is missing while has_next is true.', [
                            'shop'       => $shop->shop,
                            'page_count' => $pageCount,
                            'cursor'     => $cursor,
                            'item_count' => count($allProducts),
                        ]);
                        break;
                    }

                    // 4. Repeated cursor protection
                    if ($nextCursor === $cursor || isset($visitedCursors[$nextCursor])) {
                        Log::warning('Shopify pagination stopped: repeated cursor detected.', [
                            'shop'        => $shop->shop,
                            'page_count'  => $pageCount,
                            'cursor'      => $cursor,
                            'next_cursor' => $nextCursor,
                            'item_count'  => count($allProducts),
                        ]);
                        break;
                    }

                    // 5. Maximum page limit reached
                    if ($pageCount >= $maxPages) {
                        Log::warning('Shopify pagination stopped: maximum page limit reached.', [
                            'shop'       => $shop->shop,
                            'page_count' => $pageCount,
                            'max_pages'  => $maxPages,
                            'item_count' => count($allProducts),
                        ]);
                        break;
                    }

                    $visitedCursors[$nextCursor] = true;
                    $cursor = $nextCursor;

                } while (true);

                // Convert Product → Variant Inventory
                return $this->flattenVariants(
                    $allProducts,
                    $shop
                );
            }
        );
    }

    private function flattenVariants(
        array $allProducts,
        Shop $shop
    ): array {

        $result = [];

        $selectedLocationId = null;

        $locations = $shop->shopify_locations ?? [];
        $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;
        $selectedLocation = $locations[$selectedIndex] ?? null;

        if ($selectedLocation && !empty($selectedLocation['id'])) {
            $selectedLocationId = (string) $selectedLocation['id'];
        }

        Log::info('SHOPIFY SELECTED LOCATION RESOLVED', [
            'shop_id' => $shop->id,
            'selected_location_index' => $shop->selected_location_index,
            'effective_index' => $selectedIndex,
            'selected_location_id' => $selectedLocationId,
            'selected_location' => $selectedLocation,
        ]);

        $mappings = ProductMarketplaceMapping::where(
            'shop_id',
            $shop->id
        )
            ->get()
            ->keyBy('shopify_variant_id');

        foreach ($allProducts as $product) {

            foreach ($product['variants']['nodes'] ?? [] as $variant) {

                $qty = isset($variant['inventoryQuantity']) && $variant['inventoryQuantity'] !== null
                    ? (int) $variant['inventoryQuantity']
                    : null;

                $available = null;
                $committed = null;
                $onHand = null;
                $unavailable = null;

                $levels = $variant['inventoryItem']['inventoryLevels']['nodes'] ?? [];

                $selectedLevel = null;

                foreach ($levels as $level) {

                    $levelLocationId = str_replace(
                        'gid://shopify/Location/',
                        '',
                        (string) ($level['location']['id'] ?? '')
                    );

                    \Log::info('SHOPIFY LOCATION LEVEL CHECK', [
                        'shop_id' => $shop->id,
                        'inventory_item_id' => $variant['inventoryItem']['id'] ?? null,
                        'selected_location_id' => $selectedLocationId,
                        'level_location_id' => $levelLocationId,
                        'matched' => (
                            $selectedLocationId !== null &&
                            $levelLocationId === $selectedLocationId
                        ),
                    ]);

                    if (
                        $selectedLocationId !== null &&
                        $levelLocationId === $selectedLocationId
                    ) {
                        $selectedLevel = $level;
                        break;
                    }
                }

                if ($selectedLevel) {

                    foreach ($selectedLevel['quantities'] ?? [] as $q) {

                        if ($q['name'] === 'available' && isset($q['quantity']) && $q['quantity'] !== null) {
                            $available = (int) $q['quantity'];
                        }

                        if ($q['name'] === 'committed' && isset($q['quantity']) && $q['quantity'] !== null) {
                            $committed = (int) $q['quantity'];
                        }

                        if ($q['name'] === 'on_hand' && isset($q['quantity']) && $q['quantity'] !== null) {
                            $onHand = (int) $q['quantity'];
                        }
                    }

                    if ($onHand !== null && $available !== null) {
                        $unavailable = max(0, $onHand - $available);
                    }
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

                if ($available === null) {
                    $status = 'unknown';
                } elseif ($available < 0) {
                    $status = 'oversold';
                } elseif ($available === 0) {
                    $status = 'out_of_stock';
                } else {
                    $status = 'synced';
                }

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
                    'unavailable' => $unavailable,
                    'qty' => $qty,
                    'status' => $status,
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
    public function isExpired(Shop $shop): bool
    {
        $locations = $shop->shopify_locations ?? [];
        $effectiveIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;

        $cacheKey = "shopify_inventory_{$shop->shop}_location_{$effectiveIndex}";

        return !Cache::has($cacheKey);
    }

    public function refreshShopifyInventory(Shop $shop): array
    {
        $locations = $shop->shopify_locations ?? [];
        $effectiveIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;

        $cacheKey = "shopify_inventory_{$shop->shop}_location_{$effectiveIndex}";

        Cache::forget($cacheKey);

        return $this->getInventory($shop);
    }
}
