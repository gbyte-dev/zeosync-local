<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSubscription extends Model
{
    protected $table = 'shop_subscriptions';

    protected $fillable = [
        'shop_id',
        'plan_id',
        'shopify_subscription_gid',
        'shopify_confirmation_url',
        'shopify_return_url',
        'status',
        'price',
        'billing_cycle_months',
        'billing_interval',
        'currency_code',
        'trial_days',
        'is_test',
        'trial_ends_at',
        'started_at',
        'activated_at',
        'current_period_end',
        'ended_at',
        'cancelled_at',
        'is_trial',
        'trial_used',
        'requested_plan_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'billing_cycle_months' => 'integer',
        'trial_days' => 'integer',
        'is_test' => 'boolean',
        'trial_ends_at' => 'datetime',
        'started_at' => 'datetime',
        'activated_at' => 'datetime',
        'current_period_end' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }
}
