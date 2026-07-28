<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    protected $fillable = [
        'shop',
        'access_token',
        'shop_name',
        'email',
        'domain',
        'installed_at',
        'is_active',
        'plan',
        'plan_expires_at',
        'hmac'
    ];

    protected $table = 'shops';
    // 🔹 Optional: auto-cast
    protected $casts = [
        'installed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

        /**
     * Get the products for the shop.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function settings()
    {
        return $this->hasOne(Setting::class);
    }
}