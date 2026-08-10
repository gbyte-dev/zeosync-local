<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\Setting;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoSkuMappingService
{
    public function handle(Shop $shop, ?array $shopifyInventory = null, ?array $amazonInventory = null): void
    {
        $setting = Setting::where('shop_id', $shop->id)->first();

        if (!$setting || !$setting->auto_sku_mapping) {
            Log::info('AUTO SKU MAPPING DISABLED', ['shop' => $shop->shop]);
            return;
        }

        Log::info('AUTO SKU MAPPING STARTED', ['shop' => $shop->shop]);

        $shopifyInventory = $shopifyInventory ?? $this->loadShopifyInventory($shop);
        $amazonInventory = $amazonInventory ?? $this->loadAmazonInventory($shop);

        if (empty($shopifyInventory) || empty($amazonInventory)) {
            Log::info('AUTO SKU MAPPING SKIPPED - Missing inventory data in cache', ['shop' => $shop->shop]);
            return;
        }

        $existingMappings = ProductMarketplaceMapping::where('shop_id', $shop->id)->get();
        $mappedShopifyVariants = $existingMappings->pluck('shopify_variant_id')->filter()->toArray();
        $mappedAmazonSkus = $existingMappings->pluck('amazon_sku')->filter()->toArray();

        foreach ($shopifyInventory as $shopifyItem) {
            $shopifySku = trim($shopifyItem['sku'] ?? '');

            if (empty($shopifySku) || $shopifySku === 'No SKU') {
                continue;
            }

            if ($this->isShopifyMapped($shopifyItem['vid'], $mappedShopifyVariants)) {
                Log::info('SHOPIFY ALREADY MAPPED', ['sku' => $shopifySku, 'shop' => $shop->shop]);
                continue;
            }

            $amazonItem = $this->findMatchingSku($shopifySku, $amazonInventory);

            if (!$amazonItem) {
                continue;
            }

            $amazonSku = trim($amazonItem['sku'] ?? '');

            if (empty($amazonSku)) {
                continue;
            }

            if ($this->isAmazonMapped($amazonSku, $mappedAmazonSkus)) {
                Log::info('AMAZON SKU ALREADY MAPPED', ['sku' => $amazonSku, 'shop' => $shop->shop]);
                continue;
            }

            $this->createMapping($shop, $shopifyItem, $amazonItem);

            $mappedShopifyVariants[] = (string) $shopifyItem['vid'];
            $mappedAmazonSkus[] = (string) $amazonSku;
        }

        Log::info('AUTO SKU MAPPING COMPLETED', ['shop' => $shop->shop]);
    }

    private function loadShopifyInventory(Shop $shop): array
    {
        return Cache::get("shopify_inventory_{$shop->shop}", []);
    }

    private function loadAmazonInventory(Shop $shop): array
    {
        $marketplaceId = $shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER';

        return Cache::get("amazon_inventory_{$shop->id}_{$marketplaceId}", []);
    }

    private function findMatchingSku(string $sku, array $amazonInventory): ?array
    {
        foreach ($amazonInventory as $item) {
            $amazonSku = trim($item['sku'] ?? '');
            if (strcasecmp($amazonSku, $sku) === 0) {
                return $item;
            }
        }

        return null;
    }

    private function isShopifyMapped($variantId, array $mappedVariants): bool
    {
        return in_array((string) $variantId, $mappedVariants, true);
    }

    private function isAmazonMapped(string $sku, array $mappedSkus): bool
    {
        return in_array($sku, $mappedSkus, true);
    }

    private function createMapping(Shop $shop, array $shopifyItem, array $amazonItem): void
    {
        Log::info('MATCH FOUND', [
            'shopify_sku' => $shopifyItem['sku'],
            'amazon_sku'  => $amazonItem['sku']
        ]);

        $localProduct = Product::where('shop_id', $shop->id)
            ->where('shopify_id', $shopifyItem['pid'])
            ->first();

        $localVariantId = null;
        if ($localProduct && !empty($localProduct->variants)) {
            $variants = is_array($localProduct->variants) ? $localProduct->variants : json_decode($localProduct->variants, true);
            if (is_array($variants)) {
                $selectedVariant = collect($variants)->firstWhere('id', (int) $shopifyItem['vid']);
                if ($selectedVariant) {
                    $localVariantId = $selectedVariant['id'] ?? null;
                }
            }
        }

        ProductMarketplaceMapping::create([
            'shop_id'                   => $shop->id,
            'product_id'                => $localProduct ? $localProduct->id : null,
            'variant_id'                => $localVariantId,
            'shopify_product_id'        => (string) $shopifyItem['pid'],
            'shopify_variant_id'        => (string) $shopifyItem['vid'],
            'shopify_inventory_item_id' => (string) $shopifyItem['inventory_item_id'],
            'amazon_sku'                => (string) $amazonItem['sku'],
            'quantity'                  => (int) ($shopifyItem['qty'] ?? 0),
            'sync_status'               => 'pending',
            'submission_status'         => 'not_submitted',
        ]);

        Log::info('CREATED NEW MAPPING', [
            'shop'               => $shop->shop,
            'shopify_variant_id' => $shopifyItem['vid'],
            'amazon_sku'         => $amazonItem['sku']
        ]);
    }
}
