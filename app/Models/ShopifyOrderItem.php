<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShopifyOrderItem extends Model
{
    protected $fillable = [
        'shop_id','shopify_order_id','source_line_key','shopify_product_id',
        'shopify_variant_id','sku','title','quantity','unit_price',
        'line_total','order_created_at',
    ];
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
        'order_created_at' => 'datetime',
    ];
}
