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
use Illuminate\Support\Facades\DB;

$service = app(AmazonProductQueryService::class);
$inventoryController = app(InventoryController::class);
$productController = app(ProductSchemaController::class);

echo "========================================================================\n";
echo "SHOP-SCOPED PARITY & PRODUCTION VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$targetDomain = 'store-test-ye80qt78.myshopify.com';
$targetShop = Shop::where('shop', $targetDomain)->first();

if (!$targetShop) {
    echo "Note: Domain '{$targetDomain}' not directly found in local DB.\n";
    echo "Available shops in local database:\n";
    $allShops = Shop::all();
    foreach ($allShops as $s) {
        $prodCount = AllProduct::where('user_id', $s->id)->whereNull('parent_id')->count();
        echo "  - Shop #{$s->id} ({$s->shop}): is_active={$s->is_active}, token=" . (empty($s->access_token) ? 'EMPTY' : 'SET') . ", allproducts_count={$prodCount}\n";
    }
    echo "\nRunning complete verification across ALL available shops in local database...\n\n";
    $testShops = $allShops;
} else {
    $testShops = collect([$targetShop]);
}

$allTestsPassed = true;

foreach ($testShops as $shop) {
    $shopId = (int) $shop->id;

    // 1. Raw SQL Query baseline
    $sqlProducts = DB::select("
        SELECT id, user_id, sku, status, submission_status
        FROM allproducts
        WHERE user_id = :shop_id
          AND parent_id IS NULL
          AND (
              submission_status IS NOT NULL
              OR status = 'draft'
          )
    ", ['shop_id' => $shopId]);

    $sqlCount = count($sqlProducts);
    $sqlIds = collect($sqlProducts)->pluck('id')->sort()->values()->toArray();
    $sqlSkus = collect($sqlProducts)->pluck('sku')->sort()->values()->toArray();

    // 2. Shared AmazonProductQueryService (used by Products -> Amazon Products)
    $serviceProducts = $service->getProductsForShop($shop);
    $serviceCount = $serviceProducts->count();
    $serviceIds = $serviceProducts->pluck('id')->sort()->values()->toArray();
    $serviceSkus = $serviceProducts->pluck('sku')->sort()->values()->toArray();

    // 3. InventoryController::amazonProducts endpoint
    $request = new Request();
    $request->attributes->set('active_shop_model', $shop);
    session(['active_shop' => $shop->shop, '_shopify_verified_shop' => $shop->shop]);
    
    $response = $inventoryController->amazonProducts($request);
    $responseData = json_decode($response->getContent(), true);
    $invProducts = $responseData['products'] ?? [];
    $invCount = count($invProducts);
    $invIds = collect($invProducts)->pluck('id')->sort()->values()->toArray();
    $invSkus = collect($invProducts)->pluck('sku')->sort()->values()->toArray();

    // 4. Check Shop-Scoping for EVERY returned row
    $shopScopedStrict = true;
    foreach ($invProducts as $item) {
        if ((int) $item['user_id'] !== $shopId) {
            $shopScopedStrict = false;
            echo "CRITICAL ERROR: Item ID {$item['id']} has user_id {$item['user_id']}, expected {$shopId}!\n";
        }
    }

    $countMatch = ($sqlCount === $serviceCount && $serviceCount === $invCount);
    $idMatch = ($sqlIds === $serviceIds && $serviceIds === $invIds);
    $skuMatch = ($sqlSkus === $serviceSkus && $serviceSkus === $invSkus);

    $passed = ($countMatch && $idMatch && $skuMatch && $shopScopedStrict);
    if (!$passed) {
        $allTestsPassed = false;
    }

    echo "------------------------------------------------------------------------\n";
    echo "Shop #{$shopId} ({$shop->shop}):\n";
    echo "  - Raw SQL Products Count:           {$sqlCount}\n";
    echo "  - Products -> Amazon Products Count: {$serviceCount}\n";
    echo "  - Inventory -> Amazon Products Count: {$invCount}\n";
    echo "  - Count Equality (Products == Inv): " . ($countMatch ? "PASS" : "FAIL") . "\n";
    echo "  - ID Set Exact Match:               " . ($idMatch ? "PASS" : "FAIL") . "\n";
    echo "  - SKU Set Exact Match:              " . ($skuMatch ? "PASS" : "FAIL") . "\n";
    echo "  - All rows user_id === shop_id:      " . ($shopScopedStrict ? "PASS" : "FAIL") . "\n";

    if ($invCount > 0) {
        echo "  - Rows Verified (id | user_id | sku | status | submission_status):\n";
        foreach ($invProducts as $p) {
            $rawMatch = collect($sqlProducts)->firstWhere('id', $p['id']);
            echo "      [ID: {$p['id']}] user_id: {$p['user_id']} | sku: {$p['sku']} | status: {$p['status']} | submission_status: " . ($rawMatch->submission_status ?? 'null') . "\n";
        }
    }
}

echo "\n========================================================================\n";
echo "NEGATIVE TEST: NON-SHOP PRODUCTS AND INVENTORY-ONLY EXCLUSION\n";
echo "========================================================================\n";

// Ensure cross-shop products never leak
$allProductsInDb = AllProduct::count();
echo "Total AllProduct records across all shops in DB: {$allProductsInDb}\n";

$shopWithProducts = Shop::find(21); // Shop 21 has 9 products
if ($shopWithProducts) {
    $request = new Request();
    $request->attributes->set('active_shop_model', $shopWithProducts);
    session(['active_shop' => $shopWithProducts->shop, '_shopify_verified_shop' => $shopWithProducts->shop]);
    
    $response = $inventoryController->amazonProducts($request);
    $responseData = json_decode($response->getContent(), true);
    $invItems = $responseData['products'] ?? [];
    
    $otherShopProductIds = AllProduct::where('user_id', '!=', 21)->pluck('id')->toArray();
    $leakedOtherShopIds = array_intersect(collect($invItems)->pluck('id')->toArray(), $otherShopProductIds);

    if (empty($leakedOtherShopIds)) {
        echo "Cross-Shop Isolation Test (Shop #21): PASS (Zero records from other shops leaked)\n";
    } else {
        echo "Cross-Shop Isolation Test: FAIL (Found leaked IDs: " . implode(', ', $leakedOtherShopIds) . ")\n";
        $allTestsPassed = false;
    }
}

echo "\nFINAL SUITE RESULT: " . ($allTestsPassed ? "ALL STRICT SHOP-SCOPING CHECKS PASSED" : "FAILED") . "\n";
