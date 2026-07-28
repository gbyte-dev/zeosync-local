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

    public static function send($key, $title, $message)
    {
        $setting = NotificationSetting::where(
            'notification_key',
            $key
        )->first();

        if (!$setting) {
            return;
        }

        if ($setting->in_app_enabled) {
            AdminNotification::create([
                'notification_key' => $key,
                'title' => $title,
                'message' => $message,
            ]);
        }
    }
}
