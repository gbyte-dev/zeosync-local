<?php

namespace App\Jobs;

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
    ) {}

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

            $this->retryOrAbort($mapping, $errMsg);
            return;
        }

        // -----------------------------------------------------------------
        // EXTRACT LIVE AMAZON QUANTITY
        // -----------------------------------------------------------------
        $liveQuantity = null;

        if (isset($listing['fulfillmentAvailability'][0]['quantity'])) {
            $liveQuantity = (int) $listing['fulfillmentAvailability'][0]['quantity'];
        } elseif (isset($listing['attributes']['fulfillment_availability'][0]['quantity'])) {
            $liveQuantity = (int) $listing['attributes']['fulfillment_availability'][0]['quantity'];
        }

        Log::info('VerifyAmazonInventoryQuantityJob: Live Amazon quantity extracted.', [
            'shop_id'           => $this->shopId,
            'sku'               => $this->sku,
            'attempt'           => $this->attempt,
            'live_quantity'     => $liveQuantity,
            'expected_quantity' => $this->expectedQuantity,
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

                Log::info('VerifyAmazonInventoryQuantityJob: Amazon inventory quantity CONFIRMED.', [
                    'shop_id'  => $this->shopId,
                    'sku'      => $this->sku,
                    'quantity' => $liveQuantity,
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

    private function retryOrAbort(ProductMarketplaceMapping $mapping, string $failureReason): void
    {
        $maxAttempts = 4;

        if ($this->attempt < $maxAttempts) {
            $nextAttempt = $this->attempt + 1;
            // Attempt 1 -> Attempt 2 (+35s) => T+60s
            // Attempt 2 -> Attempt 3 (+60s) => T+120s
            // Attempt 3 -> Attempt 4 (+60s) => T+180s
            $delaySeconds = match ($this->attempt) {
                1       => 35,
                2       => 60,
                default => 60,
            };

            Log::info("VerifyAmazonInventoryQuantityJob: Scheduling retry attempt {$nextAttempt} in {$delaySeconds}s.", [
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
            )->delay(now()->addSeconds($delaySeconds));

            return;
        }

        // Retries exhausted (Attempt 4 @ ~180s) -> mark mismatch / failed
        $fresh = $mapping->fresh();
        if ($this->isStillCurrent($fresh)) {
            $fresh->update([
                'sync_status'       => 'failed',
                'submission_status' => 'mismatch',
                'error_message'     => $failureReason,
            ]);

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
}
