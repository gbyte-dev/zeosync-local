<?php

namespace App\Jobs;

use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyAmazonInventoryQuantityJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $shopId,
        public readonly string $sku,
        public readonly int $expectedQuantity,
        public readonly ?string $submissionId = null,
        public readonly ?string $syncedAt = null,
        public readonly int $attempt = 1
    ) {
        $this->onConnection('database')->onQueue('default');
    }

    public function handle(AmazonService $amazonService): void
    {
        $shop = Shop::find($this->shopId);
        if (!$shop) {
            Log::warning('VerifyAmazonInventoryQuantityJob: Shop not found.', [
                'shop_id' => $this->shopId,
                'sku'     => $this->sku,
            ]);
            return;
        }

        $mapping = ProductMarketplaceMapping::where('shop_id', $this->shopId)
            ->where('amazon_sku', $this->sku)
            ->first();

        if (!$mapping) {
            Log::info('VerifyAmazonInventoryQuantityJob: Mapping not found or unmapped.', [
                'shop_id' => $this->shopId,
                'sku'     => $this->sku,
            ]);
            return;
        }

        // -----------------------------------------------------------------
        // RACE CONDITION PROTECTION
        // -----------------------------------------------------------------
        if ($this->submissionId !== null && $mapping->submission_id !== $this->submissionId) {
            Log::info('VerifyAmazonInventoryQuantityJob: Abandoning verification (submission_id mismatch).', [
                'shop_id'             => $this->shopId,
                'sku'                 => $this->sku,
                'job_submission_id'   => $this->submissionId,
                'db_submission_id'    => $mapping->submission_id,
            ]);
            return;
        }

        $expectedAmazonQty = max(0, (int) $mapping->quantity);
        if ($expectedAmazonQty !== (int) $this->expectedQuantity) {
            Log::info('VerifyAmazonInventoryQuantityJob: Abandoning verification (quantity changed).', [
                'shop_id'            => $this->shopId,
                'sku'                => $this->sku,
                'job_expected'       => $this->expectedQuantity,
                'db_quantity'        => $mapping->quantity,
                'db_amazon_expected' => $expectedAmazonQty,
            ]);
            return;
        }

        if ($this->syncedAt !== null && !empty($mapping->last_synced_at)) {
            try {
                $currentSyncedAt = Carbon::parse($mapping->last_synced_at);
                $originalSyncedAt = Carbon::parse($this->syncedAt);
                if ($currentSyncedAt->greaterThan($originalSyncedAt)) {
                    Log::info('VerifyAmazonInventoryQuantityJob: Abandoning verification (newer sync timestamp).', [
                        'shop_id'            => $this->shopId,
                        'sku'                => $this->sku,
                        'job_synced_at'      => $this->syncedAt,
                        'db_last_synced_at'  => $mapping->last_synced_at,
                    ]);
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('VerifyAmazonInventoryQuantityJob: Timestamp parse error, proceeding cautiously.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // If already confirmed, nothing to do
        if ($mapping->submission_status === 'confirmed') {
            return;
        }

        // -----------------------------------------------------------------
        // EXECUTE AMAZON LISTING READ-BACK
        // -----------------------------------------------------------------
        try {
            $listing = $amazonService->checkAmazonListing($shop, $this->sku);
        } catch (\Throwable $e) {
            Log::error('VerifyAmazonInventoryQuantityJob: checkAmazonListing threw exception.', [
                'shop_id' => $this->shopId,
                'sku'     => $this->sku,
                'attempt' => $this->attempt,
                'error'   => $e->getMessage(),
            ]);

            $this->retryOrAbort($mapping, "Amazon verification request failed: {$e->getMessage()}");
            return;
        }

        if (isset($listing['success']) && $listing['success'] === false) {
            $errMsg = $listing['error'] ?? 'Amazon listing check returned failure';
            Log::warning('VerifyAmazonInventoryQuantityJob: checkAmazonListing returned failure.', [
                'shop_id' => $this->shopId,
                'sku'     => $this->sku,
                'attempt' => $this->attempt,
                'error'   => $errMsg,
            ]);

            $isFatal = $this->isPermanentFailure($errMsg);
            $this->retryOrAbort($mapping, $errMsg, forceAbort: $isFatal);
            return;
        }

        // -----------------------------------------------------------------
        // EXTRACT LIVE AMAZON QUANTITY (CHANNEL-AWARE)
        // -----------------------------------------------------------------
        $resolver = app(\App\Services\AmazonFulfillmentChannelResolver::class);
        $resolution = $resolver->resolve($mapping, $this->expectedQuantity, $listing);
        $targetChannel = $resolution['channel'];

        if ($targetChannel !== null) {
            $resolved = $this->resolveLiveQuantityForChannel($listing, (string) $targetChannel);
            $liveQuantity = $resolved['quantity'];
        } else {
            $liveQuantity = null;
            $resolved = [
                'quantity'           => null,
                'source'             => $resolution['source'],
                'channel'            => null,
                'available_channels' => $resolution['available_channels'],
            ];
        }

        $matchingOp = !empty($mapping->shopify_inventory_item_id)
            ? InventorySyncOperation::where('shop_id', $this->shopId)
                ->where('shopify_inventory_item_id', $mapping->shopify_inventory_item_id)
                ->whereIn('status', ['awaiting_verification', 'processing'])
                ->latest('id')
                ->first()
            : null;

        Log::info('Amazon Inventory Verification Quantity Resolved', [
            'operation_id'       => $matchingOp?->id,
            'operation_uuid'     => $matchingOp?->operation_uuid,
            'amazon_sku'         => $this->sku,
            'expected_quantity'  => $this->expectedQuantity,
            'target_channel'     => $targetChannel,
            'selected_quantity'  => $liveQuantity,
            'selected_source'    => $resolved['source'],
            'selected_channel'   => $resolved['channel'],
            'available_channels' => $resolved['available_channels'],
            'attempt'            => $this->attempt,
            'submission_id'      => $this->submissionId,
        ]);

        // -----------------------------------------------------------------
        // 1. PRIORITIZE ACTUAL QUANTITY VERIFICATION
        // If live quantity matches expected quantity -> CONFIRMED!
        // (Even if generic/unrelated catalog listing issues exist)
        // -----------------------------------------------------------------
        if ($liveQuantity !== null && $liveQuantity === (int) $this->expectedQuantity) {
            $fresh = $mapping->fresh();
            if ($this->isStillCurrent($fresh)) {
                $fresh->update([
                    'sync_status'       => 'success',
                    'submission_status' => 'confirmed',
                    'error_message'     => null,
                ]);

                // Transition matching awaiting_verification operations to completed
                if (!empty($fresh->shopify_inventory_item_id)) {
                    InventorySyncOperation::where('shop_id', $this->shopId)
                        ->where('shopify_inventory_item_id', $fresh->shopify_inventory_item_id)
                        ->whereIn('status', ['awaiting_verification', 'processing'])
                        ->update([
                            'status'       => 'completed',
                            'stage'        => 'completed',
                            'completed_at' => now(),
                        ]);
                }

                Log::info('VerifyAmazonInventoryQuantityJob: Amazon inventory quantity CONFIRMED.', [
                    'shop_id'  => $this->shopId,
                    'sku'      => $this->sku,
                    'quantity' => $liveQuantity,
                    'attempt'  => $this->attempt,
                ]);
            }
            return;
        }

        // -----------------------------------------------------------------
        // 2. QUANTITY DOES NOT MATCH (YET) -> RETRY OR MISMATCH
        // Do NOT immediately mark rejected on generic listing errors; continue
        // bounded retry until Amazon finishes asynchronous processing.
        // -----------------------------------------------------------------
        $liveQtyStr = $liveQuantity !== null ? (string) $liveQuantity : 'unreported';
        $failureReason = "Amazon inventory quantity mismatch: expected {$this->expectedQuantity}, but Amazon reported {$liveQtyStr} after {$this->attempt} attempt(s).";

        $this->retryOrAbort($mapping, $failureReason);
    }

    private function isPermanentFailure(string $errorMessage): bool
    {
        $lower = strtolower($errorMessage);
        return str_contains($lower, 'invalid sku')
            || str_contains($lower, 'unauthorized')
            || str_contains($lower, 'invalid product type')
            || str_contains($lower, 'invalid marketplace')
            || str_contains($lower, 'permanently rejected');
    }

    private function retryOrAbort(ProductMarketplaceMapping $mapping, string $failureReason, bool $forceAbort = false): void
    {
        $maxAttempts = 8;

        if (!$forceAbort && $this->attempt < $maxAttempts) {
            $nextAttempt = $this->attempt + 1;
            // Progressive reconciliation schedule:
            // Attempt 1 -> 2: +35s (T+60s)
            // Attempt 2 -> 3: +60s (T+120s)
            // Attempt 3 -> 4: +60s (T+180s)
            // Attempt 4 -> 5: +120s (T+300s / 5m)
            // Attempt 5 -> 6: +180s (T+480s / 8m)
            // Attempt 6 -> 7: +240s (T+720s / 12m)
            // Attempt 7 -> 8: +300s (T+1020s / 17m)
            $delaySeconds = match ($this->attempt) {
                1       => 35,
                2, 3    => 60,
                4       => 120,
                5       => 180,
                6       => 240,
                default => 300,
            };

            Log::info("VerifyAmazonInventoryQuantityJob: Scheduling reconciliation attempt {$nextAttempt} in {$delaySeconds}s.", [
                'shop_id' => $this->shopId,
                'sku'     => $this->sku,
                'attempt' => $this->attempt,
            ]);

            self::dispatch(
                $this->shopId,
                $this->sku,
                $this->expectedQuantity,
                $this->submissionId,
                $this->syncedAt,
                $nextAttempt
            )->onConnection('database')
             ->onQueue('default')
             ->delay(now()->addSeconds($delaySeconds));

            return;
        }

        // Retries exhausted -> mark mismatch / failed
        $fresh = $mapping->fresh();
        if ($this->isStillCurrent($fresh)) {
            $fresh->update([
                'sync_status'       => 'failed',
                'submission_status' => 'mismatch',
                'error_message'     => $failureReason,
            ]);

            // Mark matching awaiting_verification operations as failed
            if (!empty($fresh->shopify_inventory_item_id)) {
                InventorySyncOperation::where('shop_id', $this->shopId)
                    ->where('shopify_inventory_item_id', $fresh->shopify_inventory_item_id)
                    ->whereIn('status', ['awaiting_verification'])
                    ->update([
                        'status'     => 'failed',
                        'last_error' => $failureReason,
                    ]);
            }

            Log::warning('VerifyAmazonInventoryQuantityJob: Max verification attempts reached (mismatch).', [
                'shop_id' => $this->shopId,
                'sku'     => $this->sku,
                'error'   => $failureReason,
            ]);
        }
    }

    private function isStillCurrent(?ProductMarketplaceMapping $mapping): bool
    {
        if (!$mapping) {
            return false;
        }

        if ($this->submissionId !== null && $mapping->submission_id !== $this->submissionId) {
            return false;
        }

        $expectedAmazonQty = max(0, (int) $mapping->quantity);
        if ($expectedAmazonQty !== (int) $this->expectedQuantity) {
            return false;
        }

        if ($this->syncedAt !== null && !empty($mapping->last_synced_at)) {
            try {
                $currentSyncedAt = Carbon::parse($mapping->last_synced_at);
                $originalSyncedAt = Carbon::parse($this->syncedAt);
                if ($currentSyncedAt->greaterThan($originalSyncedAt)) {
                    return false;
                }
            } catch (\Throwable) {
                // Ignore parse error on check
            }
        }

        return true;
    }

    private function resolveLiveQuantityForChannel(array $listing, string $targetChannel): array
    {
        $selectedQuantity = null;
        $selectedSource = null;
        $selectedChannel = null;
        $availableChannels = [];

        // 1. Search top-level fulfillmentAvailability for target channel
        if (!empty($listing['fulfillmentAvailability']) && is_array($listing['fulfillmentAvailability'])) {
            foreach ($listing['fulfillmentAvailability'] as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $channel = $entry['fulfillmentChannelCode'] ?? $entry['fulfillment_channel_code'] ?? 'DEFAULT';
                $qty = $entry['quantity'] ?? null;
                $availableChannels[] = "fulfillmentAvailability[{$index}]:{$channel}=" . ($qty ?? 'null');
                if ($selectedQuantity === null && strcasecmp((string) $channel, $targetChannel) === 0 && $qty !== null) {
                    $selectedQuantity = (int) $qty;
                    $selectedSource = "fulfillmentAvailability[{$index}]";
                    $selectedChannel = (string) $channel;
                }
            }
        }

        // 2. Search attributes.fulfillment_availability for target channel
        if ($selectedQuantity === null && !empty($listing['attributes']['fulfillment_availability']) && is_array($listing['attributes']['fulfillment_availability'])) {
            foreach ($listing['attributes']['fulfillment_availability'] as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $channel = $entry['fulfillment_channel_code'] ?? $entry['fulfillmentChannelCode'] ?? 'DEFAULT';
                $qty = $entry['quantity'] ?? null;
                $availableChannels[] = "attributes.fulfillment_availability[{$index}]:{$channel}=" . ($qty ?? 'null');
                if ($selectedQuantity === null && strcasecmp((string) $channel, $targetChannel) === 0 && $qty !== null) {
                    $selectedQuantity = (int) $qty;
                    $selectedSource = "attributes.fulfillment_availability[{$index}]";
                    $selectedChannel = (string) $channel;
                }
            }
        }

        return [
            'quantity'           => $selectedQuantity,
            'source'             => $selectedSource,
            'channel'            => $selectedChannel,
            'available_channels' => $availableChannels,
        ];
    }
}
