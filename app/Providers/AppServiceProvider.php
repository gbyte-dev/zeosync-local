<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\AdminNotification;
use App\Models\UserNotification;
use Illuminate\Support\Facades\View;
use App\Models\Shop;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        require_once app_path('helpers/mainHelper.php');
	        getMailSettings();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('helpers/SubscriptionHelper.php');

        View::composer('*', function ($view) {

            $adminNotifications = AdminNotification::where('is_read', 0)
                ->latest()
                ->take(3)
                ->get();

            $adminUnreadCount = AdminNotification::where('is_read', 0)
                ->count();

            // ---------------- User ----------------
            $currentShop = request('shop') ?? session('active_shop');

            $shopId = session('active_shop_id');

            if (!$shopId && $currentShop) {
                $shopId = Shop::where('shop', $currentShop)->value('id');
            }

            $userNotifications = collect();
            $userUnreadCount = 0;

            if ($shopId) {
                $userNotifications = UserNotification::where('shop_id', $shopId)
                    ->where('is_read', 0)->latest()->take(3)->get();
                $userUnreadCount = UserNotification::where('shop_id', $shopId)
                    ->where('is_read', 0)->count();
            }

            $view->with([
                'adminNotifications' => $adminNotifications,
                'unreadCount' => $adminUnreadCount,
                'userNotifications' => $userNotifications,
                'userUnreadCount' => $userUnreadCount,
            ]);
        });
    }
}
