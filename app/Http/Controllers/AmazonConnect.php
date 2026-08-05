<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Store;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopSubscription;
use App\Services\NotificationService;
use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Crypt;
use App\Models\MailTemplate;
use App\Services\EmailService;
use App\Models\AdminSetting;

class AmazonConnect extends ShopifyController
{
    public function connect(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        session(['amazon_shop' => $activeShop]);
        $shop = Shop::where('shop', $activeShop)->first();
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }

        return view('amazonconnect.index', compact('activeShop', 'shop'));
    }

    public function authorizeAmazon(Request $request)
    {
        $config = json_decode($request->input('amazon_config'), true);
        $activeShop = $request->shop ?? session('active_shop');
        $shop = Shop::where('shop', $activeShop)->first();
        $settings = AdminSetting::where('option_key', 'amazon_app_id')->pluck('option_value', 'option_key');

        if (!$config || !$shop) {
            return back()->with('error', 'Please select a valid marketplace.');
        }

        $state = bin2hex(random_bytes(16));

        $shop->update([
            'amazon_oauth_state'    => $state,
            'amazon_marketplace_id' => $config['id'],
            'amazon_mws_region'     => $config['region'],
            'amazon_endpoint'       => $config['endpoint'],
        ]);

        session([
            'amazon_oauth_state'     => $state,
            'amazon_pending_id'      => $config['id'],
            'amazon_pending_region'  => $config['region'],
            'amazon_pending_endpoint' => $config['endpoint'],
        ]);

        $app_id = $settings['amazon_app_id'] ?? config('amazon.app_id');

        $authUrl = match ($config['region']) {
            'eu'    => 'https://sellercentral-europe.amazon.com',
            'fe'    => 'https://sellercentral.amazon.co.jp',
            default => 'https://sellercentral.amazon.com',
        };

        $query = http_build_query([
            'application_id' => $app_id,
            'state'          => $state,
            'version'        => 'beta',
            'redirect_uri'   => route('amazon.callback'),
        ]);

        session([
            'amazon_is_iframe' => $request->boolean('is_iframe')
        ]);

        if (isset($request->is_iframe) && $request->is_iframe == 1) {

            $encryptedShop = Crypt::encryptString($shop->shop);
            $requrl = url('amzon/authorize/shopify/' . $encryptedShop);
            $template = MailTemplate::where('slug', 'amazon-connect')->first();

            if ($template) {
                $shop->amazon_connect_url = $requrl;
                app(EmailService::class)->sendDynamicEmail(
                    $template,
                    $shop
                );

                cache()->put(
                    "amazon_connect_progress_{$shop->id}",
                    [
                        'percent' => 20,
                        'message' => 'Authorization email sent.',
                        'completed' => false,
                    ],
                    now()->addMinutes(10)
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Authorization email sent.'
            ]);
        }

        // Normal (non-iframe) flow
        return redirect()->away("{$authUrl}/apps/authorize/consent?{$query}");
    }

    public function authorizeAmazonIframe(Request $request, $ens)
    {
        $decryptedShop = Crypt::decryptString($ens);

        $activeShop = $decryptedShop;
        $shop = Shop::where('shop', $activeShop)->first();
        if (!$shop || !$shop->amazon_marketplace_id || !$shop->amazon_mws_region) {
            return back()->with('error', 'Please select a valid marketplace.');
        }
        $state = $shop->amazon_oauth_state ?? bin2hex(random_bytes(16));
        session([
            'amazon_oauth_state'        => $shop->amazon_oauth_state,
            'amazon_pending_id'         => $shop->amazon_marketplace_id,
            'amazon_pending_region'     => $shop->amazon_mws_region,
            'amazon_pending_endpoint'   => $shop->amazon_pending_endpoint,

            // ADD THIS
            'amazon_is_iframe'          => true,
        ]);

        $authUrl = match ($shop->amazon_mws_region) {
            'eu'    => 'https://sellercentral-europe.amazon.com',
            'fe'    => 'https://sellercentral.amazon.co.jp',
            default => 'https://sellercentral.amazon.com',
        };

        $settings = AdminSetting::where('option_key', 'amazon_app_id')->pluck('option_value', 'option_key');
        $app_id = $settings['amazon_app_id'] ?? config('amazon.app_id');

        $query = http_build_query([
            'application_id' => $app_id,
            'state'          => $state,
            'version'        => 'beta',
            'redirect_uri'   => route('amazon.callback'),
        ]);
        cache()->put(
            "amazon_connect_progress_{$shop->id}",
            [
                'percent' => 40,
                'message' => 'Amazon authorization page opened.',
                'completed' => false,
            ],
            now()->addMinutes(10)
        );

        return redirect("{$authUrl}/apps/authorize/consent?{$query}");
    }

    public function handleCallback(Request $request)
    {
        $session_auth = '';
        if (session('amazon_oauth_state')) {
            $session_auth = session('amazon_oauth_state');
            $shopdata = Shop::where('amazon_oauth_state', $session_auth)->first();
        } else {
            $shopdata = Shop::where('amazon_oauth_state', $request->state)->first();
            if ($shopdata) {
                $session_auth = $shopdata->amazon_oauth_state;
            }
        }

        if ($request->state !== $session_auth) {
            return redirect()->route('dashboard')->with('error', 'Invalid state.');
        }

        $settings = AdminSetting::pluck('option_value', 'option_key');
        $client_id = $settings['production_client_id'] ?? config('amazon.client_id');
        $client_secret = $settings['production_client_secret'] ?? config('amazon.client_secret');

        $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $request->spapi_oauth_code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $sessionshop = $shopdata->shop ?? session('amazon_shop');
            $shop = Shop::where('shop', $sessionshop)->first();

            cache()->put(
                "amazon_connect_progress_{$shop->id}",
                [
                    'percent' => 80,
                    'message' => 'Saving Amazon credentials...',
                    'completed' => false,
                ],
                now()->addMinutes(10)
            );

            $shop->update([
                'amazon_refresh_token'  => $data['refresh_token'],
                'amazon_seller_id'      => $request->selling_partner_id ?? ''
            ]);

            $request->session()->forget([
                'amazon_oauth_state',
                'amazon_pending_id',
                'amazon_pending_region',
                'amazon_pending_endpoint'
            ]);

            $shopName = str_replace('.myshopify.com', '', $shop->shop);
            NotificationService::send(
                'amazon_account_status',
                'Amazon Seller Connected',
                "{$shopName} connected Amazon seller account successfully."
            );

            cache()->put(
                "amazon_connect_progress_{$shop->id}",
                [
                    'percent' => 100,
                    'message' => 'Amazon connected successfully.',
                    'completed' => true,
                ],
                now()->addMinutes(10)
            );
            $isIframe = session('amazon_is_iframe', false);

            $request->session()->forget('amazon_is_iframe');

            if ($isIframe) {

                return redirect()->route('amazon.connect.success', [
                    'shop' => $shop->shop,
                ]);
            }

            return redirect()->route('amazon.connect.success', [
                'shop' => $shop->shop,
            ])->with('success', 'Amazon Connected!');

            // return redirect()->route('dashboard', [
            //         'shop' => $shop->shop,
            //     ])->with('success', 'Amazon Connected!');
        }

        return redirect()->route('dashboard')->with('error', 'Failed to connect Amazon.');
    }

    public function progress(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        if (!$shopModel) {
            return response()->json(['percent' => 0, 'message' => 'Shop not found.',  'completed' => false]);
        }

        return response()->json(
            cache()->get(
                "amazon_connect_progress_{$shopModel->id}",
                [
                    'percent' => 0,
                    'message' => 'Preparing...',
                    'completed' => false,
                ]
            )
        );
    }


    public function syncOrders($shop)
    {
        $settings = AdminSetting::pluck('option_value', 'option_key');
        $client_id = $settings['production_client_id'] ?? config('amazon.client_id');
        $client_secret = $settings['production_client_secret'] ?? config('amazon.client_secret');

        // 1. Get Access Token
        $auth = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $shop->amazon_refresh_token,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
        ])->json();

        $accessToken = $auth['access_token'];

        // 2. Call Orders API
        $response = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->get('https://sellingpartnerapi-na.amazon.com/orders/v0/orders', [
            'CreatedAfter'   => now()->subDays(1)->toIso8601String(),
            'MarketplaceIds' => ['ATVPDKIKX0DER'], // USA Marketplace ID
        ]);

        $orders = $response->json()['payload']['Orders'] ?? [];

        foreach ($orders as $amzOrder) {
            // Logic to insert into your local DB or push to Shopify via Admin API
            Log::info("Found Amazon Order: " . $amzOrder['AmazonOrderId']);
        }
    }

    public function disconnect(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel->shop;
        $shop = Shop::where('shop', $activeShop)->first();
        $shop->update([
            'amazon_refresh_token'  => null,
        ]);

        NotificationService::send(
            'amazon_account_status',
            'Amazon Seller Disconnected',
            $shop->shop . ' Amazon seller account disconnected successfully.'
        );

        return redirect()->back()->with('success', 'Removed Successfully');
    }

    public function success(Request $request)
    {
        return view('amazonconnect.success', [
            'shop' => $request->query('shop')
        ]);
    }
}
