<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInventoryUpdateJob;
use App\Models\InventorySyncOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecoverInventorySyncOperationsCommand extends Command
{
    protected $signature = 'inventory:recover-operations {--pending-timeout=2 : Minutes before pending is considered abandoned} {--processing-timeout=3 : Minutes before processing is considered abandoned}';

    protected $description = 'Recover abandoned or stuck pending/processing inventory sync operations';

    public function handle(): int
    {
        $pendingTimeout = (int) $this->option('pending-timeout');
        $processingTimeout = (int) $this->option('processing-timeout');
        $pendingCutoff = now()->subMinutes($pendingTimeout)->toDateTimeString();
        $processingCutoff = now()->subMinutes($processingTimeout)->toDateTimeString();
        $nowStr = now()->toDateTimeString();

        $this->info("Scanning for abandoned inventory sync operations (Pending > {$pendingTimeout}m, Processing > {$processingTimeout}m)...");

        // -----------------------------------------------------------------
        // 1. Recover abandoned PENDING operations
        // (Guarded by last_dispatched_at threshold + ShouldBeUnique on Job)
        // -----------------------------------------------------------------
        $abandonedPending = InventorySyncOperation::where('status', 'pending')
            ->where(function ($query) use ($pendingCutoff) {
                $query->where(function ($q) use ($pendingCutoff) {
                    $q->whereNull('last_dispatched_at')
                        ->where('created_at', '<=', $pendingCutoff);
                })->orWhere('last_dispatched_at', '<=', $pendingCutoff);
            })
            ->limit(50)
            ->get();

        $recoveredPendingCount = 0;
        foreach ($abandonedPending as $operation) {
            // Atomic check & update last_dispatched_at before dispatching
            $updated = InventorySyncOperation::where('id', $operation->id)
                ->where('status', 'pending')
                ->where(function ($query) use ($pendingCutoff) {
                    $query->where(function ($q) use ($pendingCutoff) {
                        $q->whereNull('last_dispatched_at')
                            ->where('created_at', '<=', $pendingCutoff);
                    })->orWhere('last_dispatched_at', '<=', $pendingCutoff);
                })
                ->update([
                    'last_dispatched_at' => $nowStr,
                ]);

            if ($updated) {
                ProcessInventoryUpdateJob::dispatch($operation->id);
                $recoveredPendingCount++;

                Log::info('RecoverInventorySyncOperationsCommand: Recovered abandoned pending operation.', [
                    'operation_id' => $operation->id,
                    'shop_id'      => $operation->shop_id,
                    'created_at'   => $operation->created_at?->toDateTimeString(),
                ]);
            }
        }

        // -----------------------------------------------------------------
        // 2. Recover abandoned PROCESSING operations
        // (Guarded by non-blocking SKU lock + atomic state transition)
        // -----------------------------------------------------------------
        $abandonedProcessing = InventorySyncOperation::where('status', 'processing')
            ->where('processing_started_at', '<=', $processingCutoff)
            ->limit(50)
            ->get();

        $recoveredProcessingCount = 0;
        foreach ($abandonedProcessing as $operation) {
            $lockSku = !empty($operation->amazon_sku) ? $operation->amazon_sku : 'item_' . $operation->shopify_inventory_item_id;
            $lockKey = "inventory_sku_lock_{$operation->shop_id}_{$lockSku}";
            $lock = Cache::lock($lockKey, 10);

            // Attempt non-blocking lock acquisition:
            // If active worker is currently running inside critical section, get() returns false -> DO NOT STEAL!
            if (!$lock->get()) {
                Log::info('RecoverInventorySyncOperationsCommand: Active worker currently holds SKU lock, skipping.', [
                    'operation_id' => $operation->id,
                    'shop_id'      => $operation->shop_id,
                ]);
                continue;
            }

            try {
                if ($operation->attempts >= $operation->max_attempts) {
                    $operation->update([
                        'status'     => 'failed',
                        'last_error' => 'Max recovery attempts reached for abandoned processing operation.',
                    ]);
                    Log::warning('RecoverInventorySyncOperationsCommand: Marked abandoned operation as failed (retry limit).', [
                        'operation_id' => $operation->id,
                        'attempts'     => $operation->attempts,
                    ]);
                } else {
                    // Atomic DB state transition inside lock
                    $updated = InventorySyncOperation::where('id', $operation->id)
                        ->where('status', 'processing')
                        ->where('processing_started_at', '<=', $processingCutoff)
                        ->update([
                            'status'                => 'pending',
                            'processing_started_at' => null,
                            'last_dispatched_at'    => $nowStr,
                        ]);

                    if ($updated) {
                        ProcessInventoryUpdateJob::dispatch($operation->id);
                        $recoveredProcessingCount++;

                        Log::info('RecoverInventorySyncOperationsCommand: Recovered stuck processing operation.', [
                            'operation_id'          => $operation->id,
                            'shop_id'               => $operation->shop_id,
                            'processing_started_at' => $operation->processing_started_at?->toDateTimeString(),
                        ]);
                    }
                }
            } finally {
                $lock->release();
            }
        }

        $this->info("Recovery completed. Recovered {$recoveredPendingCount} pending and {$recoveredProcessingCount} processing operations.");

        return Command::SUCCESS;
    }
}
