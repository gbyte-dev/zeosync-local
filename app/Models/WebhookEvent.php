<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookEvent extends Model
{
    protected $fillable = [
        'provider','event_id','topic','shop_id','status','attempts',
        'payload','error','received_at','processed_at',
    ];
    protected $casts = [
        'payload' => 'encrypted:array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
