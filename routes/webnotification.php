<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

Route::prefix('admin')->group(function () {
    Route::middleware('auth:admin')->group(function () {

        //notification
        Route::get('/notification', [NotificationController::class, 'index'])
            ->name('admin.notification');

        Route::post('/notification-settings', [NotificationController::class, 'saveSettings'])
            ->name('admin.notification.settings.save');

        Route::post(
            '/admin/trial-ending-notify',
            [NotificationController::class, 'sendTrialEndingNotifications']
        )->name('admin.trial.ending.notify');

        Route::post('/admin/notification/{id}/read', [NotificationController::class, 'markAdminNotificationRead'])
            ->name('admin.notification.read');
    });
});

Route::post('/user/notification/{id}/read', [NotificationController::class, 'markUserNotificationRead'])
    ->name('user.notification.read');

Route::post('/user/notification/mark-all-read', [NotificationController::class, 'markAllUserNotificationsRead'])
    ->name('user.notification.markAllRead');
