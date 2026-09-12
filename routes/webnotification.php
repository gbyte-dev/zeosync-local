<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

Route::prefix('admin')->middleware([\App\Http\Middleware\VerifyAdminRequest::class, \App\Http\Middleware\EnsureAdminAuthenticated::class])->group(function () {

        //notification
        Route::get('/notification', [NotificationController::class, 'index'])
            ->name('admin.notification');

        Route::post('/notification-settings', [NotificationController::class, 'saveSettings'])
            ->name('admin.notification.settings.save');

        Route::post( '/trial-ending-notify', [NotificationController::class, 'sendTrialEndingNotifications']
        )->name('admin.trial.ending.notify');

        Route::post('/notification/{id}/read', [NotificationController::class, 'markAdminNotificationRead'])
            ->name('admin.notification.read');
});

Route::post('/user/notification/{id}/read', [NotificationController::class, 'markUserNotificationRead'])
    ->middleware('ip.rate:5,60')->name('user.notification.read');

Route::post('/user/notifications/mark-viewed', [NotificationController::class, 'markViewedUserNotificationsRead']
)->middleware('ip.rate:5,60')->name('user.notifications.markViewed');

/* Route::post('/user/notification/mark-all-read', [NotificationController::class, 'markAllUserNotificationsRead'])
    ->name('user.notification.markAllRead'); */
