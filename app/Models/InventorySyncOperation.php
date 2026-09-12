<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventorySyncOperation extends Model
{
    protected $fillable = [
        'shop_id','webhook_event_id','mapping_id','source_key','sku',
        'source','source_state','inventory_item_id','location_id','marketplace_id',
        'submitted_at','next_attempt_at','delta','requested_quantity',
        'observed_quantity','desired_quantity','status','attempts','error','processed_at',
    ];
    protected $casts = [
        'submitted_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'delta' => 'integer',
        'requested_quantity' => 'integer',
        'observed_quantity' => 'integer',
        'desired_quantity' => 'integer',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];
}
