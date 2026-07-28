<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMarketplaceMapping extends Model
{
    protected $table = 'product_marketplace_mappings';

    protected $fillable = [
        'shop_id',
        'product_id',
        'variant_id',
        'shopify_product_id',
        'shopify_variant_id',
        'shopify_inventory_item_id',
        'amazon_sku',
        'amazon_parent_sku',
        'amazon_asin',
        'amazon_parent_asin',
        'amazon_marketplace_id',
        'amazon_product_type',
        'quantity',
        'sync_status',
        'submission_status',
        'submission_id',
        'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
