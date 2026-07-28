<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Setting extends Model
{
    protected $fillable = [
        'shop_id',
        'auto_sync',
        'auto_sku_mapping',
        'ai_assist',
        'currency',
        'tax_behavior',
        'ai_client_id',
        'ai_client_secret'
    ];


    protected $casts = [
        'auto_sync' => 'boolean',
        'auto_sku_mapping' => 'boolean',
        'ai_assist' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
