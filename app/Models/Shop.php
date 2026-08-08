<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Shop extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    use SoftDeletes;

    protected $fillable = [
        'shop',
        'access_token',
        'access_token_expires_at',
        'refresh_token',
        'refresh_token_expires_at',
        'shopify_connection_status',
        'shop_name',
        'email',
        'domain',
        'installed_at',
        'is_active',
        'plan',
        'plan_expires_at',
        'amazon_seller_id',
        'amazon_mws_region',
        'amazon_refresh_token',
        'amazon_marketplace_id',
        'amazon_endpoint',
        'stripe_customer_id',
        'amazon_oauth_state',
        'hmac',
        'store_status',
        'last_status_check_at',
    ];

    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_status_check_at' => 'datetime',
    ];

    /**
     * Get the products for the shop.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }


    public function orders(): HasMany
    {
        return $this->hasMany(ShopifyOrder::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(ShopSubscription::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(Setting::class);
    }

    public function shopifySubscriptions(): HasMany
    {
        return $this->hasMany(ShopifySubscription::class);
    }

    public function getidByshop($shop)
    {
        return $this->where('shop', $shop)->value('id');
    }
    public function needsStatusCheck(): bool
    {
        if (empty($this->last_status_check_at)) {
            return true;
        }

        return Carbon::parse($this->last_status_check_at)
            ->lte(now()->subHours(24));
    }
}
