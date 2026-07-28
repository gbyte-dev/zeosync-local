<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifySubscription extends Model
{
    protected $fillable = [
        'shop_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'stripe_product_id',
        'status',
        'current_period_start',
        'current_period_end',
        'trial_start',
        'trial_end',
        'cancel_at',
        'canceled_at',
        'cancel_reason',
        'canceled_by',
        'payment_status',
        'quantity',
    ];
    
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
