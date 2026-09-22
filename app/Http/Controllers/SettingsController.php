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

            $shop->update([  'selected_location_index' => $locationIndex ]);

            if ($oldIndex !== null) {
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$oldIndex}");
            }
            if ($locationIndex !== null) {
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$locationIndex}");
            }

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
        $logs = SyncLog::where('shop_id', $shop->id)->latest('id')->paginate();
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

        // Log::info('SHOPIFY_DEBUG: activation_form_rendered', [
        //     'event' => 'activation_form_rendered',
        //     'request_shop' => $request->query('shop') ?? $request->input('shop'),
        //     'resolved_shop_id' => $shopModel?->id,
        //     'resolved_shop_domain' => $shopModel?->shop,
        //     'session_active_shop' => session('active_shop'),
        //     'session_verified_shop' => session('_shopify_verified_shop'),
        // ]);

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

        try {
            if ( $request->filled('shop') && $request->filled('shop_url') &&
                strtolower(trim((string) $request->query('shop'))) !==
                    strtolower(trim((string) $request->input('shop_url')))
            ) {
                // Shop mismatch hone par bhi existing flow continue rahega
            }

            $validated = $request->validate([
                            'shop_url' => 'nullable|string',
                            'shop_name' => 'required',
                            'email' => 'required|email',
                        ]);
            if (empty($validated['shop_url'])) {
                $validated['shop_url'] = strtolower(trim((string) $request->query('shop') ?? ''));
            }

            $shopUrl = strtolower(trim((string) $validated['shop_url']));
            $shop = Shop::whereRaw('LOWER(shop) = ?', [$shopUrl])->first();

            if (!$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found.',
                ], 404);
            }

            $shop->update([ 'shop_name' => $validated['shop_name'],
                'email' => $validated['email'], 'is_active' => 1,
            ]);

            $shop->fresh();

            session([
                'active_shop' => $shop->shop,
                'active_shop_id' => $shop->id,
                '_shopify_verified_shop' => $shop->shop,
            ]);

            try {
                $template = MailTemplate::active()
                    ->where('slug', 'welcome-email')->first();

                if ($template) {
                    app(\App\Services\EmailService::class)
                        ->sendDynamicEmail($template, (object) [
                            'name' => $shop->shop,
                            'email' => $validated['email'],
                        ]);
                }
            } catch (\Throwable $e) {
                // Email failure should not block activation
            }

            $dashboardUrl = route('dashboard', [ 'shop' => $shop->shop ]);

            $pollUrl = route('setup.activation.status', ['shop' => $shop->shop ]);

            return response()->json([
                'success' => true,
                'shop' => $shop->shop,
                'poll_url' => $pollUrl,
                'redirect_url' => $dashboardUrl,
                'message' => 'Store activated successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Log::error('SETUP_STORE: Exception during activation', [
            //     'request_id' => $requestId,
            //     'error' => $e->getMessage(),
            // ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Request ID: ' . $requestId,
            ], 500);
        }
    }

    public function activationStatus(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json([
                'message' => 'Invalid request.',
            ], 404);
        }
        $requestId = (string) \Illuminate\Support\Str::uuid();
        $shopParam = strtolower(trim((string) ($request->query('shop') ?? $request->input('shop', ''))));

        $shop = null;
        if ($shopParam !== '') {
            $shop = Shop::whereRaw('LOWER(shop) = ?', [$shopParam])->first();
        }

        if (!$shop) {
            $shopModel = $this->getActiveShop($request);
            if ($shopModel) {
                $shop = $shopModel;
            }
        }

        if (!$shop) {

            return response()->json([
                'activated' => false, 'shop' => $shopParam, 'shop_name' => null,
                'email' => null, 'message' => 'Shop not found.',
            ], 403);
        }

        $shopName = trim((string) $shop->shop_name);
        $email = trim((string) $shop->email);
        $isActivated = ($shopName !== '' && $email !== '' && (int) $shop->is_active === 1);

        return response()->json([
            'activated' => $isActivated,
            'shop' => $shop->shop,
            'shop_name' => $isActivated ? $shopName : null,
            'email' => $isActivated ? $email : null,
            'message' => $isActivated ? 'Store activated successfully.' : 'Store activation pending.',
        ]);
    }


    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('crm.entry');
    }
}
