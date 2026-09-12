<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ComplianceRequest extends Model
{
    protected $fillable = [
        'event_id','shop_id','shop_domain_hash','type','customer_id',
        'customer_email_hash','status','request_payload','result_payload',
        'error','completed_at','purge_after',
    ];
    protected $casts = [
        'request_payload' => 'encrypted:array',
        'result_payload' => 'encrypted:array',
        'completed_at' => 'datetime',
        'purge_after' => 'datetime',
    ];
}
