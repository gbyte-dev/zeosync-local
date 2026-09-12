<?php
namespace App\Services\Webhooks;
use App\Models\AllProduct;
use App\Models\ComplianceRequest;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class ShopifyComplianceProcessor
{
    public function process(ComplianceRequest $request): void
    {
        \Illuminate\Support\Facades\Cache::lock('privacy:shop:'.($request->shop_id ?? 0), 300)->block(5, function () use ($request) {
            $this->processLocked($request);
        });
    }

    private function processLocked(ComplianceRequest $request): void
    {
        $request->refresh();
        if (in_array($request->status, ['completed','awaiting_delivery','revoked'], true)) { return; }
        $payload = $request->request_payload ?: [];
        $shop = $request->shop_id ? Shop::withTrashed()->find($request->shop_id) : null;
        if ($request->type !== 'shop_redact' && ! $shop) { throw new \RuntimeException('Shop no longer exists for compliance request.'); }
        match ($request->type) {
            'customers_data_request' => $this->dataRequest($request, $shop, $payload),
            'customers_redact' => $this->customerRedact($request, $shop, $payload),
            'shop_redact' => $this->shopRedact($request, $shop),
            default => throw new \InvalidArgumentException('Unsupported compliance request type.'),
        };
    }
    private function dataRequest(ComplianceRequest $request, Shop $shop, array $payload): void
    {
        $orders = $this->matchingOrders($shop, $payload)->map(fn (ShopifyOrder $order) => [
            'shopify_order_id' => $order->shopify_order_id, 'order_number' => $order->order_number,
            'name' => $order->name, 'email' => $order->email,
            'customer_first_name' => $order->customer_first_name, 'customer_last_name' => $order->customer_last_name,
            'customer_phone' => $order->customer_phone, 'phone' => $order->phone,
            'billing_address' => $order->billing_address, 'shipping_address' => $order->shipping_address,
            'customer' => $order->customer, 'line_items' => $order->line_items,
            'total_price' => $order->total_price, 'currency' => $order->currency,
            'order_created_at' => optional($order->order_created_at)->toIso8601String(),
        ])->values()->all();
        $request->update(['status' => 'awaiting_delivery', 'result_payload' => ['shop' => $shop->shop, 'customer' => $payload['customer'] ?? null, 'orders' => $orders, 'generated_at' => now()->toIso8601String()], 'completed_at' => now(), 'purge_after' => now()->addDays(30), 'error' => null]);
    }
    private function customerRedact(ComplianceRequest $request, Shop $shop, array $payload): void
    {
        $privacy = app(\App\Services\OrderPrivacyService::class);
        $orders = $this->matchingOrders($shop, $payload);
        DB::transaction(function () use ($privacy, $payload, $orders, $shop, $request) {
            $privacy->remember($shop->id, $payload);
            foreach ($orders as $order) {
                $privacy->put($shop->id, 'order', (string) $order->shopify_order_id);
                $safe = $privacy->operational($order->raw_payload ?: ['id' => $order->shopify_order_id, 'line_items' => $order->line_items ?: []]);
                $order->update(array_fill_keys(['email','customer_first_name','customer_last_name','customer_phone','phone','customer','billing_address','shipping_address'], null) + ['line_items' => $safe['line_items'], 'raw_payload' => $safe]);
            }
            \App\Models\WebhookEvent::where('shop_id', $shop->id)->where('provider', 'shopify')->eachById(function ($event) use ($privacy, $shop) {
                $event->update(['payload' => $privacy->filter($shop->id, $event->payload ?: []), 'error' => null]);
            });
            ComplianceRequest::where('shop_id', $shop->id)->where('id', '!=', $request->id)->whereIn('status', ['completed','awaiting_delivery'])->update(['request_payload' => null, 'result_payload' => null, 'status' => 'revoked']);
            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('compliance/'.$shop->id);
            $request->update(['status' => 'completed', 'request_payload' => null, 'result_payload' => ['redacted_orders' => $orders->count()], 'completed_at' => now(), 'purge_after' => now()->addDays(30), 'error' => null]);
        }, 3);
    }
    private function shopRedact(ComplianceRequest $request, ?Shop $shop): void
    {
        \Illuminate\Support\Facades\Cache::lock(\App\Services\InventoryOperationService::lockKey($shop?->id ?? 0), 300)->block(5, fn () => $this->eraseShop($request, $shop?->fresh()));
    }
    private function eraseShop(ComplianceRequest $request, ?Shop $shop): void
    {
        if (! $shop) { $request->update(['status' => 'completed', 'completed_at' => now(), 'purge_after' => now()->addDays(30)]); return; }
        if ($shop->is_active) { $request->update(['status' => 'review_required', 'error' => 'Active installation: verify lifecycle before erasure.']); return; }
        $shopId = $shop->id;
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('compliance/'.$shopId);
        $shop->forceFill(['access_token' => '', 'refresh_token' => null, 'amazon_refresh_token' => null, 'is_active' => false, 'store_status' => 'redacting'])->save();
        DB::transaction(function () use ($shop, $shopId) {
            foreach (['product_sync_logs','user_notifications'] as $table) {
                if (Schema::hasTable($table)) { DB::table($table)->where('shop_id', $shopId)->delete(); }
            }
            \App\Models\WebhookEvent::where('shop_id', $shopId)->update(['payload' => null, 'error' => null, 'status' => 'processed']);
            ComplianceRequest::where('shop_id', $shopId)->update(['request_payload' => null, 'result_payload' => null, 'error' => null]);
            $shop->forceDelete();
        }, 3);
        $request->refresh()->update(['shop_id' => null, 'request_payload' => null, 'status' => 'completed', 'completed_at' => now(), 'purge_after' => now()->addDays(30)]);
    }
    private function matchingOrders(Shop $shop, array $payload)
    {
        $query = ShopifyOrder::where('shop_id', $shop->id);
        $customerId = (string) data_get($payload, 'customer.id', '');
        $email = strtolower(trim((string) data_get($payload, 'customer.email', '')));
        return $query->get()->filter(function (ShopifyOrder $order) use ($customerId, $email) {
            $oid = (string) data_get($order->customer, 'id', '');
            $oemail = strtolower(trim((string) ($order->email ?: data_get($order->customer, 'email', ''))));
            return ($customerId !== '' && $oid === $customerId) || ($email !== '' && $oemail === $email);
        })->values();
    }
}

