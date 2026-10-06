<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetupController extends Controller
{
    /**
     * Resolve the shop strictly from verified credentials / session.
     */
    protected function getAuthenticatedShop(Request $request): ?Shop
    {
        // 1. Cryptographically verified model from VerifyShopifyAuthentication / ResolveActiveShop
        if ($request->attributes->has('shopify_verified_model')) {
            $shop = $request->attributes->get('shopify_verified_model');
            if ($shop instanceof Shop && $shop->is_active == 1) {
                return $shop;
            }
        }

        if ($request->attributes->has('shopify_verified_shop')) {
            $verifiedShop = $request->attributes->get('shopify_verified_shop');
            return Shop::where('shop', $verifiedShop)->where('is_active', 1)->first();
        }

        // 2. Cryptographically verified Laravel session established by prior Shopify authentication
        if (session()->has('_shopify_verified_shop') && session()->has('active_shop_id')) {
            return Shop::where('id', session('active_shop_id'))
                ->where('shop', session('_shopify_verified_shop'))
                ->where('is_active', 1)
                ->first();
        }

        return null;
    }

    /**
     * Display the email collection form.
     */
    public function form(Request $request)
    {
        $shop = $this->getAuthenticatedShop($request);

        if (!$shop) {
            return redirect()->route('crm.entry');
        }

        // If email is already present, proceed directly to dashboard
        if (filled($shop->shop_name) && filled($shop->email)) {
            $redirectParams = ['shop' => $shop->shop];
            if ($request->filled('host')) {
                $redirectParams['host'] = $request->query('host');
            }
            if ($request->filled('embedded')) {
                $redirectParams['embedded'] = $request->query('embedded');
            }

            return redirect()->route('dashboard', $redirectParams);
        }

        return view('setup', [
            'shop' => $shop,
            'shopName' => $shop->shop_name ?? $shop->shop,
            'host' => $request->query('host', $request->input('host')),
            'embedded' => $request->query('embedded', $request->input('embedded', '1')),
        ]);
    }

    /**
     * Validate and save the merchant-provided email.
     */
    public function store(Request $request)
    {
        $shop = $this->getAuthenticatedShop($request);

        if (!$shop) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            abort(403, 'Unauthorized shop action.');
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));

        DB::transaction(function () use ($shop, $normalizedEmail) {
            $shop->update([
                'email' => $normalizedEmail,
            ]);
        });

        // Log::info('SHOPIFY_MANUAL_EMAIL_SAVED', [
        //     'shop_id' => $shop->id,
        //     'shop' => $shop->shop,
        //     'email_present' => true,
        // ]);

        $redirectParams = ['shop' => $shop->shop];
        if ($request->filled('host')) {
            $redirectParams['host'] = $request->input('host');
        }
        if ($request->filled('embedded')) {
            $redirectParams['embedded'] = $request->input('embedded');
        }

        return redirect()->route('dashboard', $redirectParams)->with('success', 'Store details saved successfully.');
    }
}
