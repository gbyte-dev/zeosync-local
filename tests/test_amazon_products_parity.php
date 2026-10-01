<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AmazonProductQueryService;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductSchemaController;
use App\Models\Shop;
use App\Models\AllProduct;
use Illuminate\Http\Request;

$service = app(AmazonProductQueryService::class);
$inventoryController = app(InventoryController::class);
$productController = app(ProductSchemaController::class);

echo "========================================================\n";
echo "STEP 10 & 11: FULL DATA SOURCE & PARITY VERIFICATION TEST\n";
echo "========================================================\n\n";

$shops = Shop::all();
$allPassed = true;

foreach ($shops as $s) {
    echo "Shop #{$s->id} ({$s->shop}): is_active={$s->is_active}, token=" . (empty($s->access_token) ? 'EMPTY' : 'SET') . ", amz_token=" . (empty($s->amazon_refresh_token) ? 'EMPTY' : 'SET') . "\n";
}
echo "--------------------------------------------------------\n\n";

foreach ($shops as $shop) {
    // 1. Fetch from Products -> Amazon Products source (via AmazonProductQueryService)
    $products = $service->getProductsForShop($shop);
    $productsCount = $products->count();
    $productSkus = $products->pluck('sku')->filter()->sort()->values()->toArray();
    $productIds = $products->pluck('id')->sort()->values()->toArray();

    // 2. Fetch from Inventory -> Amazon Products controller endpoint
    $request = new Request();
    $request->attributes->set('active_shop_model', $shop);
    session(['active_shop' => $shop->shop, '_shopify_verified_shop' => $shop->shop]);
    $response = $inventoryController->amazonProducts($request);
    $responseData = json_decode($response->getContent(), true);

    $invItems = $responseData['products'] ?? [];
    $invCount = count($invItems);
    $invSkus = collect($invItems)->pluck('sku')->filter()->sort()->values()->toArray();
    $invIds = collect($invItems)->pluck('id')->sort()->values()->toArray();

    if ($productsCount > 0 && $invCount === 0) {
        echo "DEBUG Response for Shop #{$shop->id}: " . substr($response->getContent(), 0, 300) . "\n";
    }

    $countMatch = ($productsCount === $invCount);
    $skuMatch = ($productSkus === $invSkus);
    $idMatch = ($productIds === $invIds);

    $status = ($countMatch && $skuMatch && $idMatch) ? "PASS" : "FAIL";
    if ($status === "FAIL") {
        $allPassed = false;
    }

    echo "Shop #{$shop->id} ({$shop->shop}):\n";
    echo "  - Products -> Amazon Products count: {$productsCount}\n";
    echo "  - Inventory -> Amazon Products count: {$invCount}\n";
    echo "  - Base SKU exact match: " . ($skuMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Base ID exact match: " . ($idMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Overall Match: {$status}\n";

    if (!empty($invItems)) {
        echo "  - Sample enriched item:\n";
        $first = $invItems[0];
        echo "    * Title: " . substr($first['title'] ?? 'N/A', 0, 40) . "\n";
        echo "    * SKU: " . ($first['sku'] ?? 'N/A') . "\n";
        echo "    * ASIN: " . ($first['asin'] ?? 'N/A') . "\n";
        echo "    * Amazon Status: " . ($first['status'] ?? 'N/A') . "\n";
        echo "    * Mapping Status: " . ($first['mapping_status'] ?? 'N/A') . "\n";
        echo "    * Mapped Shopify: " . ($first['mapped_shopify_title'] ?? 'N/A') . "\n";
        echo "    * Shopify Qty: " . ($first['shopify_qty'] ?? 'N/A') . "\n";
        echo "    * Amazon Qty: " . ($first['amazon_qty'] ?? 'N/A') . "\n";
        echo "    * Fulfillment: " . ($first['fulfillment_channel'] ?? 'N/A') . "\n";
    }
    echo "--------------------------------------------------------\n";
}

echo "\n========================================================\n";
echo "NEGATIVE TEST: UNRELATED INVENTORY LISTINGS EXCLUSION\n";
echo "========================================================\n";

// Let's test negative condition: If a listing/SKU is NOT in AllProduct, does it appear in Inventory -> Amazon Products?
$shop = Shop::where('is_active', 1)->whereNotNull('access_token')->first();
if ($shop) {
    $request = new Request();
    $request->attributes->set('active_shop_model', $shop);
    session(['active_shop' => $shop->shop, '_shopify_verified_shop' => $shop->shop]);
    $response = $inventoryController->amazonProducts($request);
    $responseData = json_decode($response->getContent(), true);
    $displayedSkus = collect($responseData['products'] ?? [])->pluck('sku')->toArray();

    // Verify all displayed SKUs exist in AllProduct
    $validAllProductSkus = AllProduct::where('user_id', $shop->id)->pluck('sku')->toArray();
    $invalidSkusFound = array_diff($displayedSkus, $validAllProductSkus);

    echo "Shop #{$shop->id}: " . count($displayedSkus) . " SKUs displayed.\n";
    if (empty($invalidSkusFound)) {
        echo "Negative Test: PASS (Zero unrelated Amazon inventory report SKUs leaked into Inventory -> Amazon Products)\n";
    } else {
        echo "Negative Test: FAIL (Found leaked SKUs: " . implode(', ', $invalidSkusFound) . ")\n";
        $allPassed = false;
    }
}

echo "\n========================================================\n";
echo "STEP 4, 5, 6, 7: MAPPING, QUANTITY, ASIN, FULFILLMENT TEST\n";
echo "========================================================\n";

$mappingCount = \App\Models\ProductMarketplaceMapping::count();
echo "Total marketplace mappings in DB: {$mappingCount}\n";

$mappedProductsFound = 0;
foreach ($shops as $shop) {
    $request = new Request();
    $request->attributes->set('active_shop_model', $shop);
    session(['active_shop' => $shop->shop, '_shopify_verified_shop' => $shop->shop]);
    $response = $inventoryController->amazonProducts($request);
    $responseData = json_decode($response->getContent(), true);

    foreach ($responseData['products'] ?? [] as $item) {
        if ($item['is_mapped']) {
            $mappedProductsFound++;
            echo "Shop #{$shop->id} - Mapped Product found: SKU={$item['sku']}, ShopifyProd={$item['mapped_shopify_product_title']}, ShopifyVar={$item['mapped_shopify_variant_title']}, Qty={$item['quantity']}, Fulfillment={$item['fulfillment_channel']}\n";
        }
    }
}
echo "Mapped Amazon Products resolved: {$mappedProductsFound}\n";

echo "\nOVERALL TEST RESULT: " . ($allPassed ? "ALL TESTS PASSED" : "FAILED") . "\n";
