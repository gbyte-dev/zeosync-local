<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\UserNotification;
use App\Models\UserNotificationSetting;
use App\Models\NotificationSetting;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserNotificationService
{
    public static function send($shopId, $key, $title, $message)
    {
        Log::info('UserNotificationService HIT', [
            'shop_id' => $shopId,
            'key' => $key,
        ]);

        // trial_ending admin notification permission se chalega
        if (in_array($key, ['trial_ending', 'payment_failed'])) {

            $setting = NotificationSetting::where('notification_key', $key)->first();

            $appEnabled = $setting?->in_app_enabled;
            $mailEnabled = $setting?->email_enabled;

            Log::info('Admin Notification Setting Used', [
                'key' => $key,
                'exists' => (bool) $setting,
                'email_enabled' => $mailEnabled,
                'in_app_enabled' => $appEnabled,
            ]);
        } else {

            $setting = UserNotificationSetting::where('notification_key', $key)->first();

            $appEnabled = $setting?->app_enabled;
            $mailEnabled = $setting?->mail_enabled;

            Log::info('User Notification Setting Used', [
                'key' => $key,
                'exists' => (bool) $setting,
                'mail_enabled' => $mailEnabled,
                'app_enabled' => $appEnabled,
            ]);
        }

        if (!$setting) {
            Log::warning('Notification setting not found', [
                'key' => $key,
            ]);
            return;
        }

        if ($appEnabled) {
            UserNotification::create([
                'shop_id' => $shopId,
                'notification_key' => $key,
                'title' => $title,
                'message' => $message,
            ]);

            Log::info('User in-app notification created');
        }

        if ($mailEnabled) {
            $shop = Shop::find($shopId);

            if ($shop && !empty($shop->email)) {
                try {
                    Mail::to($shop->email)->send(
                        new NotificationMail($shop, $title, $message)
                    );

                    Log::info('User notification mail sent', [
                        'email' => $shop->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('User notification mail failed', [
                        'shop_id' => $shopId,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('Shop email not found, mail skipped', [
                    'shop_id' => $shopId,
                    'shop_found' => (bool) $shop,
                ]);
            }
        }
    }
}
