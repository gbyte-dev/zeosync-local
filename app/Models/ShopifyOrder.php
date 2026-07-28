<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyOrder extends Model
{
    protected $table = 'shopify_orders';

    protected $fillable = [
        'shop_id',
        'shopify_order_id',
        'admin_graphql_api_id',
        'shopify_event_id',
        'shopify_webhook_id',
        'order_number',
        'name',
        'email',
        'customer_first_name',
        'customer_last_name',
        'customer_phone',
        'phone',
        'financial_status',
        'fulfillment_status',
        'currency',
        'subtotal_price',
        'total_tax',
        'total_discounts',
        'total_price',
        'line_items_count',
        'source_name',
        'tags',
        'note',
        'customer',
        'billing_address',
        'shipping_address',
        'line_items',
        'discount_codes',
        'shipping_lines',
        'tax_lines',
        'raw_payload',
        'order_created_at',
        'processed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal_price' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_discounts' => 'decimal:2',
        'total_price' => 'decimal:2',
        'line_items_count' => 'integer',
        'customer' => 'array',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'line_items' => 'array',
        'discount_codes' => 'array',
        'shipping_lines' => 'array',
        'tax_lines' => 'array',
        'raw_payload' => 'array',
        'order_created_at' => 'datetime',
        'processed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function getCustomerNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->customer_first_name,
            $this->customer_last_name,
        ])));
    }
}
