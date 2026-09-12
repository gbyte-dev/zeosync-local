<?php

namespace App\Jobs;

use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessInventoryUpdateJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 4;
    public array $backoff = [10, 30, 60];
    public int $timeout = 60;
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return (string) $this->operationId;
    }

    public function __construct(
        public readonly int $operationId
    ) {}

    public function handle(AmazonService $amazonService): void
    {
        $operation = InventorySyncOperation::find($this->operationId);

        if (!$operation) {
            Log::warning('ProcessInventoryUpdateJob: Operation not found.', [
                'operation_id' => $this->operationId,
            ]);
            return;
        }

        // Terminal or already accepted states check
        if (in_array($operation->status, ['awaiting_verification', 'completed', 'failed', 'superseded', 'stale_external_state'], true)) {
            Log::info('ProcessInventoryUpdateJob: Operation already in terminal or verified state.', [
                'operation_id' => $operation->id,
                'status'       => $operation->status,
            ]);
            return;
        }

        $shop = Shop::find($operation->shop_id);
        if (!$shop) {
            $operation->update([
                'status'     => 'failed',
                'last_error' => 'Shop not found for operation.',
            ]);
            Log::error('ProcessInventoryUpdateJob: Shop not found.', [
                'operation_id' => $operation->id,
                'shop_id'      => $operation->shop_id,
            ]);
            return;
        }

        // Stale / Latest-wins check (before locking)
        if ($this->isSupersededByNewerOperation($operation)) {
            $operation->update([
                'status'     => 'superseded',
                'last_error' => 'Superseded by newer inventory update.',
            ]);
            Log::info('ProcessInventoryUpdateJob: Operation superseded by newer operation.', [
                'operation_id' => $operation->id,
                'shop_id'      => $operation->shop_id,
                'item_id'      => $operation->shopify_inventory_item_id,
            ]);
            return;
        }

        // Resolve mapping
        $mapping = $operation->mapping_id
            ? ProductMarketplaceMapping::find($operation->mapping_id)
            : ProductMarketplaceMapping::where('shop_id', $shop->id)
                ->where('shopify_inventory_item_id', $operation->shopify_inventory_item_id)
                ->first();

        $amazonSku = $operation->amazon_sku ?? $mapping?->amazon_sku;
        $lockSku = !empty($amazonSku) ? $amazonSku : 'item_' . $operation->shopify_inventory_item_id;
        $lockKey = "inventory_sku_lock_{$shop->id}_{$lockSku}";

        // -----------------------------------------------------------------
        // STAGE 1: Update Shopify Inventory (Protected by SKU lock)
        // -----------------------------------------------------------------
        if ($operation->stage === 'pending') {
            $lock = Cache::lock($lockKey, 30);
            $lock->block(15, function () use ($operation, $shop, $mapping) {
                $operation->refresh();
                if ($mapping) {
                    $mapping->refresh();
                }

                if (in_array($operation->status, ['awaiting_verification', 'completed', 'failed', 'superseded', 'stale_external_state'], true)) {
                    return;
                }

                if ($this->isSupersededByNewerOperation($operation)) {
                    $operation->update([
                        'status'     => 'superseded',
                        'last_error' => 'Superseded by newer inventory update.',
                    ]);
                    return;
                }

                // LAYER 1: Local Optimistic Concurrency Control (OCC)
                if ($mapping && $operation->expected_inventory_version !== null) {
                    $currentVersion = (int) ($mapping->inventory_version ?? 1);
                    $expectedVersion = (int) $operation->expected_inventory_version;

                    if ($currentVersion !== $expectedVersion) {
                        $operation->update([
                            'status'     => 'stale_external_state',
                            'last_error' => "Stale local inventory version: current={$currentVersion}, expected={$expectedVersion}.",
                        ]);
                        Log::info('ProcessInventoryUpdateJob: Operation stale due to local inventory version mismatch.', [
                            'operation_id'     => $operation->id,
                            'current_version'  => $currentVersion,
                            'expected_version' => $expectedVersion,
                        ]);
                        return;
                    }
                }

                $locationId = $operation->shopify_location_id;

                if (!$locationId) {
                    $locations = $shop->shopify_locations ?? [];
                    $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
                        ? (int) $shop->selected_location_index
                        : 0;
                    $locationId = $locations[$selectedIndex]['id'] ?? null;
                }

                if (!$locationId) {
                    $errorMsg = 'No Shopify inventory location configured.';
                    $operation->update([
                        'status'     => 'failed',
                        'last_error' => $errorMsg,
                    ]);
                    Log::error('ProcessInventoryUpdateJob: Location missing.', [
                        'operation_id' => $operation->id,
                        'shop_id'      => $shop->id,
                    ]);
                    return;
                }

                $shopify = new ShopifyService($shop->shop, $shop->access_token);

                // LAYER 2: Fresh Authoritative Shopify Inventory Read (bypassing local cache)
                if ($operation->baseline_quantity !== null) {
                    try {
                        $levelsResponse = $shopify->shopifyRest(
                            $shop,
                            'get',
                            'inventory_levels.json',
                            [
                                'inventory_item_ids' => (string) $operation->shopify_inventory_item_id,
                                'location_ids'       => (string) $locationId,
                            ]
                        );

                        if (!empty($levelsResponse['error'])) {
                            $errorMessage = $levelsResponse['message'] ?? 'Failed to fetch authoritative Shopify inventory level.';

                            if (
                                str_contains(strtolower($errorMessage), 'not found') ||
                                str_contains(strtolower($errorMessage), 'invalid') ||
                                str_contains(strtolower($errorMessage), 'unauthorized')
                            ) {
                                $operation->update([
                                    'status'     => 'failed',
                                    'last_error' => $errorMessage,
                                ]);
                                return;
                            }

                            $operation->update(['last_error' => $errorMessage]);
                            throw new \Exception($errorMessage);
                        }

                        $levels = $levelsResponse['inventory_levels'] ?? [];
                        $liveLevel = null;
                        foreach ($levels as $lvl) {
                            if ((string) ($lvl['location_id'] ?? '') === (string) $locationId) {
                                $liveLevel = $lvl;
                                break;
                            }
                        }
                        if (!$liveLevel && !empty($levels)) {
                            $liveLevel = $levels[0];
                        }

                        $liveAvailable = (isset($liveLevel['available']) && $liveLevel['available'] !== null)
                            ? (int) $liveLevel['available']
                            : null;

                        if ($liveAvailable === null) {
                            $operation->update([
                                'status'     => 'failed',
                                'last_error' => 'Live Shopify inventory level unknown or null for location.',
                            ]);
                            Log::warning('ProcessInventoryUpdateJob: Live Shopify inventory level unknown or null.', [
                                'operation_id'      => $operation->id,
                                'inventory_item_id' => $operation->shopify_inventory_item_id,
                                'location_id'       => $locationId,
                            ]);
                            return;
                        }

                        if ($liveAvailable !== (int) $operation->baseline_quantity) {
                            $operation->update([
                                'status'     => 'stale_external_state',
                                'last_error' => "Shopify inventory changed externally from baseline {$operation->baseline_quantity} to {$liveAvailable} before execution.",
                            ]);

                            $selectedIndex = $shop->selected_location_index ?? 0;
                            Cache::forget("shopify_inventory_{$shop->shop}_location_{$selectedIndex}");

                            if ($mapping) {
                                $mapping->update([
                                    'quantity'          => $liveAvailable,
                                    'inventory_version' => ($mapping->inventory_version ?? 1) + 1,
                                ]);
                            }

                            Log::info('ProcessInventoryUpdateJob: Operation aborted due to stale external Shopify inventory baseline.', [
                                'operation_id'      => $operation->id,
                                'baseline_quantity' => $operation->baseline_quantity,
                                'live_quantity'     => $liveAvailable,
                                'desired_quantity'  => $operation->desired_quantity,
                            ]);
                            return;
                        }
                    } catch (\Throwable $e) {
                        if ($operation->status === 'stale_external_state' || $operation->status === 'failed') {
                            return;
                        }
                        $operation->update(['last_error' => $e->getMessage()]);
                        throw $e;
                    }
                }

                // Mark as processing
                $operation->update([
                    'status'                => 'processing',
                    'processing_started_at' => now(),
                    'attempts'              => $operation->attempts + 1,
                ]);

                try {
                    $response = $shopify->shopifyRest(
                        $shop,
                        'post',
                        'inventory_levels/set.json',
                        [
                            'location_id'       => $locationId,
                            'inventory_item_id' => $operation->shopify_inventory_item_id,
                            'available'         => $operation->desired_quantity,
                        ]
                    );

                    if (!empty($response['error'])) {
                        $errorMessage = $response['message'] ?? 'Shopify inventory update failed.';

                        // Check if permanent error
                        if (
                            str_contains(strtolower($errorMessage), 'not found') ||
                            str_contains(strtolower($errorMessage), 'invalid') ||
                            str_contains(strtolower($errorMessage), 'unauthorized')
                        ) {
                            $operation->update([
                                'status'     => 'failed',
                                'last_error' => $errorMessage,
                            ]);
                            return;
                        }

                        // Transient error -> throw to trigger queue retry
                        $operation->update(['last_error' => $errorMessage]);
                        throw new \Exception($errorMessage);
                    }
                } catch (\Throwable $e) {
                    $operation->update(['last_error' => $e->getMessage()]);
                    throw $e;
                }

                // Shopify success: update stage & cache
                $operation->update([
                    'stage'      => 'shopify_completed',
                    'last_error' => null,
                ]);

                $selectedIndex = $shop->selected_location_index ?? 0;
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$selectedIndex}");

                // Reflect confirmed Shopify quantity in mapping and advance version
                if ($mapping) {
                    $mapping->update([
                        'quantity'          => $operation->desired_quantity,
                        'inventory_version' => ($mapping->inventory_version ?? 1) + 1,
                    ]);
                }
            });
        }

        $operation->refresh();
        if (in_array($operation->status, ['awaiting_verification', 'completed', 'failed', 'superseded', 'stale_external_state'], true)) {
            return;
        }

        // -----------------------------------------------------------------
        // STAGE 2: Update Amazon Inventory (Handled via AmazonService)
        // -----------------------------------------------------------------
        if ($mapping && !empty($amazonSku)) {
            $lock = Cache::lock($lockKey, 30);
            $lock->block(15, function () use ($operation, $shop, $mapping, $amazonSku, $amazonService) {
                $operation->refresh();
                if ($mapping) {
                    $mapping->refresh();
                }

                if (in_array($operation->status, ['awaiting_verification', 'completed', 'failed', 'superseded', 'stale_external_state'], true)) {
                    return;
                }

                // Re-verify stale protection before outbound Amazon push (Stage 2 check)
                if ($this->isSupersededByNewerOperation($operation)) {
                    $operation->update([
                        'status'     => 'superseded',
                        'last_error' => 'Superseded before Amazon sync.',
                    ]);
                    return;
                }

                // Verify mapping inventory version has not been superseded by an intervening external order
                if ($operation->expected_inventory_version !== null && ($mapping->inventory_version ?? 1) > ((int) $operation->expected_inventory_version + 1)) {
                    $operation->update([
                        'status'     => 'stale_external_state',
                        'last_error' => 'Inventory state changed before Amazon sync.',
                    ]);
                    Log::info('ProcessInventoryUpdateJob: Amazon sync aborted because mapping version advanced.', [
                        'operation_id'     => $operation->id,
                        'mapping_version'  => $mapping->inventory_version,
                        'expected_version' => $operation->expected_inventory_version,
                    ]);
                    return;
                }

                // LAYER 2 for Stage 2: Fresh authoritative Shopify Inventory Read immediately before Amazon write
                $locationId = $operation->shopify_location_id;
                if (!$locationId) {
                    $locations = $shop->shopify_locations ?? [];
                    $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
                        ? (int) $shop->selected_location_index
                        : 0;
                    $locationId = $locations[$selectedIndex]['id'] ?? null;
                }

                if ($locationId) {
                    $shopify = new ShopifyService($shop->shop, $shop->access_token);
                    try {
                        $levelsResponse = $shopify->shopifyRest(
                            $shop,
                            'get',
                            'inventory_levels.json',
                            [
                                'inventory_item_ids' => (string) $operation->shopify_inventory_item_id,
                                'location_ids'       => (string) $locationId,
                            ]
                        );

                        if (!empty($levelsResponse['error'])) {
                            $errorMessage = $levelsResponse['message'] ?? 'Failed to fetch authoritative Shopify inventory level before Amazon sync.';
                            if (
                                str_contains(strtolower($errorMessage), 'not found') ||
                                str_contains(strtolower($errorMessage), 'invalid') ||
                                str_contains(strtolower($errorMessage), 'unauthorized')
                            ) {
                                $operation->update([
                                    'status'     => 'failed',
                                    'last_error' => $errorMessage,
                                ]);
                                return;
                            }

                            $operation->update(['last_error' => $errorMessage]);
                            throw new \Exception($errorMessage);
                        }

                        $levels = $levelsResponse['inventory_levels'] ?? [];
                        $liveLevel = null;
                        foreach ($levels as $lvl) {
                            if ((string) ($lvl['location_id'] ?? '') === (string) $locationId) {
                                $liveLevel = $lvl;
                                break;
                            }
                        }
                        if (!$liveLevel && !empty($levels)) {
                            $liveLevel = $levels[0];
                        }

                        $liveAvailable = (isset($liveLevel['available']) && $liveLevel['available'] !== null)
                            ? (int) $liveLevel['available']
                            : null;

                        if ($liveAvailable === null) {
                            $operation->update([
                                'status'     => 'failed',
                                'last_error' => 'Live Shopify inventory level unknown or null for location before Amazon sync.',
                            ]);
                            Log::warning('ProcessInventoryUpdateJob: Live Shopify inventory level unknown or null before Amazon sync.', [
                                'operation_id'      => $operation->id,
                                'inventory_item_id' => $operation->shopify_inventory_item_id,
                                'location_id'       => $locationId,
                            ]);
                            return;
                        }

                        // Stage 2 expected state is operation->desired_quantity (which was established in Stage 1)
                        if ($liveAvailable !== (int) $operation->desired_quantity) {
                            $operation->update([
                                'status'     => 'stale_external_state',
                                'last_error' => "Shopify inventory changed externally to {$liveAvailable} after Shopify stage before Amazon sync.",
                            ]);

                            $selectedIndex = $shop->selected_location_index ?? 0;
                            Cache::forget("shopify_inventory_{$shop->shop}_location_{$selectedIndex}");

                            if ($mapping) {
                                $mapping->update([
                                    'quantity'          => $liveAvailable,
                                    'inventory_version' => ($mapping->inventory_version ?? 1) + 1,
                                ]);
                            }

                            Log::info('ProcessInventoryUpdateJob: Amazon sync aborted because live Shopify inventory changed after Stage 1.', [
                                'operation_id'      => $operation->id,
                                'desired_quantity'  => $operation->desired_quantity,
                                'live_quantity'     => $liveAvailable,
                            ]);
                            return;
                        }
                    } catch (\Throwable $e) {
                        if ($operation->status === 'stale_external_state' || $operation->status === 'failed') {
                            return;
                        }
                        $operation->update(['last_error' => $e->getMessage()]);
                        throw $e;
                    }
                }

                // Mark as processing if not already
                if ($operation->status !== 'processing') {
                    $operation->update([
                        'status'                => 'processing',
                        'processing_started_at' => now(),
                        'attempts'              => $operation->attempts + 1,
                    ]);
                }

                $amazonTargetQty = max(0, $operation->desired_quantity);

                try {
                    if ($operation->desired_quantity < 0) {
                        $amazonResult = $amazonService->updateInventory(
                            $shop,
                            $amazonSku,
                            0,
                            syncToShopify: false,
                            shopifyMappingQuantity: $operation->desired_quantity
                        );
                    } else {
                        $amazonResult = $amazonService->updateInventory(
                            $shop,
                            $amazonSku,
                            $amazonTargetQty,
                            syncToShopify: false
                        );
                    }

                    if ($mapping) {
                        $submissionId = is_array($amazonResult) ? ($amazonResult['submissionId'] ?? null) : null;
                        $submissionId ??= $mapping->fresh()?->submission_id;

                        $mapping->update([
                            'quantity'          => $operation->desired_quantity,
                            'sync_status'       => 'success',
                            'submission_status' => 'accepted',
                            'submission_id'     => $submissionId,
                            'last_synced_at'    => now(),
                            'error_message'     => null,
                        ]);
                    }

                    $operation->update([
                        'status'       => 'awaiting_verification',
                        'stage'        => 'amazon_accepted',
                        'last_error'   => null,
                    ]);

                    Log::info('ProcessInventoryUpdateJob: Inventory operation accepted by Amazon, awaiting verification.', [
                        'operation_id'     => $operation->id,
                        'shop_id'          => $shop->id,
                        'desired_quantity' => $operation->desired_quantity,
                        'amazon_sku'       => $amazonSku,
                    ]);
                } catch (\Throwable $e) {
                    if ($mapping) {
                        $freshMapping = $mapping->fresh();
                        $subStatus = ($freshMapping?->submission_status === 'rejected') ? 'rejected' : 'failed';
                        $mapping->update([
                            'sync_status'       => 'failed',
                            'submission_status' => $subStatus,
                            'error_message'     => $e->getMessage(),
                        ]);
                    }

                    $operation->update(['last_error' => $e->getMessage()]);

                    // If Amazon rejected permanently
                    if (str_contains(strtolower($e->getMessage()), 'rejected') || str_contains(strtolower($e->getMessage()), 'invalid')) {
                        $operation->update([
                            'status'     => 'failed',
                            'last_error' => $e->getMessage(),
                        ]);
                        return;
                    }

                    // Transient -> throw for queue retry
                    throw $e;
                }
            });
        } else {
            // Shopify-only item without Amazon mapping
            $operation->update([
                'status'       => 'completed',
                'stage'        => 'completed',
                'completed_at' => now(),
                'last_error'   => null,
            ]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $operation = InventorySyncOperation::find($this->operationId);
        if ($operation && !in_array($operation->status, ['awaiting_verification', 'completed', 'superseded', 'stale_external_state'], true)) {
            $operation->update([
                'status'     => 'failed',
                'last_error' => $exception?->getMessage() ?? 'Max retry attempts exhausted.',
            ]);

            Log::error('ProcessInventoryUpdateJob: Operation permanently failed.', [
                'operation_id' => $operation->id,
                'error'        => $exception?->getMessage(),
            ]);
        }
    }

    private function isSupersededByNewerOperation(InventorySyncOperation $operation): bool
    {
        return InventorySyncOperation::where('shop_id', $operation->shop_id)
            ->where('shopify_inventory_item_id', $operation->shopify_inventory_item_id)
            ->where('id', '>', $operation->id)
            ->whereIn('status', ['pending', 'processing', 'awaiting_verification', 'completed'])
            ->exists();
    }
}
