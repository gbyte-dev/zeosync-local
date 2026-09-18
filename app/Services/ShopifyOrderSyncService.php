<?php

namespace App\Services;

use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Services\AmazonService;
use App\Services\UserNotificationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ShopifyOrderSyncService
{
    public function __construct(
        protected AmazonService $amazonService
    ) {}

    /**
     * Synchronize a Shopify order payload safely and idempotently.
     *
     * @param Shop $shop
     * @param array $data Raw Shopify order payload
     * @param string $action 'create' | 'update' | 'sync'
     * @param string|null $eventId X-Shopify-Event-Id header value
     * @param string|null $webhookId X-Shopify-Webhook-Id header value
     * @return array
     */
    public function syncOrder(
        Shop $shop,
        array $data,
        string $action = 'create',
        ?string $eventId = null,
        ?string $webhookId = null
    ): array {
        $rawOrderId = $data['id'] ?? null;
        if (empty($rawOrderId)) {
            throw new InvalidArgumentException('Invalid Shopify order payload: missing order ID.');
        }

        $orderId = trim((string) $rawOrderId);
        $eventId = !empty($eventId) ? trim((string) $eventId) : null;
        $webhookId = !empty($webhookId) ? trim((string) $webhookId) : null;

        Log::info('Shopify order sync started.', [
            'shop_id' => $shop->id,
            'shop_domain' => $shop->shop,
            'shopify_order_id' => $orderId,
            'shopify_event_id' => $eventId,
            'action' => $action,
        ]);

        // Secondary check: Exact event ID already processed for this shop
        if ($eventId !== null && ShopifyOrder::where('shop_id', $shop->id)->where('shopify_event_id', $eventId)->exists()) {
            $existing = ShopifyOrder::where('shop_id', $shop->id)->where('shopify_order_id', $orderId)->first();
            Log::info('Shopify order sync skipped: exact webhook event already processed.', [
                'shop_id' => $shop->id,
                'shopify_order_id' => $orderId,
                'shopify_event_id' => $eventId,
                'result' => 'duplicate_event',
            ]);

            return [
                'result' => 'duplicate_event',
                'order' => $existing,
                'is_new' => false,
                'status_changed' => false,
                'fulfillment_transition' => false,
                'delivered_transition' => false,
                'inventory_deducted' => false,
            ];
        }

        $customer = data_get($data, 'customer', []);
        $lineItems = data_get($data, 'line_items', []);
        $newFulfillmentStatus = data_get($data, 'fulfillment_status');
        $fulfillments = data_get($data, 'fulfillments', []);
        $newShipmentStatus = is_array($fulfillments) ? $this->resolveAggregateShipmentStatus($fulfillments) : null;

        $syncResult = DB::transaction(function () use (
            $shop,
            $data,
            $orderId,
            $action,
            $eventId,
            $webhookId,
            $customer,
            $lineItems,
            $newFulfillmentStatus,
            $newShipmentStatus
        ) {
            // Find existing order using composite key (shop_id, shopify_order_id) under lock
            $order = ShopifyOrder::where('shop_id', $shop->id)
                ->where('shopify_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            $isNew = false;

            if (!$order) {
                try {
                    $order = ShopifyOrder::create([
                        'shop_id' => $shop->id,
                        'shopify_order_id' => $orderId,
                        'admin_graphql_api_id' => data_get($data, 'admin_graphql_api_id'),
                        'shopify_event_id' => $eventId,
                        'shopify_webhook_id' => $webhookId,
                        'order_number' => data_get($data, 'order_number'),
                        'name' => data_get($data, 'name'),
                        'email' => data_get($data, 'email'),
                        'customer_first_name' => data_get($customer, 'first_name'),
                        'customer_last_name' => data_get($customer, 'last_name'),
                        'customer_phone' => data_get($customer, 'phone'),
                        'phone' => data_get($data, 'phone'),
                        'financial_status' => data_get($data, 'financial_status'),
                        'fulfillment_status' => $newFulfillmentStatus,
                        'shipment_status' => $newShipmentStatus,
                        'currency' => data_get($data, 'currency'),
                        'subtotal_price' => (float) data_get($data, 'subtotal_price', 0),
                        'total_tax' => (float) data_get($data, 'total_tax', 0),
                        'total_discounts' => (float) data_get($data, 'total_discounts', 0),
                        'total_price' => (float) data_get($data, 'total_price', 0),
                        'line_items_count' => is_array($lineItems) ? count($lineItems) : 0,
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
                        'raw_payload' => $data,
                        'order_created_at' => $this->parseNullableDate(data_get($data, 'created_at')),
                        'processed_at' => $this->parseNullableDate(data_get($data, 'processed_at')),
                        'cancelled_at' => $this->parseNullableDate(data_get($data, 'cancelled_at')),
                    ]);
                    $isNew = true;
                } catch (UniqueConstraintViolationException|QueryException $e) {
                    // Race condition: another thread inserted this order concurrently.
                    // Recover gracefully by re-fetching under row lock.
                    Log::warning('Handled concurrency race on order insertion (duplicate key caught).', [
                        'shop_id' => $shop->id,
                        'shopify_order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);

                    $order = ShopifyOrder::where('shop_id', $shop->id)
                        ->where('shopify_order_id', $orderId)
                        ->lockForUpdate()
                        ->first();

                    if (!$order) {
                        throw $e;
                    }
                    $isNew = false;
                }
            }

            if ($isNew) {
                // Deduct Amazon inventory ONLY on genuine first creation
                $this->deductAmazonInventory($shop, $order, is_array($lineItems) ? $lineItems : []);

                return [
                    'result' => 'created',
                    'order' => $order,
                    'is_new' => true,
                    'status_changed' => false,
                    'fulfillment_transition' => false,
                    'delivered_transition' => false,
                    'inventory_deducted' => true,
                ];
            }

            // Existing order: compare meaningful fields to prevent false / redundant updates
            $oldFulfillmentStatus = $order->fulfillment_status;
            $oldShipmentStatus = $order->shipment_status;
            $oldFinancialStatus = $order->financial_status;

            $hasChanges = $this->hasMeaningfulChanges(
                $order,
                $data,
                $newFulfillmentStatus,
                $newShipmentStatus
            );

            if ($hasChanges) {
                $order->update([
                    'admin_graphql_api_id' => data_get($data, 'admin_graphql_api_id', $order->admin_graphql_api_id),
                    'shopify_event_id' => $eventId ?: $order->shopify_event_id,
                    'shopify_webhook_id' => $webhookId ?: $order->shopify_webhook_id,
                    'order_number' => data_get($data, 'order_number', $order->order_number),
                    'name' => data_get($data, 'name', $order->name),
                    'email' => data_get($data, 'email', $order->email),
                    'customer_first_name' => data_get($customer, 'first_name', $order->customer_first_name),
                    'customer_last_name' => data_get($customer, 'last_name', $order->customer_last_name),
                    'customer_phone' => data_get($customer, 'phone', $order->customer_phone),
                    'phone' => data_get($data, 'phone', $order->phone),
                    'financial_status' => data_get($data, 'financial_status', $order->financial_status),
                    'fulfillment_status' => $newFulfillmentStatus,
                    'shipment_status' => $newShipmentStatus,
                    'currency' => data_get($data, 'currency', $order->currency),
                    'subtotal_price' => (float) data_get($data, 'subtotal_price', $order->subtotal_price),
                    'total_tax' => (float) data_get($data, 'total_tax', $order->total_tax),
                    'total_discounts' => (float) data_get($data, 'total_discounts', $order->total_discounts),
                    'total_price' => (float) data_get($data, 'total_price', $order->total_price),
                    'line_items_count' => is_array($lineItems) ? count($lineItems) : $order->line_items_count,
                    'source_name' => data_get($data, 'source_name', $order->source_name),
                    'tags' => data_get($data, 'tags', $order->tags),
                    'note' => data_get($data, 'note', $order->note),
                    'customer' => $customer ?: $order->customer,
                    'billing_address' => data_get($data, 'billing_address', $order->billing_address),
                    'shipping_address' => data_get($data, 'shipping_address', $order->shipping_address),
                    'line_items' => $lineItems ?: $order->line_items,
                    'discount_codes' => data_get($data, 'discount_codes', $order->discount_codes),
                    'shipping_lines' => data_get($data, 'shipping_lines', $order->shipping_lines),
                    'tax_lines' => data_get($data, 'tax_lines', $order->tax_lines),
                    'raw_payload' => $data,
                    'order_created_at' => $this->parseNullableDate(data_get($data, 'created_at')) ?: $order->order_created_at,
                    'processed_at' => $this->parseNullableDate(data_get($data, 'processed_at')) ?: $order->processed_at,
                    'cancelled_at' => $this->parseNullableDate(data_get($data, 'cancelled_at')) ?: $order->cancelled_at,
                ]);

                $isFulfilledTransition = ($oldFulfillmentStatus !== 'fulfilled' && $newFulfillmentStatus === 'fulfilled');
                $isDeliveredTransition = ($oldShipmentStatus !== 'delivered' && $newShipmentStatus === 'delivered');
                $isFinancialChanged = ($oldFinancialStatus !== data_get($data, 'financial_status'));

                return [
                    'result' => 'updated',
                    'order' => $order,
                    'is_new' => false,
                    'status_changed' => $isFulfilledTransition || $isDeliveredTransition || $isFinancialChanged,
                    'fulfillment_transition' => $isFulfilledTransition,
                    'delivered_transition' => $isDeliveredTransition,
                    'inventory_deducted' => false,
                ];
            }

            // Meaningful data is unchanged; touch event ID metadata if different
            if ($eventId !== null && $order->shopify_event_id !== $eventId) {
                $order->update(['shopify_event_id' => $eventId]);
            }

            return [
                'result' => 'unchanged',
                'order' => $order,
                'is_new' => false,
                'status_changed' => false,
                'fulfillment_transition' => false,
                'delivered_transition' => false,
                'inventory_deducted' => false,
            ];
        }, 3);

        Log::info('Shopify order sync completed.', [
            'shop_id' => $shop->id,
            'shopify_order_id' => $orderId,
            'result' => $syncResult['result'],
            'is_new' => $syncResult['is_new'],
            'inventory_deducted' => $syncResult['inventory_deducted'],
        ]);

        return $syncResult;
    }

    /**
     * Check if incoming payload contains meaningful data changes compared to database record.
     */
    public function hasMeaningfulChanges(
        ShopifyOrder $order,
        array $data,
        ?string $newFulfillmentStatus,
        ?string $newShipmentStatus
    ): bool {
        if ($order->fulfillment_status !== $newFulfillmentStatus) {
            return true;
        }

        if ($order->shipment_status !== $newShipmentStatus) {
            return true;
        }

        $incomingFinancial = data_get($data, 'financial_status');
        if ($incomingFinancial !== null && $order->financial_status !== $incomingFinancial) {
            return true;
        }

        $incomingCancelledAt = $this->parseNullableDate(data_get($data, 'cancelled_at'));
        if ($incomingCancelledAt?->toDateTimeString() !== $order->cancelled_at?->toDateTimeString()) {
            return true;
        }

        $incomingTotalPrice = (float) data_get($data, 'total_price', 0);
        if (abs((float) $order->total_price - $incomingTotalPrice) > 0.001) {
            return true;
        }

        $incomingSubtotal = (float) data_get($data, 'subtotal_price', 0);
        if (abs((float) $order->subtotal_price - $incomingSubtotal) > 0.001) {
            return true;
        }

        $incomingTax = (float) data_get($data, 'total_tax', 0);
        if (abs((float) $order->total_tax - $incomingTax) > 0.001) {
            return true;
        }

        $incomingDiscounts = (float) data_get($data, 'total_discounts', 0);
        if (abs((float) $order->total_discounts - $incomingDiscounts) > 0.001) {
            return true;
        }

        $incomingEmail = data_get($data, 'email');
        if ($incomingEmail !== null && $order->email !== $incomingEmail) {
            return true;
        }

        $incomingLineItems = data_get($data, 'line_items');
        if (is_array($incomingLineItems)) {
            $existingLineItems = is_array($order->line_items) ? $order->line_items : json_decode($order->line_items ?? '[]', true);
            if ($this->normalizeArrayForComparison($existingLineItems) !== $this->normalizeArrayForComparison($incomingLineItems)) {
                return true;
            }
        }

        $incomingShippingAddress = data_get($data, 'shipping_address');
        if (is_array($incomingShippingAddress)) {
            $existingShipping = is_array($order->shipping_address) ? $order->shipping_address : json_decode($order->shipping_address ?? '[]', true);
            if ($this->normalizeArrayForComparison($existingShipping) !== $this->normalizeArrayForComparison($incomingShippingAddress)) {
                return true;
            }
        }

        $incomingBillingAddress = data_get($data, 'billing_address');
        if (is_array($incomingBillingAddress)) {
            $existingBilling = is_array($order->billing_address) ? $order->billing_address : json_decode($order->billing_address ?? '[]', true);
            if ($this->normalizeArrayForComparison($existingBilling) !== $this->normalizeArrayForComparison($incomingBillingAddress)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deduct Amazon inventory for mapped line items upon initial order creation.
     */
    public function deductAmazonInventory(Shop $shop, ShopifyOrder $order, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            $variantId = $item['variant_id'] ?? null;
            $orderedQty = $item['quantity'] ?? 0;

            if (!$variantId) {
                continue;
            }

            $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
                ->where('shopify_variant_id', (string) $variantId)
                ->first();

            if (!$mapping) {
                Log::info('No marketplace mapping found.', [
                    'shop_id' => $shop->id,
                    'variant_id' => $variantId,
                ]);
                continue;
            }

            if ($mapping->quantity === null || $mapping->quantity === '') {
                Log::info('Skipping Amazon inventory sync: mapping quantity is unknown/null.', [
                    'shop_id' => $shop->id,
                    'variant_id' => $variantId,
                    'amazon_sku' => $mapping->amazon_sku,
                ]);
                continue;
            }

            $newShopifyQuantity = ((int) $mapping->quantity) - ((int) $orderedQty);
            $amazonTargetQuantity = max(0, $newShopifyQuantity);

            try {
                $this->amazonService->updateInventory(
                    $shop,
                    $mapping->amazon_sku,
                    $amazonTargetQuantity,
                    false,
                    $newShopifyQuantity
                );
            } catch (Throwable $e) {
                Log::error('Webhook inventory sync failed.', [
                    'variant_id' => $variantId,
                    'amazon_sku' => $mapping->amazon_sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Aggregate fulfillment shipment statuses into a prioritized status.
     */
    public function resolveAggregateShipmentStatus(array $fulfillments): ?string
    {
        $activeFulfillments = array_values(array_filter($fulfillments, function ($f) {
            return is_array($f) && ($f['status'] ?? '') !== 'cancelled';
        }));

        if (empty($activeFulfillments)) {
            return null;
        }

        $shipmentStatuses = array_map(function ($f) {
            return $f['shipment_status'] ?? null;
        }, $activeFulfillments);

        $nonNullStatuses = array_values(array_filter($shipmentStatuses));

        if (empty($nonNullStatuses)) {
            return null;
        }

        // If all active fulfillments are delivered, aggregate status is delivered
        if (count($nonNullStatuses) === count($activeFulfillments) && collect($nonNullStatuses)->every(fn($s) => $s === 'delivered')) {
            return 'delivered';
        }

        $priorityOrder = [
            'out_for_delivery',
            'in_transit',
            'attempted_delivery',
            'failure',
            'delivered',
            'ready_for_pickup',
            'label_printed',
            'label_purchased',
            'confirmed',
        ];

        foreach ($priorityOrder as $priority) {
            if (in_array($priority, $nonNullStatuses, true)) {
                return $priority;
            }
        }

        return $nonNullStatuses[0] ?? null;
    }

    public function parseNullableDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function normalizeArrayForComparison(?array $arr): string
    {
        if (empty($arr)) {
            return '';
        }

        // Sort keys recursively for deterministic JSON representation
        $this->ksortRecursive($arr);

        return json_encode($arr);
    }

    protected function ksortRecursive(array &$arr): void
    {
        ksort($arr);
        foreach ($arr as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }
}
