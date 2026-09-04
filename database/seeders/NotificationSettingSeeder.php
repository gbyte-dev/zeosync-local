<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NotificationSetting;

class NotificationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notifications = [

            [
                'notification_key' => 'shopify_connected',
                'title' => 'Shopify store connected successfully',
                'description' => 'Send a notification when a Shopify store is connected successfully.',
            ],

            [
                'notification_key' => 'amazon_account_status',
                'title' => 'Amazon seller account connected/disconnected',
                'description' => 'Send a notification when an Amazon seller account is connected or disconnected.',
            ],

            [
                'notification_key' => 'payment_failed',
                'title' => 'Subscription/payment failed',
                'description' => 'Send a notification when a subscription payment fails.',
            ],

            [
                'notification_key' => 'trial_ending',
                'title' => 'Trial ending soon',
                'description' => 'Send a notification when a user trial is ending soon.',
            ],

            [
                'notification_key' => 'new_user_registered',
                'title' => 'New User Registered',
                'description' => 'Send a notification when a new user registers.',
            ],

            [
                'notification_key' => 'contact_enquiry',
                'title' => 'New Contact Enquiry',
                'description' => 'Send a notification when a user submits a contact or enterprise enquiry.',
            ],
            [
                'notification_key' => 'ai_error',
                'title' => 'AI Service Error',
                'description' => 'Send a notification when an AI provider, configuration, quota, rate limit, authentication, or generation error occurs.',
            ],
        ];

        foreach ($notifications as $item) {

            NotificationSetting::updateOrCreate(
                [
                    'notification_key' => $item['notification_key']
                ],
                [
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'email_enabled' => true,
                    'in_app_enabled' => true,
                ]
            );
        }
    }
}
