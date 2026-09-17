<?php

namespace App\Services;


use App\Models\AdminNotification;
use App\Models\NotificationSetting;

class NotificationService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public static function send($key, $title, $message, $shopId = null)
    {
        $setting = NotificationSetting::where(
            'notification_key',
            $key
        )->first();

        if (!$setting) {
            return;
        }

        if ($setting->in_app_enabled) {
            $data = [
                'notification_key' => $key,
                'title' => $title,
                'message' => $message,
            ];

            if (!is_null($shopId)) {
                $data['shop_id'] = $shopId;
            }

            AdminNotification::create($data);
        }
    }
}
