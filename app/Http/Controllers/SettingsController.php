<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ShopifyController;
use App\Models\Log as SyncLog;
use App\Models\Plan;
use App\Models\ReturnItem;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Models\UserNotificationSetting;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SettingsController extends ShopifyController
{
    public function refreshLocations(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        if (!$shopModel) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.',
            ], 401);
        }

        $shop = Shop::where('shop', $shopModel->shop)->first();
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found.',
            ], 404);
        }

        $oldLocations = $shop->shopify_locations ?? [];
        if (!is_array($oldLocations)) {
            $oldLocations = json_decode($oldLocations, true) ?? [];
        }

        $oldSelectedIndex = $shop->selected_location_index;
        $oldSelectedLocationId = null;
        if ($oldSelectedIndex !== null && isset($oldLocations[$oldSelectedIndex])) {
            $oldSelectedLocationId = $oldLocations[$oldSelectedIndex]['id'] ?? null;
        }

        try {
            $shopifyService = new ShopifyService($shop->shop, $shop->access_token);
            $locationResponse = $shopifyService->getLocations($shop);

            if (!empty($locationResponse['error'])) {
                // Log::error('Shopify locations refresh API returned error', [
                //     'shop_id' => $shop->id,
                //     'shop' => $shop->shop,
                //     'locations_count' => 0,
                //     'error' => $locationResponse['message'] ?? 'Unknown Shopify API error',
                //     'success' => false,
                // ]);

                return response()->json([
                    'success' => false,
                    'message' => $locationResponse['message'] ?? 'Failed to fetch latest Shopify locations.',
                ], 500);
            }

            $newLocations = $locationResponse['locations'] ?? [];

            if (empty($newLocations)) {
                // Log::warning('Shopify locations refresh returned 0 locations', [
                //     'shop_id' => $shop->id,
                //     'shop' => $shop->shop,
                //     'locations_count' => 0,
                //     'success' => false,
                // ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No active Shopify locations were returned by Shopify.',
                ], 422);
            }

            // Preserve currently selected location if it still exists in the new locations
            $newSelectedIndex = null;
            if ($oldSelectedLocationId !== null) {
                foreach ($newLocations as $idx => $loc) {
                    $locId = $loc['id'] ?? null;
                    if ((string) $locId === (string) $oldSelectedLocationId || (basename((string) $locId) !== '' && basename((string) $locId) === basename((string) $oldSelectedLocationId))) {
                        $newSelectedIndex = $idx;
                        break;
                    }
                }
            }

            // If previously selected location was not found (e.g. deleted on Shopify), fallback safely to index 0
            if ($newSelectedIndex === null) {
                $newSelectedIndex = !empty($newLocations) ? 0 : null;
            }

            $shop->update([
                'shopify_locations' => $newLocations,
                'selected_location_index' => $newSelectedIndex,
            ]);

            // Clear cache if index changed
            if ($oldSelectedIndex !== null && $oldSelectedIndex !== $newSelectedIndex) {
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$oldSelectedIndex}");
            }
            if ($newSelectedIndex !== null) {
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$newSelectedIndex}");
            }

            // Log::info('Shopify locations refreshed successfully', [
            //     'shop_id' => $shop->id,
            //     'shop' => $shop->shop,
            //     'locations_count' => count($newLocations),
            //     'selected_location_index' => $newSelectedIndex,
            //     'success' => true,
            // ]);

            return response()->json([
                'success' => true,
                'message' => 'Shopify locations updated successfully.',
                'locations' => $newLocations,
                'selected_location_index' => $newSelectedIndex,
            ]);
        } catch (\Throwable $e) {
            // Log::error('Shopify locations refresh failed with exception', [
            //     'shop_id' => $shop->id,
            //     'shop' => $shop->shop,
            //     'error' => $e->getMessage(),
            //     'success' => false,
            // ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch latest Shopify locations: ' . $e->getMessage(),
            ], 500);
        }
    }

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

            $shop->update(['selected_location_index' => $locationIndex]);

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

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('crm.entry');
    }
}
