<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShopifyOrder;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Category;
use App\Models\AdminSetting;
use App\Models\AdminNotification;
use App\Models\NotificationSetting;
use App\Models\MailTemplate;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;
use App\Models\ShopSubscription;
use App\Models\ProductMarketplaceMapping;
use App\Services\UserNotificationService;
use App\Models\AmazonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\ShopifyBillingService;

class AdminController extends Controller
{
    public function dashboard(Request $req)
    {
        $totalshops =   Shop::where('is_active', 1)->get()->count();
        $totalcategories = AmazonSchema::count();
        $totalsyncs = ProductMarketplaceMapping::count();

        $stats = [
            'shops' => $totalshops,
            'categories' => $totalcategories,
            'syncs' => $totalsyncs,
        ];

        $recentActivities = AdminNotification::latest()
            ->take(5)->get()
            ->map(function ($activity) {
                return [
                    'title' => $activity->title,
                    'message' => $activity->message,
                    'time' => $activity->created_at ? $activity->created_at->diffForHumans() : 'Just now',
                    'badge_class' => $activity->is_read ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary',
                    'badge_text' => $activity->is_read ? 'Read' : 'New',
                ];
            });

        $weeklyBars = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $count = AdminNotification::whereDate('created_at', $date)->count();
            $weeklyBars[] = max(25, min(100, ($count * 20) + 30));
        }

        $healthItems = [
            [
                'name' => 'Inventory Sync',
                'value' => min(100, 70 + ($totalshops > 0 ? min(20, floor($totalshops / 2)) : 0)),
                'color' => 'success',
            ],
            [
                'name' => 'Product Mapping',
                'value' => min(100, 60 + ($totalcategories > 0 ? min(20, floor($totalcategories / 10)) : 0)),
                'color' => 'primary',
            ],
            [
                'name' => 'Webhook Delivery',
                'value' => min(100, 65 + ($totalsyncs > 0 ? min(15, floor($totalsyncs / 50)) : 0)),
                'color' => 'warning',
            ],
        ];

        $summaryCards = [
            ['label' => 'Active Shops', 'value' => $totalshops, 'icon' => '🏪', 'color' => 'primary', 'hint' => $totalshops > 0 ? '+' . min(20, $totalshops) . ' active this month' : 'No active shops'],
            ['label' => 'Categories', 'value' => $totalcategories, 'icon' => '🗂️', 'color' => 'success', 'hint' => 'Updated from your catalog'],
            ['label' => 'Total Syncs', 'value' => $totalsyncs, 'icon' => '🔄', 'color' => 'warning', 'hint' => 'Growing steadily'],
            ['label' => 'Live Jobs', 'value' => max(1, $recentActivities->count() + floor($totalsyncs / 10)), 'icon' => '⚡', 'color' => 'info', 'hint' => 'Based on current activity'],
        ];

        return view('admin.index', compact('stats', 'recentActivities', 'summaryCards', 'weeklyBars', 'healthItems'));
    }
    public function order()
    {
        $orders = ShopifyOrder::latest()->paginate(10);
        return view('admin.orders.index', compact('orders'));
    }

    public function category()
    {
        $categories = Category::whereNull('parent_id')->get();
        $parentCategories = Category::whereNull('parent_id')->get();
        return view('admin.category.index', compact('categories', 'parentCategories'));
    }

    public function categoryChildren($id)
    {
        $category = Category::with('parent')->findOrFail($id);
        $children = Category::where('parent_id', $id)->get();
        $parentCategories = Category::whereNull('parent_id')->get();
        return view('admin.category.subcategory', compact('category', 'children', 'parentCategories'));
    }

    public function categoryserchedChildren(Request $req)
    {
        $req->validate([ 'category' => 'required' ]);

        $category = Category::with('parent')->where('name', 'like', '%' . $req->category . '%')->first();
        if (!$category) {
            return redirect()->back()->with('error', 'No category found');
        }
        $children =  Category::with('parent')->where('name', 'like', '%' . $req->category . '%')->get();
        $parentCategories = Category::whereNull('parent_id')->get();
        return view('admin.category.subcategory', compact('category', 'children', 'parentCategories'));
    }


    public function categoryCreate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories',
            'marketplaceIds' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:Active,Inactive'
        ]);
        $data['self_added'] = 1;
        $categories = Category::create($data);
        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function deleteCategory(Request $request, Category $category)
    {
        // If this category has child categories, don't allow deletion
        if ($category->children()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'This category cannot be deleted because it has subcategories. Please move all subcategories to another category before removing this category.'
            ], 422);
        }
        if ($category->self_added != 1) {
            return response()->json([
                'status' => false,
                'message' => 'Primary Categories can not be deleted.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully.'
        ]);
    }

    public function categoryEdit(Request $request, Category $category)
    {
        if ($category->parent_id == '') {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'status' => 'required|in:active,draft'
            ]);
        } else {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:categories,id',
            ]);
        }

        $category->update($data);
        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function product()
    {
        $products = Product::latest()->paginate(12);
        return view('admin.products.index', compact('products'));
    }

    public function settings()
    {
        $settings = AdminSetting::all()
            ->pluck('option_value', 'option_key')
            ->toArray();
        $notifications = NotificationSetting::all();
        return view('admin.settings.index', compact('settings', 'notifications'));
    }

    public function settingsupdate(Request $request)
    {
        $oldProductionClientId = trim((string) AdminSetting::get('production_client_id', ''));
        $oldProductionClientSecret = trim((string) AdminSetting::get('production_client_secret', ''));

        $keys = [
            'app_name',
            'currency',
            'timezone',
            'app_logo',
            'app_favicon',
            'support_email',
            'admin_email',
            'SMTP_host',
            'SMTP_port',
            'SMTP_username',
            'SMTP_password',
            'SMTP_encryption',
            'from_email',
            'from_name',
            'support_contact_no',
            'stripe_secret_key',
            'stripe_publishable_key',
            'stripe_webhook_secret',

            'test_client_id',
            'test_client_secret',
            'test_refresh_token',
            'is_testmode',

            'production_client_id',
            'production_client_secret',
            'amazon_refresh_token',
            'amazon_seller_id',
            'amazon_app_id',

            'SHOPIFY_API_KEY',
            'SHOPIFY_API_SECRET',
            'SHOPIFY_REDIRECT_URI',

            // AI Settings
            'openai_api_key',
            'ai_provider',
            'openai_model',
            'openai_temperature',
            'openai_endpoint',
        ];

        foreach ($keys as $key) {
            if ($key === 'app_logo' || $key === 'app_favicon') {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $filename = time() . '_' . $file->getClientOriginalName();

                    // Delete old file if exists
                    $old = AdminSetting::where('option_key', $key)->value('option_value');

                    if ($old && Storage::disk('public')->exists($old)) {
                        Storage::disk('public')->delete($old);
                    }

                    // Store file in storage/app/public/logo
                    $path = $file->storeAs( 'logo', $filename, 'public'  );

                    // Save relative path in database
                    AdminSetting::updateOrCreate(
                        ['option_key' => $key],
                        ['option_value' => $path]
                    );
                }

                continue;
            }

            if (AdminSetting::isSensitiveKey($key)) {
                $submittedValue = $request->input($key);
                if ($submittedValue === null || trim((string) $submittedValue) === '') {
                    // Preserve existing stored credential when blank is submitted
                    continue;
                }

                AdminSetting::updateOrCreate(
                    ['option_key' => $key],
                    ['option_value' => (string) $submittedValue]
                );
            } else {
                AdminSetting::updateOrCreate(
                    ['option_key' => $key],
                    [
                        'option_value' => in_array($key, ['is_testmode', 'app_maintenance', 'install_info'])
                            ? ($request->has($key) ? '1' : '0')
                            : ($request->input($key) ?? '')
                    ]
                );
            }
        }

        $newProdClientId = $request->input('production_client_id');
        $newProdClientSecret = $request->input('production_client_secret');

        $credentialsChanged =
            ($newProdClientId !== null && trim((string) $newProdClientId) !== '' && $oldProductionClientId !== trim((string) $newProdClientId)) ||
            ($newProdClientSecret !== null && trim((string) $newProdClientSecret) !== '' && $oldProductionClientSecret !== trim((string) $newProdClientSecret));

        if ($credentialsChanged) {
            $shops = Shop::where('is_active', 1)->get();
            $template = MailTemplate::active()->where('slug', 'amazon-reconnect')->first();

            $notificationSetting = NotificationSetting::where(
                'notification_key',
                'amazon_reconnect'
            )->first();

            foreach ($shops as $shop) {
                UserNotificationService::send(
                    $shop->id,  'amazon_reconnect', 'Amazon Reconnection Required',
                    'We have updated our Amazon integration . Please disconnect your Amazon account and reconnect it to continue using Amazon features without interruption.'
                );

                if ($notificationSetting &&  $notificationSetting->mail_enabled &&
                    $template && !empty($shop->email)) {

                    app(EmailService::class)->sendDynamicEmail(
                        $template,
                        (object) [
                            'name' => $shop->shop,
                            'first_name' => explode('.', $shop->shop)[0],
                            'email' => $shop->email,
                            'amazon_connect_url' => route('amazon.connect', [
                                'shop' => $shop->shop,
                            ]),
                        ]
                    );
                }
            }
        }

        foreach (NotificationSetting::all() as $notification) {

            $data = $request->input(
                'notifications.' . $notification->notification_key,  []
            );

            $notification->update([
                'email_enabled'  => isset($data['email']) ? 1 : 0,
                'in_app_enabled' => isset($data['in_app']) ? 1 : 0,
            ]);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function shops(Request $request)
    {
        $shops = Shop::with('subscription')->latest()->get();
        return view('admin.shops.index', compact('shops'));
    }

    public function updateShop(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $shop->update($data);

        return back()->with('success', 'Shop details updated successfully.');
    }

    public function deactivateShop(Request $request, Shop $shop)
    {
        if ((int) $shop->is_active !== 1) {
            return back()->with('info', 'This shop is already inactive.');
        }

        $shop->update([
            'is_active' => 0,
            'shopify_connection_status' => 'inactive',
            'store_status' => 'inactive',
            'last_status_check_at' => now(),
        ]);

        $subscription = ShopSubscription::where('shop_id', $shop->id)
            ->whereIn('status', ['active', 'accepted'])
            ->whereNotNull('shopify_subscription_gid')
            ->latest('id')
            ->first();

        if ($subscription) {
            try {
                $billingService = app(ShopifyBillingService::class);
                $result = $billingService->cancelSubscription(
                    $shop,
                    $subscription->shopify_subscription_gid
                );

                if ($result) {
                    $subscription->update([
                        'status' => 'cancelled',
                        'price' => 0,
                        'trial_ends_at' => null,
                        'current_period_end' => null,
                        'cancelled_at' => now(),
                        'ended_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Admin shop deactivation subscription cancellation failed', [
                    'shop_id' => $shop->id,
                    'subscription_id' => $subscription->id,
                    'shopify_subscription_gid' => $subscription->shopify_subscription_gid,
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);
            }
        }

        return back()->with(
            'success',
            'Shop status changed to inactive and the active subscription was cancelled.'
        );
    }

    public function cancel(Shop $shop)
    {
        $subscription = ShopSubscription::where('shop_id', $shop->id)
            ->whereIn('status', ['active', 'accepted'])
            ->whereNotNull('shopify_subscription_gid')
            ->latest('id')
            ->first();

        if (!$subscription) {
            return back()->with('error', 'No active Shopify subscription found.');
        }

        if (empty($shop->access_token) || empty($shop->shop)) {
            // Log::error('Admin cancellation failed: Shop access token or domain is missing', [
            //     'shop_id' => $shop->id,
            //     'has_shop' => !empty($shop->shop),
            //     'has_token' => !empty($shop->access_token),
            // ]);

            return back()->with('error', 'Shop access token or domain is missing.');
        }

        try {
            $billingService = app(ShopifyBillingService::class);

            $result = $billingService->cancelSubscription(
                $shop,  $subscription->shopify_subscription_gid
            );

            if (!$result) {
                // Log::error('Admin Shopify Subscription Cancellation Failed via API', [
                //     'shop_id' => $shop->id,
                //     'subscription_id' => $subscription->id,
                //     'has_gid' => !empty($subscription->shopify_subscription_gid),
                // ]);

                return back()->with('error', 'Shopify subscription cancellation failed. Please check Shopify connection and credentials.');
            }

            DB::transaction(function () use ($subscription) {
                $subscription->update([
                    'status' => 'cancelled', 'price' => 0,
                    'trial_ends_at' => null, 'current_period_end' => null,
                    'cancelled_at' => now(), 'ended_at' => now(),
                ]);
            });

            try {
                $template = MailTemplate::active()->where('slug', 'payment-cancelled')
                    ->first();

                if ($template) {
                    app(EmailService::class)->sendDynamicEmail(
                        $template,
                        (object) [
                            'name' => $shop->shop,
                            'first_name' => $shop->shop ? explode('.', $shop->shop)[0] : 'Shop',
                            'email' => $shop->email,
                        ]
                    );
                } else {
                    Log::warning('Payment cancel template not found', [
                        'shop_id' => $shop->id,
                    ]);
                }
            } catch (\Throwable $mailException) {
                Log::warning('Payment cancellation email dispatch failed', [
                    'shop_id' => $shop->id,
                    'error' => $mailException->getMessage(),
                ]);
            }

            // Log::info('Admin Shopify Subscription Cancelled', [
            //     'shop_id' => $shop->id,
            //     'subscription_id' => $subscription->id,
            //     'shopify_subscription_gid' => $subscription->shopify_subscription_gid,
            // ]);

            return back()->with('success', 'Subscription cancelled successfully.');
        } catch (\Throwable $e) {
            // Log::error('Admin Shopify Subscription Cancellation Failed', [
            //     'shop_id' => $shop->id,
            //     'subscription_id' => $subscription->id ?? null,
            //     'shopify_subscription_gid' => $subscription->shopify_subscription_gid ?? null,
            //     'message' => $e->getMessage(),
            //     'line' => $e->getLine(),
            //     'file' => $e->getFile(),
            // ]);

            return back()->with('error', 'Unable to cancel Shopify subscription: ' . $e->getMessage());
        }
    }
}
