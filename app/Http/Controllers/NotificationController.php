<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSetting;
use App\Services\NotificationService;
use App\Models\AdminNotification;
use App\Models\UserNotification;
use App\Models\UserNotificationSetting;
use App\Models\ShopSubscription;
use App\Services\UserNotificationService;


class NotificationController extends Controller
{
    public function index()
    {
        $notifications = NotificationSetting::all();
        $totalNotifications = AdminNotification::count();
        $emailEnabled = $notifications->where('email_enabled', 1)->count();
        $adminNotifications = AdminNotification::latest()
            ->take(3)
            ->get();

        $inAppEnabled = $notifications->where('in_app_enabled', 1)->count();
        $latestNotifications = AdminNotification::latest()->paginate(10);

        $lastUpdated = $notifications->max('updated_at');
        return view('admin.notification.index', compact(
            'notifications',
            'totalNotifications',
            'emailEnabled',
            'inAppEnabled',
            'lastUpdated',
            'latestNotifications',
            'adminNotifications'
        ));
    }

    public function updateNotificationSettings(Request $request)
    {
        foreach (UserNotificationSetting::all() as $notification) {

            $data = $request->input(
                'notifications.' . $notification->notification_key,
                []
            );

            $notification->update([
                'mail_enabled'  => isset($data['email']),
                'app_enabled' => isset($data['in_app']),
            ]);
        }

        return back()->with('success', 'Notification settings updated successfully.');
    }

    public function usernotification(Request $request)
    {
        $shop = $request->shop ?? session('active_shop');
        $shopModel = \App\Models\Shop::where('shop', $shop)->first();
        $shopId = $shopModel?->id;

        // Mark all unread notifications as read when viewing the page
        UserNotification::where('shop_id', $shopId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => now(),
            ]);

        $latestNotifications = UserNotification::where('shop_id', $shopId)
            ->latest()
            ->paginate(10);

        $totalNotifications = UserNotification::where('shop_id', $shopId)->count();
        $emailEnabled = UserNotificationSetting::where('mail_enabled', 1)->count();
        $inAppEnabled = UserNotificationSetting::where('app_enabled', 1)->count();
        $lastUpdated = UserNotification::where('shop_id', $shopId)->max('created_at');

        return view('notification.index', compact(
            'latestNotifications',
            'totalNotifications',
            'emailEnabled',
            'inAppEnabled',
            'lastUpdated'
        ));
    }

    public function sendTrialEndingNotifications()
    {
        \Log::info('Trial Ending Button Clicked');

        $subscriptions = ShopSubscription::where('status', 'trialing')
            ->whereBetween('trial_ends_at', [
                now(),
                now()->addDay()
            ])
            ->get();

        $count = 0;
        $skipped = 0;

        foreach ($subscriptions as $subscription) {

            $alreadySentToday = UserNotification::where('shop_id', $subscription->shop_id)
                ->where('notification_key', 'trial_ending')
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadySentToday) {
                $skipped++;
                continue;
            }

            UserNotificationService::send(
                $subscription->shop_id,
                'trial_ending',
                'Trial Ending Soon',
                'Your trial will end on ' . $subscription->trial_ends_at->format('d M Y h:i A') . '. Please upgrade your plan.'
            );

            $count++;
        }

        if ($count > 0) {
            return back()->with(
                'success',
                "{$count} trial ending notification sent successfully."
            );
        }

        return back()->with(
            'warning',
            'Today\'s trial ending notification has already been sent. It can be sent again tomorrow.'
        );
    }

    public function markAdminNotificationRead($id)
    {
        \Log::info('Admin notification read hit', ['id' => $id]);

        $notification = AdminNotification::findOrFail($id);

        $notification->update([
            'is_read' => 1,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true
        ]);
    }

    public function markUserNotificationRead($id)
    {
        $notification = UserNotification::findOrFail($id);

        if ($notification->is_read == 0) {
            $notification->update([
                'is_read' => 1,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true
        ]);
    }

    public function markAllUserNotificationsRead(Request $request)
    {
        $shop = $request->shop ?? session('active_shop');
        $shopModel = \App\Models\Shop::where('shop', $shop)->first();
        $shopId = $shopModel?->id;

        UserNotification::where('shop_id', $shopId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => now(),
            ]);

        return redirect()->back()->with('success', 'All notifications marked as read successfully.');
    }

    public function markAllAdminNotificationsRead()
    {
        AdminNotification::where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => now(),
            ]);

        return redirect()->back()->with('success', 'All notifications marked as read successfully.');

    }

    public function removeUserNotification($id)
    {
        $notification = UserNotification::findOrFail($id);
        $notification->delete();

        return redirect()->back()->with('success', 'Notification removed successfully.');
    }

    public function removeAdminNotification($id)
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->delete();

        return redirect()->back()->with('success', 'Notification removed successfully.');
    }

    public function removeAllUserNotifications(Request $request)
    {
        $shop = $request->shop ?? session('active_shop');
        $shopModel = \App\Models\Shop::where('shop', $shop)->first();
        $shopId = $shopModel?->id;

        UserNotification::where('shop_id', $shopId)->delete();

        return redirect()->back()->with('success', 'All notifications removed successfully.');
    }

    public function removeAllAdminNotifications()
    {
        AdminNotification::query()->delete();
        return redirect()->back()->with('success', 'All notifications removed successfully.');
    }



}
