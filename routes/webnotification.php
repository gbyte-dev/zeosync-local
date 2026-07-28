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
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', function () {
            return view('admin.auth.login');
        })->name('admin.login');
        Route::post('/login', [AdminAuthController::class, 'login'])
            ->name('admin.login.submit');
    });
    Route::middleware('auth:admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])
            ->name('admin.dashboard');
        Route::get('/dashboard', [AdminController::class, 'dashboard'])
            ->name('admin.dashboard');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::get('/shops', [AdminController::class, 'shops'])->name('admin.shops');
        Route::get('/orders', [AdminController::class, 'order'])->name('admin.orders');
        Route::get('/products', [AdminController::class, 'product'])->name('admin.products');
        Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
        Route::post('/settings', [AdminController::class, 'settingsupdate'])->name('admin.settings.update');
        Route::get('/category', [AdminController::class, 'category'])->name('admin.category');
        Route::get('/category/{id}/children', [AdminController::class, 'categoryChildren'])
            ->name('admin.category.children');
        Route::get('/allplans', [PlanController::class, 'index'])->name('admin.plans');
        Route::get('/plans/create', [PlanController::class, 'create'])->name('admin.plans.create');
        Route::post('/plans/create', [PlanController::class, 'store'])->name('admin.plans.store');
        Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('admin.plans.edit');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('admin.plans.update');
        Route::get('/mailtemplates', [MailTemplateController::class, 'index'])->name('admin.mailtemplates');
        Route::get('/mailtemplates/create', [MailTemplateController::class, 'create'])->name('admin.mailtemplates.create');
        Route::post('/mailtemplates/create', [MailTemplateController::class, 'store'])->name('admin.mailtemplates.store');
        Route::get('/mailtemplates/{mailtemplate}/edit', [MailTemplateController::class, 'edit'])->name('admin.mailtemplates.edit');
        Route::put('/mailtemplates/{mailtemplate}', [MailTemplateController::class, 'update'])->name('admin.mailtemplates.update');
        Route::post('/mailtemplates/{mailtemplate}', [MailTemplateController::class, 'destroy'])->name('admin.mailtemplates.delete');

        //notification
        Route::get('/notification', [NotificationController::class, 'index'])->name('admin.notification');
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
