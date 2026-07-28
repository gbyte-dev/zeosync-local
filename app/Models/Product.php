<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Product extends Model
{
    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shopify_id',
        'title',
        'description',
        'price',
        'status',
        'shopify_status',
        'shopify_error',
        'product_type',
        'vendor',
        'tags',
        'category',
        'collections',
        'images',
        'variants',
        'options',
        'metafields',
        'shop_id',
        'synced_to_amazon',
        'needs_resync',
        'category_id',
        'sub_category_id',
        'local_images',
        'amazon_product_id'
    ];
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'images' => 'array',
        'variants' => 'array',
        'options' => 'array',
        'metafields' => 'array',
        'price' => 'decimal:2',
    ];
    /**
     * Get the shop that owns the product.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
    public function amazonProduct(): HasOne
    {
        return $this->hasOne(AmazonProduct::class);
    }
}
