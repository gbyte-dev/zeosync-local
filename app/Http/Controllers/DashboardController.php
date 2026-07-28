<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopSubscription;
use App\Http\Controllers\ShopifyController;
use App\Models\Setting;
use App\Models\Log as SyncLog;
use App\Models\ReturnItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\ShopifyOrder;
use App\Models\ProductMarketplaceMapping;


class DashboardController extends ShopifyController
{
    // public function index(Request $request)
    // {
    //     $shopModel = $this->getActiveShop($request);

    //     \Log::info('Dashboard Request', [
    //         'url' => $request->fullUrl(),
    //         'shop' => $shopModel?->shop,
    //         'session' => session()->all(),
    //     ]);

    //     if (!$shopModel) {
    //         return view('welcome');
    //     }

    //     \Log::info('DASHBOARD SHOP', [
    //         'shop' => $shopModel->shop
    //     ]);

    //     return view('dashboard', [
    //         'totalProducts' => Product::where('shop_id', $shopModel->id)->count(),
    //         'totalOrders'   => 0,
    //         'isConnected'   => !empty($shopModel->amazon_seller_id),
    //         'recentLogs'    => SyncLog::where('shop_id', $shopModel->id)
    //             ->latest()
    //             ->take(10)
    //             ->get(),
    //     ]);
    // }

    public function index(Request $request)
    {
        // Fallback to active shop identification strategy (Adjust if using osiset/laravel-shopify)
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            $activeShop = $request->attributes->get('active_shop')
                ?? $request->query('shop');

            if (!$activeShop) {
                abort(404, 'Active shop not found.');
            }

            $shop = Shop::where('shop', $activeShop)
                ->where('is_active', 1)
                ->firstOrFail();
        }

        $shopId = $shop->id;
        $thirtyDaysAgo = \Carbon\Carbon::today()->subDays(30);
        $cacheTtl = 300; // Cache heavy charts for 5 minutes

        // 1. Top KPI Aggregates (Eager & efficient counts)
        $totalProducts = Product::where('shop_id', $shopId)->count();
        $totalOrders = ShopifyOrder::where('shop_id', $shopId)->count();
        $totalMapped = ProductMarketplaceMapping::where('shop_id', $shopId)->count();

        // System Health Status
        $isShopConnected = true; // Replace with actual OAuth token check

        // 2. Chart.js Data Generation (Cached)
        // A. Orders Timeline (Last 30 Days)
        $ordersTimeline = Cache::remember("shop_{$shopId}_orders_timeline", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return ShopifyOrder::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')->orderBy('date')->get();
        });

        // B. Product Creation Trend (Last 30 Days)
        $productTrend = Cache::remember("shop_{$shopId}_product_trend", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return Product::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')->orderBy('date')->get();
        });

        // 3. Recent Activity Logs
        $recentLogs = collect([]);
        if (class_exists(SyncLog::class)) {
            $recentLogs = SyncLog::where('shop_id', $shopId)->latest()->take(8)->get();
        }

        // Return only the exact variables required by the frontend
        return view('dashboard', compact(
            'totalProducts',
            'totalMapped',
            'totalOrders',
            'isShopConnected',
            'ordersTimeline',
            'productTrend',
            'recentLogs'
        ));
    }


    // public function install(Request $request)
    // {
    //     $activeShop =
    //         $request->get('shop')
    //         ?? session('amazon_shop');

    //     if (!$activeShop) {
    //         return "Shop missing (no query, no session)";
    //     }

    //     // normalize
    //     if (!str_contains($activeShop, '.myshopify.com')) {
    //         $activeShop .= '.myshopify.com';
    //     }

    //     session(['amazon_shop' => $activeShop]);

    //     $shop = Shop::where('shop', $activeShop)->first();

    //     if ($shop) {
    //         return view('dashboard');
    //     }

    //     return view('welcome');
    // }
}
