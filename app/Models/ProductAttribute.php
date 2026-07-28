<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttribute extends Model
{
    protected $table = 'product_attributes';

    protected $fillable = [
        'product_id',
        'attribute_name',
        'attribute_value',
    ];

    /**
     * Get the product that owns the attribute.
     */
    public function product(): BelongsTo
    {
        // We must provide 'product_id' and the 'allproducts' table's owner key
        return $this->belongsTo(AllProduct::class, 'product_id');
    }
}