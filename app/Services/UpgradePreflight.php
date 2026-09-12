<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class UpgradePreflight
{
    public function conflicts(): array
    {
        $results = [];
        $pairs = [
            ['product_marketplace_mappings',['shop_id','shopify_variant_id']],
            ['product_marketplace_mappings',['shop_id','amazon_marketplace_id','amazon_sku']],
            ['shopify_orders',['shop_id','shopify_order_id']],
            ['shopify_orders',['shopify_event_id']],
        ];
        foreach ($pairs as [$table,$columns]) {
            if (!Schema::hasTable($table)) { continue; }
            $missing = array_filter($columns, fn($c) => !Schema::hasColumn($table,$c));
            if ($missing) { continue; }
            $q = DB::table($table)->select($columns)->selectRaw('COUNT(*) as records');
            foreach ($columns as $c) { $q->whereNotNull($c); }
            if ($table === 'product_marketplace_mappings') {
                $q->where(function ($qq) {
                    $qq->whereNull('shopify_variant_id')->orWhere('shopify_variant_id','!=','');
                });
            }
            $rows = $q->groupBy($columns)->havingRaw('COUNT(*) > 1')->get()->all();
            if ($rows) { $results[$table.':'.implode(',',$columns)] = $rows; }
        }
        if (Schema::hasTable('product_marketplace_mappings')) {
            foreach (['shop_id'=>'shops','product_id'=>'products'] as $col => $target) {
                if (!Schema::hasColumn('product_marketplace_mappings',$col) || !Schema::hasTable($target)) { continue; }
                $ids = DB::table('product_marketplace_mappings')->whereNotNull($col)
                    ->whereNotIn($col, DB::table($target)->select('id'))->pluck('id')->all();
                if ($ids) { $results['orphan:'.$col] = $ids; }
            }
        }
        return $results;
    }
    public function assertSafe(): void
    {
        if ($this->conflicts()) {
            throw new \RuntimeException('Upgrade stopped without deleting records. Run php artisan release:preflight and reconcile conflicts from a backed-up database.');
        }
    }
}
