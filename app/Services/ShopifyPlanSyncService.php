<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\AdminSetting;

class ShopifyPlanSyncService
{
    public function sync(Shop $shop): ?ShopSubscription
    {
        try {
            $shopifyBilling = app(ShopifyBillingService::class);

            $tokenResult = $this->ensureFreshAccessToken($shop);

            if (!$tokenResult['success']) {
                Log::warning('SHOPIFY PLAN SYNC SKIPPED', [
                    'shop_id' => $shop->id,
                    'shop' => $shop->shop,
                    'message' => $tokenResult['message'],
                ]);

                return null;
            }

            $shop->refresh();

            $subscription = ShopSubscription::with('plan')
                ->where('shop_id', $shop->id)
                ->first();

            return $shopifyBilling->syncSubscription(
                $shop,
                $subscription
            );
        } catch (\Throwable $e) {
            Log::error('SHOPIFY PLAN SYNC FAILED', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function ensureFreshAccessToken(Shop $shopModel): array
    {
        try {
            if (
                $shopModel->access_token_expires_at &&
                $shopModel->access_token_expires_at->isFuture()
            ) {
                return [
                    'success' => true,
                    'access_token' => $shopModel->access_token,
                    'message' => 'Token still valid.',
                ];
            }

            if (
                !$shopModel->refresh_token_expires_at ||
                $shopModel->refresh_token_expires_at->isPast()
            ) {
                Log::warning('REFRESH TOKEN EXPIRED', [
                    'shop' => $shopModel->shop,
                ]);

                $shopModel->update([
                    'is_active' => 0,
                ]);

                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh token expired. App must be relaunched to reauthorize.',
                ];
            }

            $response = Http::asJson()->post(
                "https://{$shopModel->shop}/admin/oauth/access_token",
                [
                    'client_id' => AdminSetting::get(
                        'SHOPIFY_API_KEY',
                        config('services.shopify.api_key')
                    ),
                    'client_secret' => AdminSetting::get(
                        'SHOPIFY_API_SECRET',
                        config('services.shopify.api_secret')
                    ),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $shopModel->refresh_token,
                ]
            );

            if (!$response->successful()) {
                Log::error('TOKEN REFRESH FAILED', [
                    'shop' => $shopModel->shop,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($response->status() === 401) {
                    $shopModel->update([
                        'is_active' => 0,
                    ]);

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
                Log::error('REFRESH RESPONSE MISSING TOKEN', [
                    'shop' => $shopModel->shop,
                    'body' => $data,
                ]);

                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh response did not include an access token.',
                ];
            }

            $shopModel->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $shopModel->refresh_token,
                'access_token_expires_at' => now()->addSeconds(
                    $data['expires_in'] ?? 3600
                ),
                'refresh_token_expires_at' => now()->addSeconds(
                    $data['refresh_token_expires_in'] ?? (90 * 86400)
                ),
            ]);

            Log::info('TOKEN REFRESHED', [
                'shop' => $shopModel->shop,
            ]);

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