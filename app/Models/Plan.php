<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'price',
        'badge',
        'description',
        'features',
        'is_highlighted',
        'is_active',
        'sort_order',
        'stripe_price_ids',
        'trial_days',
        'is_trial',
        'prices',
        'sync_limit',
        'product_limit',
        'ai_autofill',
        'ai_single_field',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_highlighted' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'stripe_price_ids' => 'array',
        'prices' => 'array',
        'ai_autofill'     => 'boolean',
        'ai_single_field' => 'boolean',
    ];


    public function subscriptions(): HasMany
    {
        return $this->hasMany(ShopSubscription::class);
    }
}
