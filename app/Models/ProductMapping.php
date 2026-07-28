<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shop;

class ProductMapping extends Model
{
     use HasFactory;

    protected $table = 'product_marketplace_mappings';

    protected $fillable = [
        // Shop
        'shop_id',
        'shop_name',

        // Local Product
        'product_id',
        'variant_id',

        // Shopify
        'shopify_product_id',
        'shopify_variant_id',
        'shopify_inventory_item_id',

        // Amazon
        'amazon_sku',
        'amazon_parent_sku',
        'amazon_asin',
        'amazon_parent_asin',
        'amazon_marketplace_id',
        'amazon_product_type',
        'quantity',

        // Sync
        'sync_status',
        'submission_status',
        'submission_id',
        'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->shop_name ??= session('active_shop');

                if (!$model->shop_id && $model->shop_name) {
                    $shop = Shop::where('name', $model->shop_name)->first();

                    if ($shop) {
                        $model->shop_id = $shop->id;
                    }
                }
            }
        });
    }
}
