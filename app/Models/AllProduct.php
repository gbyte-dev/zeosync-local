<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllProduct extends Model
{
    protected $table = 'allproducts';

    protected $fillable = [
        'schema_id',
        'sku',
        'status',
        'parent_id',
        'submission_status',
        'submitted_on',
        'user_id',
        'final_json',
        'filled_json'
    ];

    /**
     * Get the schema that owns the product.
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(ProductSchema::class, 'schema_id');
    }

    /**
     * Get the attributes for the product.
     */
    public function attributes(): HasMany
    {
        // Explicitly defining the foreign key 'product_id' 
        return $this->hasMany(ProductAttribute::class, 'product_id');
    }
}