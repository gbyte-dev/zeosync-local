<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AmazonConnect;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\MailTemplateController;
use App\Http\Controllers\NotificationController;

use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\AmazonSchemaController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\AmazonSmartFormController;






Route::prefix('admin')->group(function () {
    Route::middleware('auth:admin')->group(function () {
        //notification
        Route::get('/notification', [NotificationController::class, 'index'])->name('admin.notification');
        Route::post('/notification-settings', [NotificationController::class, 'saveSettings'])
            ->name('admin.notification.settings.save');
        Route::post(
            '/admin/trial-ending-notify',
            [NotificationController::class, 'sendTrialEndingNotifications']
        )->name('admin.trial.ending.notify');
      Route::post('/admin/notification/{id}/read', [NotificationController::class, 'markAdminNotificationRead'])->name('admin.notification.read');
    });
});

Route::post('/user/notification/{id}/read', [NotificationController::class, 'markUserNotificationRead'])
    ->name('user.notification.read');
