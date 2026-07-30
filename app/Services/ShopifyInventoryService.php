<?php

namespace App\Services;

use App\Services\ShopifyService;
use App\Models\Shop;
use App\Models\ProductMarketplaceMapping;
use Illuminate\Support\Facades\Cache;

class ShopifyInventoryService
{
    public function getInventory(Shop $shop): array
    {
        $cacheKey = "shopify_inventory_{$shop->shop}";

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
                        $response['data']
                    );

                    $cursor = $response['next_cursor'];
                } while ($response['has_next']);

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
