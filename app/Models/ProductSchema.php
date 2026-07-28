<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ProductSchema extends Model
{
    use SoftDeletes;

    protected $table = 'product_schemas';
    protected $fillable = [
        'product_type',
        'schema_version',
        'schema_json',
        'parsed_json',
        'is_active',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'parsed_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeWithRequiredColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'product_type',
            'schema_version',
            'is_active',
        ]);
    }

    public function products(): HasMany
    {
        return $this->hasMany(AllProduct::class, 'schema_id');
    }
}