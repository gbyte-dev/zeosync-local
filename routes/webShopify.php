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
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\AmazonSchemaController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\AmazonSmartFormController;
use App\Http\Controllers\ProductSchemaController;
use App\Http\Controllers\InventoryMappingController;
use App\Http\Controllers\AmazonWebhookController;
use App\Models\Shop;
use App\Services\AmazonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\SyncAmazonInventoryJob;

Route::get('verify', [DashboardController::class, 'install'])->name('i.dashboard');
Route::get('verify', function () {
    return view('welcome');
})->name('crm.verify');
Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
Route::get('planview', [SubscriptionController::class, 'plans'])->name('shopify.plans');
// THIS IS JUST TETSING WILL BE REMOVED IN FUTURE
Route::POST('plans/subscribe', [SubscriptionController::class, 'subscribeToPlan'])->name('plans.subscribe');
Route::get('connect', [AmazonConnect::class, 'connect'])->name('amazon.connect');
Route::get(
    'amzon/authorize/shopify/{ens}',
    [AmazonConnect::class, 'authorizeAmazonIframe']
)->name('amazon.authorize.iframe');
Route::get('amazon/callback', [AmazonConnect::class, 'handleCallback'])->name('amazon.callback');
//amazon routes
Route::prefix('amazon')
    ->withoutMiddleware([\App\Http\Middleware\CheckSubscription::class])
    ->group(function () {
        Route::get('authorize', [AmazonConnect::class, 'authorizeAmazon'])
            ->name('amazon.authorize');


        Route::get('sync-orders', [AmazonConnect::class, 'syncOrders'])
            ->name('amazon.sync');
        Route::get('disconnect', [AmazonConnect::class, 'disconnect'])->name('amazon.disconnect');
    });
//store settings
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
// Route::get('logs', [SettingsController::class, 'logs'])->name('shopify.logs');
Route::get('activate', [SettingsController::class, 'showForm'])->name('setup.form');
Route::post('activate', [SettingsController::class, 'store'])->name('setup.store');
//return refunds
// Route::get('return_refunds', [ReturnController::class, 'index'])->name('shopify.return');
// Route::get('returns/amazon', [ReturnController::class, 'amazon'])->name('shopify.returns.amazon');
// Route::get('returns/shopify', [ReturnController::class, 'shopify'])->name('shopify.returns.shopify');
// Route::get('returns/amazon/{id}', [ReturnController::class, 'viewAmazon'])->name('shopify.returns.view.amazon');
// Route::get('returns/shopify/{id}', [ReturnController::class, 'viewShopify'])->name('shopify.returns.view.shopify');
//inventory
// Route::get('/inventory', [InventoryController::class, 'index'])->name('shopify.inventory.index');
Route::get('inventory/shopify', [InventoryController::class, 'shopify'])->name('shopify.inventory.shopify');
Route::get('/inventory/amazon', [InventoryController::class, 'amazon'])
    ->name('shopify.inventory.amazon');
Route::get('inventory/refresh', [InventoryController::class, 'refresh'])->name('shopify.inventory.refresh');
Route::get('inventory/product/{id}', [InventoryController::class, 'productDetails'])->name('shopify.inventory.details');
Route::get('product/category', [InventoryController::class, 'getProductCategory'])->name('shopify.product.category');


Route::get(
    'inventory/amazon/{parentSku}/variants',
    [InventoryController::class, 'variants']
)->name('shopify.inventory.amazon.variants');

Route::post(
    'inventory/amazon/{childSku}/update-quantity',
    [InventoryController::class, 'updateAmazonQuantity']
)->name('shopify.inventory.amazon.update');

Route::get('/inventory/shopify-products', [InventoryMappingController::class, 'shopifyProducts'])
    ->name('inventory.shopify.products');

Route::get(
    '/inventory/shopify-product-variants/{product}',
    [InventoryMappingController::class, 'variants']
)->name('inventory.shopify.variants');

Route::post(
    '/inventory/save-product-mapping',
    [InventoryMappingController::class, 'saveProductMapping']
)->name('inventory.save.mapping');

Route::post(
    '/inventory/save-amazon-mapping',
    [InventoryMappingController::class, 'saveAmazonMapping']
)->name('inventory.save.amazon.mapping');

Route::delete('/inventory/unmap/{mapping}', [InventoryMappingController::class, 'unmap'])
    ->name('inventory.unmap');

Route::post(
    '/inventory/shopify/update',
    [InventoryMappingController::class, 'updateShopifyInventory']
)->name('inventory.shopify.update');



Route::get('/clear-cache-temp', function () {
    Artisan::call('optimize:clear');

    return response()->json([
        'success' => true,
        'message' => Artisan::output(),
    ]);
});

Route::post('/amazon/test-update/{sku}', function (Illuminate\Http\Request $request, $sku) {

    $shop = \App\Models\Shop::where(
        'shop',
        $request->query('shop')
    )->firstOrFail();

    return app(\App\Services\AmazonService::class)
        ->updateInventory(
            $shop,
            $sku,
            (int) $request->quantity
        );
});
Route::get('/amazon/cache-clear', function () {

    $activeShop = session('active_shop');

    $shop = is_numeric($activeShop)
        ? Shop::findOrFail($activeShop)
        : Shop::where('shop', $activeShop)->firstOrFail();

    $marketplaceId = 'ATVPDKIKX0DER';

    $cacheKey = "amazon_inventory_{$shop->id}_{$marketplaceId}";

    Cache::forget($cacheKey);

    return response()->json([
        'success' => true,
        'message' => 'Amazon inventory cache cleared.',
        'cache_key' => $cacheKey,
    ]);
});

// Stripe webhook for payment updates
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('stripe.webhook');
Route::get('payment/success', [SubscriptionController::class, 'success'])->name('payment.success');
Route::get('payment/cancel', [SubscriptionController::class, 'cancel'])->name('payment.cancel');
Route::get('/check-payment-status', [SubscriptionController::class, 'checkStatus'])
    ->name('payment.status');
//admin routes
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
            ->name('admin.main.dashboard');
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
        Route::post('/admin/shops/{shop}/cancel', [AdminController::class, 'cancel'])
            ->name('admin.shops.cancel');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('admin.plans.update');
        Route::get('/mailtemplates', [MailTemplateController::class, 'index'])->name('admin.mailtemplates');
        Route::get('/mailtemplates/create', [MailTemplateController::class, 'create'])->name('admin.mailtemplates.create');
        Route::post('/mailtemplates/create', [MailTemplateController::class, 'store'])->name('admin.mailtemplates.store');
        Route::get('/mailtemplates/{mailtemplate}/edit', [MailTemplateController::class, 'edit'])->name('admin.mailtemplates.edit');
        Route::put('/mailtemplates/{mailtemplate}', [MailTemplateController::class, 'update'])->name('admin.mailtemplates.update');
        Route::post('/mailtemplates/{mailtemplate}', [MailTemplateController::class, 'destroy'])->name('admin.mailtemplates.delete');

        Route::get('/cat_update/{category}', [ProductSchemaController::class, 'downloadScema'])->name('admin.downloadScema');
        Route::get('/categories_p', [ProductSchemaController::class, 'index'])->name('admin.categories');
        Route::get('/schema-create', [ProductSchemaController::class, 'create'])->name('admin.schema.create');
        Route::post('/schema-store', [ProductSchemaController::class, 'store'])->name('product-schemas.store');
    });
});

Route::match(['get', 'post'], '/selectCategory', [ProductSchemaController::class, 'addProductCategory'])->name('user.addProductCategory');
Route::get('/addproduct/{schemaId}', [ProductSchemaController::class, 'productcreate'])->name('admin.product.store');
Route::post('/addproduct', [ProductSchemaController::class, 'productstore'])->name('admin.product.store.post');
Route::get('/generatePayload/{product}', [ProductSchemaController::class, 'buildListingRequest'])->name('admin.product.generatePayload');
Route::get('/productEdit/{product}', [ProductSchemaController::class, 'productEdit'])->name('admin.product.productEdit');
Route::get('/child/product/{product}', [ProductSchemaController::class, 'productEdit'])->name('admin.product.product.child');
Route::post('/addproduct/{product_id}', [ProductSchemaController::class, 'productstore'])->name('admin.product.edit.post');
Route::get('/generatePayload/{product}/{sku}', [ProductSchemaController::class, 'addChildListing'])->name('admin.product.generatePayload.child');
Route::get('/showProducts', [ProductSchemaController::class, 'showProducts'])->name('user.product.showProducts');
Route::get('/remove_drafts/{product}', [ProductSchemaController::class, 'removeDrafts'])->name('user.product.removeDrafts');



Route::get(
    '/amazon-schema',
    [AmazonSchemaController::class, 'index']
);
Route::get(
    '/amazon/subcategories/{id}',
    [AmazonSchemaController::class, 'getSubcategories']
);
Route::post(
    '/amazon/validate-rules',
    [AmazonSchemaController::class, 'validateRules']
);
// // amazon smart from 
// Route::get('/amazon-smart-form', [AmazonSmartFormController::class, 'index']);
// Route::post('/amazon-smart-form/fetch', [AmazonSmartFormController::class, 'fetch'])
//     ->name('amazon.smart.fetch');
// // test route 
Route::get('/test', [TestController::class, 'test'])->name('test');
Route::get('/getAllProductTypes', [TestController::class, 'getAllProductTypes'])->name('getAllProductTypes');
Route::get('/test-category-map', [TestController::class, 'testCategoryMapping']);
Route::get('/amazon-schema-test', function () {
    $shop = \App\Models\Shop::find(6);
    return (new \App\Services\AmazonService())
        ->getProductTypeDefinition($shop);
});
Route::get('/amazon-schema-test-2', function () {
    $shop = \App\Models\Shop::find(2);
    $productType = "SHIRT";
    return (new \App\Services\AmazonSchemaService())
        ->getProductTypeDefinition($shop, $productType);
});
Route::get('/amazon-check/{sku}', function (Request $request, $sku) {

    $shop = Shop::where('shop', $request->query('shop'))->first();

    if (!$shop) {
        return response()->json([
            'success' => false,
            'message' => 'Shop not found'
        ], 404);
    }

    $service = app(AmazonService::class);

    return response()->json(
        $service->checkAmazonListing($shop, $sku)
    );
});

Route::get(
    '/amazon/schema-fields/{slug}',
    [AmazonSchemaController::class, 'getFields']
);
Route::get(
    '/amazon-test',
    [AmazonSchemaController::class, 'amazonTest']
)->name('amazon.test');
Route::post('/amazon/evaluate-conditions', [AmazonSchemaController::class, 'evaluateConditions'])
    ->name('amazon.evaluate.conditions');
Route::post(
    '/amazon/manual-sync',
    [AmazonSchemaController::class, 'manualSync']
)->name('amazon.manual.sync');
Route::post(
    '/amazon/generate-sync-payload',
    [
        AmazonSchemaController::class,
        'generateSyncPayload'
    ]
);
Route::get(
    '/amazon/search-schema/{keyword}',
    [ShopifyController::class, 'searchAmazonSchema']
);
Route::get('/keyboard-schema', [TestController::class, 'keyboardSchema']);

Route::post(
    '/amazon/load-missing-fields',
    [AmazonSchemaController::class, 'loadMissingFields']
);


Route::get('/inventory/amazon/test-report', function (
    \App\Services\AmazonInventoryReportService $service
) {

    $activeShop = session('active_shop');

    $shop = is_numeric($activeShop)
        ? \App\Models\Shop::findOrFail($activeShop)
        : \App\Models\Shop::where('shop', $activeShop)->firstOrFail();

    return response()->json(
        $service->createReport(
            $shop,
            'ATVPDKIKX0DER'
        )
    );
});

Route::get('/inventory/amazon/test-report/{reportId}', function (
    $reportId,
    \App\Services\AmazonInventoryReportService $service
) {
    $activeShop = session('active_shop');

    $shop = is_numeric($activeShop)
        ? \App\Models\Shop::findOrFail($activeShop)
        : \App\Models\Shop::where('shop', $activeShop)->firstOrFail();

    return response()->json(
        $service->getReport(
            $shop,
            $reportId
        )
    );
});

Route::get('/inventory/amazon/test-download', function (
    Illuminate\Http\Request $request,
    \App\Services\AmazonInventoryReportService $service
) {
    $documentId = $request->get('documentId');

    $activeShop = session('active_shop');

    $shop = is_numeric($activeShop)
        ? \App\Models\Shop::findOrFail($activeShop)
        : \App\Models\Shop::where('shop', $activeShop)->firstOrFail();

    $download = $service->downloadReport($shop, $documentId);

    return response()->json([
        'compression' => $download['compression'],
        'size' => strlen($download['content']),
    ]);
});

Route::get('/inventory/amazon/test-extract', function (
    Illuminate\Http\Request $request,
    \App\Services\AmazonInventoryReportService $service
) {

    $documentId = $request->get('documentId');

    $activeShop = session('active_shop');

    $shop = is_numeric($activeShop)
        ? \App\Models\Shop::findOrFail($activeShop)
        : \App\Models\Shop::where('shop', $activeShop)->firstOrFail();

    $download = $service->downloadReport(
        $shop,
        $documentId
    );

    $reflection = new ReflectionClass($service);

    $method = $reflection->getMethod('extractReport');

    $method->setAccessible(true);

    $content = $method->invoke(
        $service,
        $download['content']
    );

    return response($content)
        ->header('Content-Type', 'text/plain');
});

Route::get('/inventory/amazon/test-parser', function (
    Illuminate\Http\Request $request,
    \App\Services\AmazonInventoryReportService $service
) {

    $activeShop = session('active_shop');

    $shop = is_numeric($activeShop)
        ? \App\Models\Shop::findOrFail($activeShop)
        : \App\Models\Shop::where('shop', $activeShop)->firstOrFail();

    $download = $service->downloadReport(
        $shop,
        $request->documentId
    );

    $reflection = new ReflectionClass($service);

    $extract = $reflection->getMethod('extractReport');

    $extract->setAccessible(true);

    $content = $extract->invoke(
        $service,
        $download['content']
    );

    return response()->json(
        $service->parseReport($content)
    );
});

Route::get(
    '/inventory/amazon/progress',
    [InventoryController::class, 'amazonProgress']
)->name('inventory.amazon.progress');


Route::post(
    '/webhooks/amazon/orders',
    [AmazonWebhookController::class, 'handleOrderNotification']
)->name('amazon.webhooks.orders');

Route::get(
    'amazon/connect/progress',
    [AmazonConnect::class, 'progress']
)->name('amazon.connect.progress');

Route::get('/test/store-status', [TestController::class, 'checkStoreStatus']);

Route::get('/test/store-status-command', function () {
    Artisan::call('stores:check-status');

    return nl2br(Artisan::output());
});


Route::get('/test/subscription', function () {

    $shop = \App\Models\Shop::find(21);

    return app(\App\Services\SubscriptionCancellationService::class)
        ->getActiveSubscription($shop);
});

Route::get('/test/cancel-subscription', function () {

    $shop = \App\Models\Shop::findOrFail(21);

    return app(\App\Services\SubscriptionCancellationService::class)
        ->cancelAtPeriodEnd($shop);
});


Route::get('/test/queue-work', function () {

    \Log::info('QUEUE ROUTE HIT');

    Artisan::call('queue:work', [
        '--queue' => 'store-status',
        '--once' => true,
    ]);

    \Log::info('QUEUE ROUTE FINISHED');

    return 'Done';
});

// Route::get('/test-amazon-sync', function () {

//     $shops = Shop::where('is_active', 1)
//         ->whereNotNull('amazon_refresh_token')
//         ->get();

//     foreach ($shops as $shop) {
//         SyncAmazonInventoryJob::dispatch($shop->id);
//     }

//     return 'Amazon inventory sync jobs dispatched.';
// });

Route::get('/test-amazon-sync', function () {

    $shops = Shop::where('is_active', 1)
        ->whereNotNull('amazon_refresh_token')
        ->get();

    foreach ($shops as $shop) {
        app(App\Services\AmazonInventoryReportService::class)
            ->syncInventory($shop, $shop->amazon_marketplace_id);
    }

    return 'Done';
});


Route::get('/test-command', function () {
    Artisan::call('amazon:refresh-inventory-cache');

    return nl2br(Artisan::output());
});
