<?php

namespace App\Services\Webhooks;

use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\ShopifyOrder;
use App\Models\WebhookEvent;
use App\Services\AmazonService;
use App\Services\ShopifyInventoryService;
use App\Services\Orders\ShopifyOrderItemService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyOrderWebhookProcessor
{
    public function __construct(
        private readonly AmazonService $amazonService,
        private readonly ShopifyInventoryService $shopifyInventoryService,
        private readonly ShopifyOrderItemService $orderItemService,
    ) {
    }

    public function process(WebhookEvent $event): void
    {
        Cache::lock('privacy:shop:'.($event->shop_id ?? 0), 300)->block(5, fn () => $this->processLocked($event->fresh()));
    }
    private function processLocked(WebhookEvent $event): void
    {
        $shop = $event->shop_id ? \App\Models\Shop::find($event->shop_id) : null;
        if (!$shop) {
            throw new \RuntimeException('Webhook shop no longer exists.');
        }

        $data = app(\App\Services\OrderPrivacyService::class)->filter($shop->id, $event->payload ?: []);
        if (!is_array($data) || empty($data['id'])) {
            throw new \InvalidArgumentException('Invalid Shopify order payload.');
        }

        $event->update(['payload'=>$data]);
        $customer = data_get($data, 'customer', []);
        $lineItems = is_array(data_get($data, 'line_items')) ? data_get($data, 'line_items') : [];

        $order = ShopifyOrder::updateOrCreate(
            [
                'shop_id' => $shop->id,
                'shopify_order_id' => (string) $data['id'],
            ],
            [
                'admin_graphql_api_id' => data_get($data, 'admin_graphql_api_id'),
                'shopify_event_id' => $event->event_id,
                'shopify_webhook_id' => data_get($data, '_zeosync_webhook_id'),
                'order_number' => data_get($data, 'order_number'),
                'name' => data_get($data, 'name'),
                'email' => data_get($data, 'email'),
                'customer_first_name' => data_get($customer, 'first_name'),
                'customer_last_name' => data_get($customer, 'last_name'),
                'customer_phone' => data_get($customer, 'phone'),
                'phone' => data_get($data, 'phone'),
                'financial_status' => data_get($data, 'financial_status'),
                'fulfillment_status' => data_get($data, 'fulfillment_status'),
                'currency' => data_get($data, 'currency'),
                'subtotal_price' => (float) data_get($data, 'subtotal_price', 0),
                'total_tax' => (float) data_get($data, 'total_tax', 0),
                'total_discounts' => (float) data_get($data, 'total_discounts', 0),
                'total_price' => (float) data_get($data, 'total_price', 0),
                'line_items_count' => count($lineItems),
                'source_name' => data_get($data, 'source_name'),
                'tags' => data_get($data, 'tags'),
                'note' => data_get($data, 'note'),
                'customer' => $customer ?: null,
                'billing_address' => data_get($data, 'billing_address'),
                'shipping_address' => data_get($data, 'shipping_address'),
                'line_items' => $lineItems ?: null,
                'discount_codes' => data_get($data, 'discount_codes'),
                'shipping_lines' => data_get($data, 'shipping_lines'),
                'tax_lines' => data_get($data, 'tax_lines'),
                'raw_payload' => $this->withoutInternalMetadata($data),
                'order_created_at' => $this->parseNullableDate(data_get($data, 'created_at')),
                'processed_at' => $this->parseNullableDate(data_get($data, 'processed_at')),
                'cancelled_at' => $this->parseNullableDate(data_get($data, 'cancelled_at')),
            ]
        );

        $this->orderItemService->sync($order, $lineItems);

        $orderedByVariant = [];
        foreach ($lineItems as $item) {
            $variantId = isset($item['variant_id']) ? trim((string) $item['variant_id']) : '';
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($variantId === '' || $quantity === 0) {
                continue;
            }
            $orderedByVariant[$variantId] = ($orderedByVariant[$variantId] ?? 0) + $quantity;
        }

        $operationIds = [];
        foreach ($orderedByVariant as $variantId => $orderedQty) {
            $operation = DB::transaction(function () use ($event, $shop, $variantId, $orderedQty) {
                $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
                    ->where('shopify_variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                if (!$mapping || blank($mapping->amazon_sku)) {
                    return null;
                }

                $sourceKey = "shopify-order:{$shop->id}:" . (string) data_get($event->payload, 'id') . ":mapping:{$mapping->id}";
                $existing = InventorySyncOperation::where('source_key', $sourceKey)->first();
                if ($existing) {
                    return $existing;
                }

                $operation = InventorySyncOperation::create([
                    'shop_id' => $shop->id,
                    'webhook_event_id' => $event->id,
                    'mapping_id' => $mapping->id,
                    'source_key' => $sourceKey,
                    'sku' => (string) $mapping->amazon_sku,
                    'inventory_item_id' => $mapping->shopify_inventory_item_id,
                    'marketplace_id' => $shop->amazon_marketplace_id,
                    'location_id' => $shop->selected_location_id,
                    'delta' => -$orderedQty,
                    // Desired quantity is resolved from authoritative Shopify
                    // inventory immediately before the Amazon write.
                    'desired_quantity' => max(0, (int) $mapping->quantity),
                    'status' => 'pending',
                ]);

                $mapping->update([
                    'sync_status' => 'pending',
                    'error_message' => null,
                ]);

                return $operation;
            }, 3);

            if ($operation) {
                $operationIds[] = $operation->id;
            }
        }

        foreach (array_unique($operationIds) as $operationId) {
            \App\Jobs\ProcessInventoryOperation::dispatch($operationId)->afterCommit();
        }

        if ($order->wasRecentlyCreated) {
            UserNotificationService::send(
                $shop->id,
                'order_sync',
                'Shopify Order Sync Completed',
                'A Shopify order was accepted for inventory synchronization.'
            );
        }
    }

    private function parseNullableDate(mixed $value): mixed
    {
        if (blank($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function withoutInternalMetadata(array $data): array
    {
        unset($data['_zeosync_webhook_id']);
        return $data;
    }
}
