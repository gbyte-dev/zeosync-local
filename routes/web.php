<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Middleware\ResolveActiveShop;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AmazonSandboxController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ProductSchemaController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;



require base_path('routes/webShopify.php');
require base_path('routes/webnotification.php');

// Route::get('/dashboard', [DashboardController::class, 'index'])->name('shopify.dashboard');

// Route::get('/verify', [DashboardController::class, 'install'])->name('dashboard');

Route::get('/', [ShopifyController::class, 'entry'])->name('crm.entry');
Route::get('/install', [ShopifyController::class, 'install'])->name('shopify.install');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/callback', [ShopifyController::class, 'callback'])->name('shopify.callback');
Route::middleware([  ResolveActiveShop::class, \App\Http\Middleware\CheckSubscription::class
])->group(function () {
    // Route::get('/products', [ShopifyController::class, 'products'])
    //     ->name('shopify.products');
    Route::get('/product/{id}', [ShopifyController::class, 'viewProduct'])->name('shopify.product.view');
    Route::get('/createProduct', [ShopifyController::class, 'create'])
    ->name('shopify.product.create');
    Route::get('/editProduct/{id}', [ShopifyController::class, 'editProduct'])->name('shopify.product.edit');
    Route::get('/orders', [ShopifyController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [ShopifyController::class, 'showOrder'])->name('orders.show');
    Route::get('return_refunds', [ReturnController::class, 'index'])->name('shopify.return');
    Route::get('returns/amazon', [ReturnController::class, 'amazon'])->name('shopify.returns.amazon');
    Route::get('returns/shopify', [ReturnController::class, 'shopify'])->name('shopify.returns.shopify');
    Route::get('returns/amazon/{id}', [ReturnController::class, 'viewAmazon'])->name('shopify.returns.view.amazon');
    Route::get('returns/shopify/{id}', [ReturnController::class, 'viewShopify'])->name('shopify.returns.view.shopify');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('shopify.inventory.index');
    Route::get('logs', [SettingsController::class, 'logs'])->name('shopify.logs');
    Route::post('logs/remove-all', [SettingsController::class, 'removeAllLogs'])->name('shopify.logs.remove.all');
    Route::delete('logs/{id}', [SettingsController::class, 'removeLog'])->name('shopify.logs.remove');
});

Route::middleware([ResolveActiveShop::class])->group(function () {
    //  product page route 
    Route::get('/products', [ShopifyController::class, 'products'])->name('shopify.products');

    Route::post('/createProduct', [ShopifyController::class, 'createProduct'])->name('shopify.product.create.post');
    // edit product
    // Route::get('/editProduct/{id}', [ShopifyController::class, 'editProduct'])->name('shopify.product.edit');
    Route::put('/updateProduct/{id}', [ShopifyController::class, 'updateProduct'])->name('shopify.product.update');
    Route::post('/updateProduct/{id}', [ShopifyController::class, 'updateProduct'])->name('shopify.product.update.post');
    Route::post('/product/delete/{id}', [ShopifyController::class, 'deleteProduct'])->name('shopify.product.delete');
    Route::get('plans', [ShopifyController::class, 'plans'])->name('plans.index');
    //Route::post('plans/subscribe', [ShopifyController::class, 'subscribeToPlan'])->name('plans.subscribe'); // TO MAKE THIS WORKING REMOVE SAME ROUTE IN WEBSHOPIFY
    Route::get('billing/callback', [ShopifyController::class, 'billingCallback'])->name('shopify.billing.callback');
    Route::post('webhooks/shopify/orders/create', [ShopifyController::class, 'handleOrdersCreateWebhook'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->name('shopify.webhooks.orders.create');
    Route::get('/image-upload', [ImageController::class, 'index'])->name('shopify.imgupload');
    Route::post('/image-upload', [ImageController::class, 'store'])->name('shopify.imgupload.store');
    Route::delete('/image-upload/{id}', [ImageController::class, 'destroy'])
        ->name('shopify.imgupload.delete');
    Route::get('/rules', function () { return view('rules'); })->name('shopify.rules');
    Route::get('/help', function () {  return view('help');   })->name('shopify.help');
    Route::get('/support', function () {   return view('support'); })->name('shopify.support');
    Route::get('/notification', [NotificationController::class, 'usernotification'])->name('user.notification');
    Route::post( '/settings/notifications',  [NotificationController::class, 'updateNotificationSettings'] )->name('notification.settings.update');
    Route::delete('/notification/user/all', [NotificationController::class, 'removeAllUserNotifications'])->name('user.notification.delete.all');
    Route::delete('/notification/user/{id}', [NotificationController::class, 'removeUserNotification'])->name('user.notification.delete');
    Route::post('/notification/user/mark-all-read', [NotificationController::class, 'markAllUserNotificationsRead'])->name('user.notification.markAllRead');

});

Route::post('/sync-to-amazon/{id}', [ShopifyController::class, 'syncToAmazon'])->name('shopify.sync.amazon');
Route::get('/admin/shops/{id}', [ShopifyController::class, 'show'])->name('admin.shops.show');
Route::post('/cancel-plan', [PlanController::class, 'cancel'])->name('plans.cancel');
Route::get('/test-amazon', [ShopifyController::class, 'testAmazon']);
// Route::get('/clear', function () {
//     Artisan::call('optimize:clear');
//     return 'cleared';
// });
Route::get('/get-seller-id', [ShopifyController::class, 'getSellerIdFull']);
Route::get('/amazon/orders', [ShopifyController::class, 'getAmazonOrders']);
Route::prefix('amazon/sandbox')->group(function () {
    Route::get('/orders', [AmazonSandboxController::class, 'debugOrders'])->name('amazon.orders');
    Route::get('/product', [AmazonSandboxController::class, 'debugProduct'])->name('amazon.products');
});
Route::get('/amazon/order/{id}', [AmazonSandboxController::class, 'orderDetail'])
    ->name('amazon.order.detail');
Route::get('/amazon/orders/filter', [AmazonSandboxController::class, 'testOrderFilters']);
Route::get('/amazon/reports', [AmazonSandboxController::class, 'listReports']);
Route::get('/amazon/report/create', [AmazonSandboxController::class, 'createReturnReport']);

Route::post('/inventory/amazon/sync', [InventoryController::class, 'syncAmazonInventory'])->name('shopify.inventory.amazon.sync');
Route::post('/webhooks/app-uninstalled', [ShopifyController::class, 'handleAppUninstalledWebhook'])
    ->name('shopify.webhooks.app.uninstalled');

Route::get('/showProducts/{parent_id}', [ProductSchemaController::class, 'showProducts'])->name('user.product.showProducts.child');
Route::get('/sync-amazon-to-shopify/{sku}', [ProductSchemaController::class, 'SyncAmazonProductToShopify'])->name('user.product.syncAmazonToShopify');
Route::get('/sync-shopify-to-amazon/{id}', [ShopifyController::class, 'syncShopifyToAmazon'])->name('user.product.syncShopifyToAmazon');
Route::post('/remove_drafts/{product}', [ProductSchemaController::class, 'removeDrafts'])->name('user.product.removeDraft');
Route::get('/amazonView/{sku}', [TestController::class, 'amazonView'])->name('user.product.amazonView');

// Route::get('/check-mail-test', [TestController::class, 'checkMailTest'])->name('check.mail.test');
Route::get('/support_front', function () {   return view('support_front'); })->name('shopify.support_frony');

Route::get('/logout', [SettingsController::class, 'logout'])->name('site.logout');
Route::prefix('admin')->group(function () {
    Route::middleware('auth:admin')->group(function () {
        Route::get('/cat_activate/{category}', [ProductSchemaController::class, 'importSchema'])->name('admin.importSchema');
        Route::get('/cat_deactivate/{category}', [ProductSchemaController::class, 'deactivateSchema'])->name('admin.schema.deactivate');
        Route::post('/notification-mark-all', [NotificationController::class, 'markAllAdminNotificationsRead'])->name('admin.notification.marked');
        Route::delete('/notification/admin/all', [NotificationController::class, 'removeAllAdminNotifications'])->name('admin.notification.delete.all');
        Route::delete('/notification/admin/{id}', [NotificationController::class, 'removeAdminNotification'])->name('admin.notification.delete');
        Route::post('/create-category', [AdminController::class, 'categoryCreate'])->name('admin.category.create');
        Route::post('/update-category/{category}', [AdminController::class, 'categoryEdit'])->name('admin.category.update');
        Route::post('/delete-category/{category}', [AdminController::class, 'deleteCategory'])->name('admin.category.delete');
        Route::get('/import-categories', [CategoryController::class, 'importCategories'])->name('admin.import.categories');
        Route::get('/search-categories', [AdminController::class, 'categoryserchedChildren'])->name('admin.search.categories');

    });
});
