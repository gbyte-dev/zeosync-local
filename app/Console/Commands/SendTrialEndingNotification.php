<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShopSubscription;
use App\Services\UserNotificationService;

class SendTrialEndingNotification extends Command
{
    protected $signature = 'app:send-trial-ending-notification';

    protected $description = 'Send notification when trial is ending soon';

    public function handle()
    {
        $subscriptions = ShopSubscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->whereNull('trial_ending_notified_at')
            ->whereBetween('trial_ends_at', [
                now(),
                now()->addDay()
            ])
            ->get();

        foreach ($subscriptions as $subscription) {

            UserNotificationService::send(
                $subscription->shop_id,
                'trial_ending',
                'Trial Ending Soon',
                'Your trial will expire on ' .
                $subscription->trial_ends_at->format('d M Y h:i A') .
                '. Please upgrade your plan to continue using AmazonSync.'
            );

            $subscription->update([
                'trial_ending_notified_at' => now()
            ]);

            $this->info("Notification sent to Shop ID {$subscription->shop_id}");
        }

        $this->info('Trial ending notification process completed.');
    }
}