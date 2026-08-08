<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopSubscription;
use App\Http\Controllers\ShopifyController;
use App\Models\Setting;
use App\Models\Log as SyncLog;
use App\Models\MailTemplate;
use App\Models\ReturnItem;
use App\Models\UserNotificationSetting;
use Illuminate\Support\Facades\Auth;


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
        Log::info('Settings Update', [
            'query_shop'   => request()->query('shop'),
            'input_shop'   => request('shop'),
            'session_shop' => session('shop'),
            'active_shop'  => app()->bound('activeShop')
                ? app('activeShop')?->shop
                : null,
        ]);
        $shop->settings()->updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'auto_sync'         => $request->has('auto_sync'),
                'auto_sku_mapping'  => $request->has('auto_sku_mapping'),
                'ai_assist'         => $request->has('ai_assist'),
                'currency'          => $request->input('currency'),
                'tax_behavior'      => $request->input('tax_behavior'),
            ]
        );

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
        $logs = SyncLog::where('shop_id', $shop->id)->paginate();
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
        return view('setup.activate', compact('shopModel'));
    }

    // public function store(Request $request)
    // {
    //     $validated= $request->validate([
    //         'shop_url' => 'required',
    //         'shop_name' => 'required',
    //         'email' => 'required|email',
    //     ]);

    //     if($request->has('access_token')){
    //         $access_token = $request->access_token;
    //     }

    //     Shop::updateOrCreate(
    //         ['shop' => $request->shop_url],
    //         [
    //             'shop_name' => $request->shop_name,
    //             'email' => $request->email,
    //             'is_active' => 1,
    //             'access_token' => $access_token ?? 'pending',
    //         ]
    //     );

    //     return redirect()->route('shopify.dashboard')
    //         ->with('success', 'App activated successfully!');
    // }

    public function store(Request $request)
    {
        \Log::info('ACTIVATE STORE HIT', [
            'input' => $request->all(),
            'url' => $request->fullUrl()
        ]);

        // =========================
        // STEP 1: VALIDATION
        // =========================
        $request->validate([
            'shop_url' => 'required',
            'shop_name' => 'required',
            'email' => 'required|email',
        ]);

        // =========================
        // STEP 2: SHOP FETCH
        // =========================
        $shop = Shop::whereRaw('LOWER(shop) = ?', [
            strtolower($request->shop_url)
        ])->first();

   
        if (!$shop) {
            \Log::error('SHOP NOT FOUND');
            return back()->with('error', 'Shop not found');
        }
dd($shop);
        try {
            $updated = $shop->update([
                'shop_name' => $request->shop_name,
                'email' => $request->email,
                'is_active' => 1
            ]);

            \Log::info('UPDATE RESULT', [
                'updated' => $updated
            ]);
        } catch (\Exception $e) {
            \Log::error('UPDATE FAILED', [
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Update failed');
        }

        // =========================
        // STEP 5: AFTER UPDATE
        // =========================
        \Log::info('AFTER UPDATE', $shop->fresh()->toArray());

        // =========================
        // STEP 6: SESSION SET
        // =========================
        session(['active_shop' => $shop->shop]);

        // =========================
        // STEP 7: EMAIL
        // =========================
        try {
            $template = MailTemplate::active()
                ->where('slug', 'welcome-email')
                ->first();

            \Log::info('EMAIL TEMPLATE', [
                'found' => $template ? true : false
            ]);

            if ($template) {
                app(\App\Services\EmailService::class)
                    ->sendDynamicEmail($template, (object)[
                        'name' => $shop->shop,
                        'email' => $request->email
                    ]);

                \Log::info('EMAIL SENT SUCCESS');
            }
        } catch (\Exception $e) {
            \Log::error('EMAIL FAILED', [
                'error' => $e->getMessage()
            ]);
        }

        // =========================
        // STEP 8: REDIRECT
        // =========================
        \Log::info('REDIRECT TO DASHBOARD');

        return redirect()->route('dashboard', [
            'shop' => $shop->shop
        ])->with('success', 'App activated successfully!');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('crm.entry');
    }
}
