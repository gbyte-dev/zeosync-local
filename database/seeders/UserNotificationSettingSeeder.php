<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\UserNotificationSetting;

class UserNotificationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notifications = [
            [
                'notification_key' => 'order_sync',
                'title' => 'Order Sync Started/Completed',
                'description' => 'Notify when order sync starts or completes.',
            ],
            [
                'notification_key' => 'amazon_token_expired',
                'title' => 'Amazon API Token Expired',
                'description' => 'Notify when Amazon API token expires.',
            ],
            [
                'notification_key' => 'app_update',
                'title' => 'New App Version/Features Released',
                'description' => 'Notify when new app updates are released.',
            ],
            [
                'notification_key' => 'inventory_stock_update',
                'title' => 'Inventory Stock Updates',
                'description' => 'Notify when inventory stock is updated.',
            ],
            [
                'notification_key' => 'stock_difference',
                'title' => 'Stock Difference Amazon and Shopify',
                'description' => 'Notify when Amazon and Shopify stock mismatch is found.',
            ],
        ];

        foreach ($notifications as $item) {
            UserNotificationSetting::updateOrCreate(
                ['notification_key' => $item['notification_key']],
                [
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'app_enabled' => true,
                    'mail_enabled' => true,
                ]
            );
        }
    }
}
