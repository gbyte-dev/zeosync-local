<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class OrderPrivacyService
{
    public function digest(string $value): string { return hash_hmac('sha256', strtolower(trim($value)), config('app.key')); }
    public function remember(int $shopId, array $payload): void
    {
        $values = ['customer'=>data_get($payload,'customer.id'),'email'=>data_get($payload,'customer.email')];
        foreach (($payload['orders_to_redact'] ?? []) as $id) { $this->put($shopId,'order',(string)$id); }
        foreach ($values as $kind=>$value) { if (filled($value)) { $this->put($shopId,$kind,(string)$value); } }
    }
    public function put(int $shopId, string $kind, string $value): void
    {
        DB::table('privacy_tombstones')->updateOrInsert(['shop_id'=>$shopId,'kind'=>$kind,'digest'=>$this->digest($value)],['created_at'=>now(),'updated_at'=>now()]);
    }
    public function filter(int $shopId, array $data): array
    {
        foreach (['order'=>$data['id'] ?? null,'customer'=>data_get($data,'customer.id'),'email'=>$data['email'] ?? data_get($data,'customer.email')] as $kind=>$value) {
            if (filled($value) && DB::table('privacy_tombstones')->where(['shop_id'=>$shopId,'kind'=>$kind,'digest'=>$this->digest((string)$value)])->exists()) { return $this->operational($data); }
        }
        return $data;
    }
    public function operational(array $data): array
    {
        $clean = array_intersect_key($data, array_flip(['id','order_number','financial_status','fulfillment_status','currency','subtotal_price','total_tax','total_discounts','total_price','created_at','processed_at','cancelled_at']));
        $clean['line_items'] = array_map(fn ($item) => array_intersect_key($item, array_flip(['id','product_id','variant_id','quantity','price'])), array_filter($data['line_items'] ?? [], 'is_array'));
        return $clean;
    }
}
