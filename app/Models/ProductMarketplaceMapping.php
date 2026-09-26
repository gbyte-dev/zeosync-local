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
        'shopify_location_id',
        'amazon_sku',
        'amazon_parent_sku',
        'amazon_asin',
        'amazon_parent_asin',
        'amazon_marketplace_id',
        'amazon_product_type',
        'fulfillment_channel_code',
        'quantity',
        'inventory_version',
        'sync_status',
        'submission_status',
        'submission_id',
        'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'inventory_version' => 'integer',
        'last_synced_at'    => 'datetime',
    ];

    /**
     * Relationship with the Product model.
     */
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Scope a query to only include valid visible mapped products for a given shop.
     * Criteria: shop_id matches, and both amazon_sku and shopify_variant_id are non-null and non-empty.
     */
    public function scopeMappedForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId)
            ->whereNotNull('amazon_sku')
            ->where('amazon_sku', '!=', '')
            ->whereNotNull('shopify_variant_id')
            ->where('shopify_variant_id', '!=', '');
    }

    /**
     * Get the exact visible mapped products count for a given shop.
     */
    public static function getMappedCountForShop(int $shopId): int
    {
        return static::mappedForShop($shopId)->count();
    }
}
