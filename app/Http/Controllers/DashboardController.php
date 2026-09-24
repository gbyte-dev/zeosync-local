<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ShopifyController;
use App\Models\AllProduct;
use App\Models\Log as SyncLog;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\ReturnItem;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\ShopSubscription;
use App\Services\ShopifyInventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class DashboardController extends ShopifyController
{
    protected ShopifyInventoryService $shopifyInventoryService;

    public function __construct()
    {
        $this->shopifyInventoryService = app(ShopifyInventoryService::class);
    }

    public function index(Request $request)
    {
        // Fallback to active shop identification strategy (Adjust if using osiset/laravel-shopify)
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            $activeShop = $request->attributes->get('active_shop') ?? $request->query('shop');

            if (!$activeShop) {
                abort(404, 'Active shop not found.');
            }

            $shop = Shop::where('shop', $activeShop)->where('is_active', 1)->first();

            if (!$shop) {
                return view('dashboard-waiting', ['shop' => $activeShop ]);
            }
        }

        $shopId = $shop->id;
        $inventory = $this->shopifyInventoryService->getInventory($shop);

        $amazonInventory = [];
        $amazonInventoryCacheExists = false;
        $isAmazonInventoryLoading = false;

        if (!empty($shop->amazon_seller_id)) {
            $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";

            $amazonInventoryCacheExists = Cache::has($cacheKey);
            if ($amazonInventoryCacheExists) {
                $amazonInventory = Cache::get($cacheKey, []);
            } else {
                $statusKey = "amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}";
                $status = Cache::get($statusKey, []);
                $isRefreshing = ($status['refreshing'] ?? false) || Cache::has("amazon_progress_{$shop->shop}");
                if ($isRefreshing) {
                    $isAmazonInventoryLoading = true;
                }
            }
        }
        $thirtyDaysAgo = \Carbon\Carbon::today()->subDays(30);
        $cacheTtl = 300;  // Cache heavy charts for 5 minutes

        // 1. Top KPI Aggregates (Eager & efficient counts)
        $totalShopifyProducts = Product::where('shop_id', $shopId)->count();
        $totalMappedProducts = ProductMarketplaceMapping::where('shop_id', $shopId)->count();
        $totalAmazonProducts = is_countable($amazonInventory) ? count($amazonInventory) : 0;
        $totalShopifyOrders = ShopifyOrder::where('shop_id', $shopId)->count();

        // Amazon Orders: Check existing local/cache-backed Amazon order data source
        $sellerId = $shop->amazon_seller_id ?? $shop->seller_id ?? null;
        $cachedAmazonOrders = [];
        if ($sellerId) {
            $cachedAmazonOrders = Cache::get('amazon_orders_' . $shop->shop . '_' . $sellerId)
                ?? Cache::get('amazon_orders_ai_' . $shop->shop . '_' . $sellerId)
                ?? Cache::get('amazon_orders_' . $shop->shop)
                ?? [];
        } else {
            $cachedAmazonOrders = Cache::get('amazon_orders_' . $shop->shop, []);
        }
        $totalAmazonOrders = is_countable($cachedAmazonOrders) ? count($cachedAmazonOrders) : 0;

        // Amazon Connection Status (Real state from current Shop model)
        $isAmazonConnected = !empty($shop->amazon_seller_id) && !empty($shop->amazon_refresh_token);

        // Aliases for backwards compatibility
        $totalProducts = $totalShopifyProducts;
        $totalMapped = $totalMappedProducts;
        $totalOrders = $totalShopifyOrders;
        $isShopConnected = $isAmazonConnected;

        // 2. Chart.js Data Generation (Cached)
        $ordersTimeline = Cache::remember("shop_{$shopId}_orders_timeline", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return ShopifyOrder::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });

        // B. Product Creation Trend (Last 30 Days)
        $productTrend = Cache::remember("shop_{$shopId}_product_trend", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return Product::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });

        // 3. Recent Activity Logs
        $recentLogs = collect([]);
        if (class_exists(SyncLog::class)) {
            $recentLogs = SyncLog::where('shop_id', $shopId)->latest()->take(8)->get();
        }
        // 4. Top Selling Products (Direct database query from shopify_orders without cache)
        $topSelling24h = $this->queryTopSellingProducts($shopId, '24h');
        $topSelling7d = $this->queryTopSellingProducts($shopId, '7d');

        $topSelling24hLabels = $topSelling24h->pluck('title')->map(fn($t) => \Illuminate\Support\Str::limit($t, 15))->values();
        $topSelling24hData = $topSelling24h->pluck('quantity')->values();

        $topSelling7dLabels = $topSelling7d->pluck('title')->map(fn($t) => \Illuminate\Support\Str::limit($t, 15))->values();
        $topSelling7dData = $topSelling7d->pluck('quantity')->values();

        $initialTimeframe = '24h';
        $topSellingProducts = $topSelling24h;
        $topSellingChartLabels = $topSelling24hLabels;
        $topSellingChartData = $topSelling24hData;

        $lowInventoryProducts = collect($inventory)
            ->map(function ($item) {
                if (!isset($item['available']) || $item['available'] === null) {
                    $item['available'] = $item['qty'] ?? 0;
                }
                return $item;
            })
            ->filter(function ($item) {
                $avail = $item['available'] ?? $item['qty'] ?? null;
                return $avail !== null && (int)$avail < 10;
            })
            ->sortBy(function ($item) {
                return (int)($item['available'] ?? $item['qty'] ?? 0);
            })
            ->take(7)
            ->values();

        $amazonLowInventoryProducts = collect($amazonInventory)
            ->filter(function ($item) {
                return ($item['quantity'] ?? 0) < 10;
            })
            ->sortBy('quantity')
            ->take(7)
            ->values();

        // Return only the exact variables required by the frontend
        return view('dashboard', compact(
            'totalShopifyProducts', 'totalMappedProducts', 'totalAmazonOrders',
            'totalAmazonProducts', 'totalShopifyOrders', 'isAmazonConnected',
            'totalProducts', 'totalMapped', 'totalOrders', 'isShopConnected',
            'ordersTimeline', 'productTrend', 'recentLogs', 'topSellingProducts',
            'topSellingChartLabels', 'topSellingChartData', 'topSelling24hLabels',
            'topSelling24hData', 'topSelling7dLabels', 'topSelling7dData',
            'initialTimeframe', 'lowInventoryProducts', 'amazonLowInventoryProducts',
            'amazonInventoryCacheExists', 'isAmazonInventoryLoading', 'shop'
        ));
    }

    /**
     * Authenticated endpoint to fetch fresh Top Selling Products directly from shopify_orders without cache.
     */
    public function topSellingProducts(Request $request)
    {
        $shop = $this->getActiveShop($request);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Active shop not found.',
            ], 403);
        }

        $period = $request->query('period', '24h');
        if (!in_array($period, ['24h', '7d'], true)) {
            $period = '24h';
        }

        $products = $this->queryTopSellingProducts((int) $shop->id, $period);

        $labels = $products->pluck('title')->map(fn($t) => \Illuminate\Support\Str::limit($t, 15))->values();
        $data = $products->pluck('quantity')->values();

        return response()->json([
            'success' => true,
            'period' => $period,
            'labels' => $labels,
            'data' => $data,
            'products' => $products,
        ]);
    }

    /**
     * Query top selling products directly from shopify_orders for the given shop_id and timeframe.
     */
    protected function queryTopSellingProducts(int $shopId, string $period = '24h'): Collection
    {
        $sinceDate = match ($period) {
            '7d' => now()->subDays(7),
            default => now()->subHours(24),
        };

        $orders = ShopifyOrder::where('shop_id', $shopId)
            ->where(function ($q) use ($sinceDate) {
                $q->where('order_created_at', '>=', $sinceDate)
                  ->orWhere('created_at', '>=', $sinceDate);
            })
            ->get();

        return $orders->flatMap(function ($order) {
                $items = is_array($order->line_items)
                    ? $order->line_items
                    : json_decode($order->line_items, true);

                return is_array($items) ? $items : [];
            })
            ->filter(function ($item) {
                return !empty($item['title']) || !empty($item['name']) || !empty($item['product_id']);
            })
            ->groupBy(function ($item) {
                return $item['product_id'] ?? $item['variant_id'] ?? $item['title'] ?? $item['name'] ?? 'item';
            })
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'title' => $first['title'] ?? $first['name'] ?? 'Unknown Product',
                    'quantity' => collect($items)->sum(fn($item) => (int)($item['quantity'] ?? 1)),
                    'amount' => collect($items)->sum(function ($item) {
                        return (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
                    }),
                ];
            })
            ->sortByDesc('quantity')
            ->take(5)
            ->values();
    }

    public function lowInventory(Request $request)
    {
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            $activeShop = $request->attributes->get('active_shop')
                ?? $request->query('shop');

            if (!$activeShop) {
                abort(404, 'Active shop not found.');
            }

            $shop = Shop::where('shop', $activeShop)
                ->where('is_active', 1)
                ->first();

            if (!$shop) {
                abort(404, 'Active shop not found.');
            }
        }

        $amazonInventory = [];
        $amazonInventoryCacheExists = false;

        if (!empty($shop->amazon_seller_id)) {
            $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
            $amazonInventoryCacheExists = Cache::has($cacheKey);
            $amazonInventory = Cache::get($cacheKey, []);
        }

        $amazonLowInventoryProducts = collect($amazonInventory)
            ->filter(function ($item) {
                return ($item['quantity'] ?? 0) < 10;
            })
            ->sortBy('quantity')
            ->values();

        return view('inventory.low-inventory', compact(
            'amazonLowInventoryProducts',
            'shop',
            'amazonInventoryCacheExists'
        ));
    }
}
