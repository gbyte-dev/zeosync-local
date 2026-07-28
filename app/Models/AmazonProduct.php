<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonProduct extends Model
{
    protected $fillable = [
        'product_id',
        'amazon_title',
        'search_terms',
        'platinum_keywords',
        'bullet_points',
        'target_audience',
        'subject_matter',
        'sku',
        'smart_payload',
        'intended_use',
    ];

    protected $casts = [
        'search_terms' => 'array',
        'platinum_keywords' => 'array',
        'bullet_points' => 'array',
        'target_audience' => 'array',
        'subject_matter' => 'array',
        'intended_use' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
