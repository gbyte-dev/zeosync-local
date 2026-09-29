<?php

use App\Models\AdminNotification;
use App\Models\ContactInquiry;
use App\Models\ShopSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('isSubscriptionActive')) {
    function isSubscriptionActive($shopId)
    {
        return app(\App\Services\SubscriptionService::class)->hasActiveEntitlement($shopId);
    }

    function getShopActiveData($shopId)
    {
        $shops = DB::table('shops')->where('shop_id', $shopId)->latest()->first();

        if (!$shops) {
            return false;
        }

        if ($shops->shop_name && $shops->email) {
            return true;
        }

        return false;
    }
}

if (!function_exists('getAdminNotificationUnread')) {
    function getAdminNotificationUnread()
    {
        $notifications = AdminNotification::where('is_read', 0)->orderBy('created_at', 'desc')->get();
        return $notifications;
    }
}

if (!function_exists('getContactInquiryUnread')) {
    function getContactInquiryUnread()
    {
        $contacts = ContactInquiry::where('is_read', 0)->orderBy('created_at', 'desc')->get();
        return $contacts;
    }
}
