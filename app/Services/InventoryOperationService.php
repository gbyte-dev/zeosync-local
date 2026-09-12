<?php
namespace App\Services;
use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Jobs\ProcessInventoryOperation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
class InventoryOperationService
{
    public static function lockKey(int $shopId): string { return "inventory:shop:{$shopId}"; }
    public function enqueue(Shop $shop, ?ProductMarketplaceMapping $mapping, string $source, int $quantity, ?string $item = null, ?string $sku = null): InventorySyncOperation
    {
        $operation = InventorySyncOperation::create([
            'shop_id' => $shop->id,'mapping_id' => $mapping?->id,
            'source_key' => 'manual:'.Str::uuid(),'source' => $source,
            'sku' => $mapping?->amazon_sku ?? $sku ?? '',
            'inventory_item_id' => $mapping?->shopify_inventory_item_id ?? $item,
            'location_id' => $shop->selected_location_id,
            'marketplace_id' => $shop->amazon_marketplace_id,
            'requested_quantity' => $quantity,'desired_quantity' => $quantity,'status' => 'pending',
        ]);
        ProcessInventoryOperation::dispatch($operation->id)->afterCommit();
        return $operation;
    }

    public function process(int $id): void
    {
        $initial = InventorySyncOperation::find($id);
        if (!$initial) { return; }
        Cache::lock(self::lockKey($initial->shop_id), 300)->block(5, function () use ($id) {
            $op = InventorySyncOperation::find($id);
            if (!$op || in_array($op->status, ['verified','completed','superseded','review_required'], true)) { return; }
            $shop = Shop::find($op->shop_id);
            if (!$shop || !$shop->is_active) { throw new \RuntimeException('Shop is inactive.'); }
            $mapping = $op->mapping_id ? ProductMarketplaceMapping::where('shop_id',$shop->id)->find($op->mapping_id) : null;
            if (!$op->mapping_id && $op->source === 'manual_amazon' && ProductMarketplaceMapping::where('shop_id',$shop->id)->where('amazon_sku',$op->sku)->exists()) {
                $op->update(['status'=>'review_required','error'=>'SKU is now mapped; request synchronization from Shopify.']); return;
            }
            if ($op->mapping_id && (!$mapping || $mapping->amazon_sku !== $op->sku || ($op->inventory_item_id && $mapping->shopify_inventory_item_id !== $op->inventory_item_id))) {
                $op->update(['status'=>'review_required','error'=>'Mapping changed; request a new synchronization.']); return;
            }
            $op->update(['attempts'=>$op->attempts+1,'error'=>null]);
            $this->processLocked($op, $shop, $mapping);
        });
    }

    private function processLocked($op, $shop, $mapping): void
    {
        try {
            $shopify = app(ShopifyInventoryService::class);
            if ($op->source === 'manual_shopify' && $op->source_state !== 'applied') {
                $op->update(['source_state' => 'started', 'status' => 'processing']);
                $shopify->setItemQuantity($shop, $op->inventory_item_id, $op->desired_quantity, $op->source_key);
                $op->update(['source_state' => 'applied']);
            }
            if (! $mapping && $op->source === 'manual_shopify') {
                $observed = $shopify->getItemAvailableQuantity($shop, $op->inventory_item_id);
                $ok = $observed === $op->desired_quantity;
                $op->update(['status' => $ok ? 'verified' : 'review_required', 'observed_quantity' => $observed, 'processed_at' => now()]);
                $shopify->invalidate($shop);

                return;
            }
            $quantity = $mapping ? $shopify->getVariantAvailableQuantity($shop, (string) $mapping->shopify_variant_id) : $op->desired_quantity;
            $amazon = app(AmazonService::class);
            $op->update(['submitted_at' => now()]);
            // Main AmazonService::updateInventory throws on failure and otherwise
            // returns the raw Amazon response; rely on the exception, not a flag.
            $amazon->updateInventory($shop, $op->sku, $quantity);
            $op->update(['desired_quantity' => $quantity, 'status' => 'accepted', 'next_attempt_at' => now()->addSeconds(30)]);
            $mapping?->update(['sync_status' => 'accepted', 'error_message' => null]);
        } catch (\Throwable $e) {
            $op->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 1000), 'next_attempt_at' => now()->addMinutes(1)]);
            $mapping?->update(['sync_status' => 'failed', 'error_message' => 'Inventory synchronization will retry.']);
            throw $e;
        }
    }
}