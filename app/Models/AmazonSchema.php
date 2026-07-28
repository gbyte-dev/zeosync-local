<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonSchema extends Model
{
    protected $fillable = [
    
        'category_slug',
        'schema_json',
        'rules_json',
        'last_synced_at'
    ];

    protected $casts = [

        'last_synced_at' => 'datetime'
    ];
}