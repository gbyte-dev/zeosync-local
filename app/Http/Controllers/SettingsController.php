<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ShopifyController;
use App\Models\Log as SyncLog;
use App\Models\MailTemplate;
use App\Models\Plan;
use App\Models\ReturnItem;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Models\UserNotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SettingsController extends ShopifyController
{
    public function index(Request $request)
    {
        $notifications = UserNotificationSetting::all();
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        session(['amazon_shop' => $activeShop]);
        $shop = Shop::where('shop', $activeShop)->first();
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }
        $settings = Setting::where('shop_id', $shop->id)->first();
        return view('settings', compact('activeShop', 'shop', 'settings', 'notifications'));
    }

    public function update(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;

        $shop = Shop::where('shop', $activeShop)->first();

        if (!$shop) {
            return back()->with('error', 'Shop not found.');
        }

        $locations = $shop->shopify_locations ?? [];

        $request->validate([
            'selected_location_index' => [
                'nullable',
                function ($attribute, $value, $fail) use ($locations) {
                    if ($value !== null && $value !== '') {
                        if (!is_numeric($value) || !array_key_exists((int) $value, $locations)) {
                            $fail('Invalid Shopify location selected.');
                        }
                    }
                },
            ],
        ]);

        Log::info('Settings Update', [
            'query_shop' => request()->query('shop'),
            'input_shop' => request('shop'),
            'session_shop' => session('shop'),
            'active_shop' => app()->bound('activeShop')
                ? app('activeShop')?->shop
                : null,
        ]);

        if ($request->hasAny(['currency', 'tax_behavior', 'auto_sync', 'auto_sku_mapping', 'ai_assist'])) {
            $shop->settings()->updateOrCreate(
                ['shop_id' => $shop->id],
                [
                    'auto_sync' => $request->has('auto_sync'),
                    'auto_sku_mapping' => $request->has('auto_sku_mapping'),
                    'ai_assist' => $request->has('ai_assist'),
                    'currency' => $request->input('currency'),
                    'tax_behavior' => $request->input('tax_behavior'),
                ]
            );
        }

        if ($request->has('selected_location_index')) {
            $oldIndex = $shop->selected_location_index;
            $newIndex = $request->input('selected_location_index');
            $locationIndex = ($newIndex !== '' && $newIndex !== null) ? (int) $newIndex : null;

            $shop->update([
                'selected_location_index' => $locationIndex,
            ]);

            if ($oldIndex !== null) {
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$oldIndex}");
            }
            if ($locationIndex !== null) {
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$locationIndex}");
            }

            Log::info('SHOPIFY LOCATION SELECTED', [
                'shop_id' => $shop->id,
                'index' => $locationIndex,
                'location' => $locationIndex !== null
                    ? ($locations[$locationIndex] ?? null)
                    : null,
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Shopify location updated.',
                'selected_location_index' => $shop->selected_location_index,
            ]);
        }

        return back()->with('success', 'Settings updated.');
    }

    public function logs(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        session(['amazon_shop' => $activeShop]);
        $shop = Shop::where('shop', $activeShop)->first();
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }
        $logs = SyncLog::where('shop_id', $shop->id)
            ->latest('id')
            ->paginate();
        return view('logs', compact('activeShop', 'shop', 'logs'));
    }

    public function removeAllLogs(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        session(['amazon_shop' => $activeShop]);
        $shop = Shop::where('shop', $activeShop)->first();
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }
        SyncLog::where('shop_id', $shop->id)->delete();
        return back()->with('success', 'All logs removed.');
    }

    public function removeLog(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        session(['amazon_shop' => $activeShop]);
        $shop = Shop::where('shop', $activeShop)->first();
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }
        $log = SyncLog::where('shop_id', $shop->id)->findOrFail($id);
        $log->delete();
        return back()->with('success', 'Log removed.');
    }

    public function showForm(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;

        Log::info('SHOPIFY_DEBUG: activation_form_rendered', [
            'event' => 'activation_form_rendered',
            'request_shop' => $request->query('shop') ?? $request->input('shop'),
            'resolved_shop_id' => $shopModel?->id,
            'resolved_shop_domain' => $shopModel?->shop,
            'session_active_shop' => session('active_shop'),
            'session_verified_shop' => session('_shopify_verified_shop'),
        ]);

        if ($request->filled('shop') && $shopModel && strtolower(trim((string) $request->query('shop'))) !== strtolower(trim((string) $shopModel->shop))) {
            Log::warning('SHOPIFY_DEBUG: activation_form_shop_mismatch', [
                'request_shop' => $request->query('shop'),
                'resolved_shop_domain' => $shopModel->shop,
                'resolved_shop_id' => $shopModel->id,
                'session_active_shop' => session('active_shop'),
            ]);
        }

        return view('setup.activate', compact('shopModel'));
    }

    public function store(Request $request)
    {
        $requestId = (string) \Illuminate\Support\Str::uuid();

        Log::withContext([
            'request_id' => $requestId,
            'controller' => self::class,
            'method' => __FUNCTION__,
        ]);

        Log::info('SHOPIFY_DEBUG: activation_form_submitted', [
            'event' => 'activation_form_submitted',
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'request_shop' => $request->query('shop'),
            'input_shop_url' => $request->input('shop_url'),
            'input_shop_name' => $request->input('shop_name'),
            'input_email' => $request->input('email'),
            'session_active_shop' => session('active_shop'),
            'session_verified_shop' => session('_shopify_verified_shop'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            if (
                $request->filled('shop') &&
                $request->filled('shop_url') &&
                strtolower(trim((string) $request->query('shop'))) !==
                    strtolower(trim((string) $request->input('shop_url')))
            ) {
                Log::warning('SHOPIFY_DEBUG: activation_shop_mismatch', [
                    'request_shop' => $request->query('shop'),
                    'input_shop_url' => $request->input('shop_url'),
                    'session_active_shop' => session('active_shop'),
                ]);
            }

            Log::info('SHOPIFY_DEBUG: validation_started');

            $validated = $request->validate([
                'shop_url' => 'required',
                'shop_name' => 'required',
                'email' => 'required|email',
            ]);

            Log::info('SHOPIFY_DEBUG: validation_passed', [
                'validated_shop_url' => $validated['shop_url'],
                'validated_shop_name' => $validated['shop_name'],
                'validated_email' => $validated['email'],
            ]);

            $shopUrl = strtolower(trim((string) $request->input('shop_url')));

            Log::info('SHOPIFY_DEBUG: shop_lookup_started', [
                'search_shop_url' => $shopUrl,
            ]);

            $shop = Shop::whereRaw('LOWER(shop) = ?', [
                $shopUrl
            ])->first();

            Log::info('SHOPIFY_DEBUG: shop_lookup_completed', [
                'shop_found' => $shop !== null,
                'matched_shop_id' => $shop?->id,
                'matched_shop' => $shop?->shop,
                'matched_is_active' => $shop?->is_active,
            ]);

            if (!$shop) {
                Log::error('SHOPIFY_DEBUG: shop_not_found', [
                    'searched_shop_url' => $shopUrl,
                    'request_shop' => $request->query('shop'),
                    'input_shop_url' => $request->input('shop_url'),
                ]);

                return back()->with('error', 'Shop not found');
            }

            Log::info('SHOPIFY_DEBUG: database_update_started', [
                'shop_id' => $shop->id,
                'shop_domain' => $shop->shop,
                'old_shop_name' => $shop->shop_name,
                'old_email' => $shop->email,
                'old_is_active' => $shop->is_active,
                'new_shop_name' => $request->input('shop_name'),
                'new_email' => $request->input('email'),
                'new_is_active' => 1,
            ]);

            $updated = $shop->update([
                'shop_name' => $request->input('shop_name'),
                'email' => $request->input('email'),
                'is_active' => 1,
            ]);

            $freshShop = $shop->fresh();

            Log::info('SHOPIFY_DEBUG: database_update_completed', [
                'update_success' => $updated,
                'shop_id' => $freshShop?->id,
                'shop_domain' => $freshShop?->shop,
                'shop_name' => $freshShop?->shop_name,
                'email' => $freshShop?->email,
                'is_active' => $freshShop?->is_active,
            ]);

            session([
                'active_shop' => $shop->shop,
            ]);

            Log::info('SHOPIFY_DEBUG: session_updated', [
                'active_shop' => session('active_shop'),
                'verified_shop' => session('_shopify_verified_shop'),
                'database_shop' => $shop->shop,
            ]);

            try {
                Log::info('SHOPIFY_DEBUG: email_process_started', [
                    'shop_id' => $shop->id,
                    'shop' => $shop->shop,
                    'email' => $request->input('email'),
                ]);

                $template = MailTemplate::active()
                    ->where('slug', 'welcome-email')
                    ->first();

                Log::info('SHOPIFY_DEBUG: email_template_lookup_completed', [
                    'template_found' => $template !== null,
                    'template_id' => $template?->id,
                    'template_slug' => $template?->slug,
                ]);

                if ($template) {
                    app(\App\Services\EmailService::class)
                        ->sendDynamicEmail($template, (object) [
                            'name' => $shop->shop,
                            'email' => $request->input('email'),
                        ]);

                    Log::info('SHOPIFY_DEBUG: email_sent_successfully', [
                        'email' => $request->input('email'),
                    ]);
                } else {
                    Log::warning('SHOPIFY_DEBUG: email_template_not_found', [
                        'slug' => 'welcome-email',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('SHOPIFY_DEBUG: email_failed', [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            Log::info('SHOPIFY_DEBUG: redirect_to_dashboard', [
                'route' => 'dashboard',
                'shop' => $shop->shop,
            ]);

            return redirect()
                ->route('dashboard', [
                    'shop' => $shop->shop,
                ])
                ->with('success', 'App activated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('SHOPIFY_DEBUG: validation_failed', [
                'errors' => $e->errors(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            Log::critical('SHOPIFY_DEBUG: activation_store_failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId,
                'request_shop' => $request->query('shop'),
                'shop_url' => $request->input('shop_url'),
                'shop_name' => $request->input('shop_name'),
                'email' => $request->input('email'),
                'active_shop' => session('active_shop'),
            ]);

            return back()->with(
                'error',
                'Something went wrong. Request ID: ' . $requestId
            );
        }
    }

    // public function store(Request $request)
    // {
    //     Log::info('SHOPIFY_DEBUG: activation_form_submitted', [
    //         'event'                 => 'activation_form_submitted',
    //         'request_shop'          => $request->query('shop'),
    //         'input_shop_url'        => $request->input('shop_url'),
    //         'session_active_shop'   => session('active_shop'),
    //         'session_verified_shop' => session('_shopify_verified_shop'),
    //     ]);

    //     if ($request->filled('shop') && $request->filled('shop_url') && strtolower(trim((string) $request->query('shop'))) !== strtolower(trim((string) $request->input('shop_url')))) {
    //         Log::warning('SHOPIFY_DEBUG: activation_form_submitted_mismatch', [
    //             'request_shop'        => $request->query('shop'),
    //             'input_shop_url'      => $request->input('shop_url'),
    //             'session_active_shop' => session('active_shop'),
    //         ]);
    //     }

    //     $request->validate([
    //         'shop_url' => 'required',
    //         'shop_name' => 'required',
    //         'email' => 'required|email',
    //     ]);

    //     $shop = Shop::whereRaw('LOWER(shop) = ?', [
    //         strtolower($request->shop_url)
    //     ])->first();

    //     if (!$shop) {
    //         \Log::error('SHOP NOT FOUND');
    //         Log::warning('SHOPIFY_DEBUG: activation_shop_not_found_in_db', [
    //             'input_shop_url' => $request->shop_url,
    //         ]);
    //         return back()->with('error', 'Shop not found');
    //     }

    //     Log::info('SHOPIFY_DEBUG: activation_database_update', [
    //         'event'               => 'activation_database_update',
    //         'input_shop_url'      => $request->input('shop_url'),
    //         'matched_shop_id'     => $shop?->id,
    //         'matched_shop_domain' => $shop?->shop,
    //     ]);

    //     // =========================
    //     // STEP 4: UPDATE
    //     // =========================
    //     try {
    //         $updated = $shop->update([
    //             'shop_name' => $request->shop_name,
    //             'email' => $request->email,
    //             'is_active' => 1
    //         ]);

    //         Log::info('SHOPIFY_DEBUG: activation_database_update_completed', [
    //             'event'               => 'activation_database_update_completed',
    //             'updated_shop_id'     => $shop?->id,
    //             'updated_shop_domain' => $shop?->shop,
    //             'update_success'      => $updated,
    //         ]);

    //         \Log::info('UPDATE RESULT', [
    //             'updated' => $updated
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('UPDATE FAILED', [
    //             'error' => $e->getMessage()
    //         ]);
    //         return back()->with('error', 'Update failed');
    //     }

    //     session(['active_shop' => $shop->shop]);

    //     // =========================
    //     // STEP 7: EMAIL
    //     // =========================
    //     try {
    //         $template = MailTemplate::active()
    //             ->where('slug', 'welcome-email')
    //             ->first();

    //         \Log::info('EMAIL TEMPLATE', [
    //             'found' => $template ? true : false
    //         ]);

    //         if ($template) {
    //             app(\App\Services\EmailService::class)
    //                 ->sendDynamicEmail($template, (object)[
    //                     'name' => $shop->shop,
    //                     'email' => $request->email
    //                 ]);

    //             \Log::info('EMAIL SENT SUCCESS');
    //         }
    //     } catch (\Exception $e) {
    //         \Log::error('EMAIL FAILED', [
    //             'error' => $e->getMessage()
    //         ]);
    //     }

    //     // =========================
    //     // STEP 8: REDIRECT
    //     // =========================
    //     \Log::info('REDIRECT TO DASHBOARD');

    //     return redirect()->route('dashboard', [
    //         'shop' => $shop->shop
    //     ])->with('success', 'App activated successfully!');
    // }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('crm.entry');
    }
}
