<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class StoreStatusService
{
    public function check(Shop $shop): void
    {
        Log::info('STORE STATUS CHECK STARTED', [
            'shop_id' => $shop->id,
            'shop'    => $shop->shop,
        ]);

        $shopifyService = app(ShopifyService::class, [
            'shop'  => $shop->shop,
            'token' => $shop->access_token
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
                    $this->updateStatus($shop, 'banned');
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
}
