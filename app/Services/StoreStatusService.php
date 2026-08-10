<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreStatusService
{
    public function check(Shop $shop): void
    {
        Log::info('STORE STATUS CHECK STARTED', [
            'shop_id' => $shop->id,
            'shop'    => $shop->shop,
        ]);

        $tokenResult = $this->ensureFreshAccessToken($shop);
        if (!$tokenResult['success']) {
            Log::warning('STORE STATUS CHECK TOKEN REFRESH FAILED', [
                'shop_id' => $shop->id,
                'shop'    => $shop->shop,
                'message' => $tokenResult['message'],
            ]);
            $this->updateStatus($shop, 'inactive');
            return;
        }

        $shopifyService = app(ShopifyService::class, [
            'shop'  => $shop->shop,
            'token' => $tokenResult['access_token'],
        ]);

        $query = <<<GRAPHQL
        {
            shop {
                id
                name
            }
        }
        GRAPHQL;

        $response = $shopifyService->graphql($query);

        // Handle API Errors, Network Exceptions, and Temporary Issues
        if (isset($response['error']) && $response['error'] === true) {
            $status = $response['status'] ?? 500;

            switch ($status) {
                case 401:
                    $this->updateStatus($shop, 'uninstalled');
                    break;

                case 403:
                    $this->ensureFreshAccessToken($shop);
                    break;

                case 402: // Payment Required (Shopify standard for frozen stores)
                case 423: // Locked
                case 404: // Store not found/deleted
                    $this->updateStatus($shop, 'inactive');
                    break;

                case 429: // Rate limit
                case 0:   // Network Exception
                default:  // 5xx and other unexpected errors
                    Log::warning('STORE STATUS CHECK TEMPORARY ISSUE', [
                        'shop_id'     => $shop->id,
                        'shop'        => $shop->shop,
                        'status_code' => $status,
                        'message'     => $response['message'] ?? 'Unknown error',
                    ]);
                    $this->updateTimestampOnly($shop);
                    break;
            }

            return;
        }

        // Verify successful HTTP 200 response contains valid Shopify store data
        if (isset($response['data']['shop']['id'])) {
            $this->updateStatus($shop, 'active');
        } else {
            // HTTP 200 but empty/unexpected structural response
            Log::warning('STORE STATUS CHECK UNEXPECTED RESPONSE', [
                'shop_id'  => $shop->id,
                'shop'     => $shop->shop,
                'response' => $response,
            ]);
            $this->updateTimestampOnly($shop);
        }

        Log::info('STORE STATUS CHECK COMPLETED', [
            'shop_id' => $shop->id,
            'shop'    => $shop->shop,
        ]);
    }

    public function updateStatus(Shop $shop, string $status): void
    {
        $shop->update([
            'store_status'         => $status,
            'is_active'            => $status === 'active' ? 1 : 0,
            'last_status_check_at' => now(),
        ]);

        // Store inactive / banned / uninstalled
        if (in_array($status, ['inactive', 'banned', 'uninstalled'])) {

            app(SubscriptionCancellationService::class)
                ->cancelAtPeriodEnd($shop);
        }

        Log::info('STORE STATUS UPDATED', [
            'shop_id'   => $shop->id,
            'shop'      => $shop->shop,
            'status'    => $status,
            'is_active' => $status === 'active' ? 1 : 0,
        ]);
    }

    protected function updateTimestampOnly(Shop $shop): void
    {
        $shop->update([
            'last_status_check_at' => now(),
        ]);

        Log::info('STORE STATUS TIMESTAMP UPDATED (NO STATUS CHANGE)', [
            'shop_id' => $shop->id,
            'shop'    => $shop->shop,
        ]);
    }

    /**
     * Ensures the shop has a valid access token, refreshing if needed.
     * Returns an array: ['success' => bool, 'access_token' => ?string, 'message' => string]
     */
    public function ensureFreshAccessToken(Shop $shopModel): array
    {
        try {
            // still valid — nothing to do
            if ($shopModel->access_token_expires_at && $shopModel->access_token_expires_at->isFuture()) {
                return [
                    'success' => true,
                    'access_token' => $shopModel->access_token,
                    'message' => 'Token still valid.',
                ];
            }

            // refresh token expired — merchant must relaunch the app to re-auth
            if (!$shopModel->refresh_token_expires_at || $shopModel->refresh_token_expires_at->isPast()) {
                Log::warning('REFRESH TOKEN EXPIRED', ['shop' => $shopModel->shop]);

                // $shopModel->update(['is_active' => 0]);
                $this->updateStatus($shopModel, 'inactive');
                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh token expired. App must be relaunched to reauthorize.',
                ];
            }

            $response = Http::asJson()->post("https://{$shopModel->shop}/admin/oauth/access_token", [
                'client_id'     => AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key')),
                'client_secret' => AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret')),
                'grant_type'    => 'refresh_token',
                'refresh_token' => $shopModel->refresh_token,
            ]);

            if (!$response->successful()) {
                Log::error('TOKEN REFRESH FAILED', [
                    'shop' => $shopModel->shop,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Shopify signals a dead refresh token with 401 invalid_request
                if ($response->status() === 401) {
                    $shopModel->update(['is_active' => 0]);
                    return [
                        'success' => false,
                        'access_token' => null,
                        'message' => 'Refresh token is no longer valid. App must be relaunched to reauthorize.',
                    ];
                }

                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Failed to refresh Shopify access token. Status: ' . $response->status(),
                ];
            }

            $data = $response->json();

            if (!isset($data['access_token'])) {
                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh response did not include an access token.',
                ];
            }

            $shopModel->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $shopModel->refresh_token,
                'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                'refresh_token_expires_at' => now()->addSeconds($data['refresh_token_expires_in'] ?? 90 * 86400),
            ]);

            Log::info('TOKEN REFRESHED', ['shop' => $shopModel->shop]);

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'message' => 'Token refreshed successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('TOKEN REFRESH EXCEPTION', [
                'shop' => $shopModel->shop ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'access_token' => null,
                'message' => 'Unexpected error while refreshing token: ' . $e->getMessage(),
            ];
        }
    }
}
