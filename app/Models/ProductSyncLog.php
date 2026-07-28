<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSyncLog extends Model
{
    protected $table = 'product_sync_logs';

    protected $fillable = [
        'product_id',
        'shop_id',
        'platform',
        'status',
        'error_message',
        'message',
        'type'
    ];


}