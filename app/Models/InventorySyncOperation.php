<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySyncOperation extends Model
{
    use HasFactory;

    protected $table = 'inventory_sync_operations';

    protected $fillable = [
        'operation_uuid',
        'shop_id',
        'mapping_id',
        'shopify_inventory_item_id',
        'shopify_location_id',
        'amazon_sku',
        'desired_quantity',
        'baseline_quantity',
        'expected_inventory_version',
        'source',
        'status',
        'stage',
        'attempts',
        'max_attempts',
        'last_error',
        'created_by',
        'last_dispatched_at',
        'processing_started_at',
        'completed_at',
    ];

    protected $casts = [
        'shop_id'                    => 'integer',
        'mapping_id'                 => 'integer',
        'desired_quantity'           => 'integer',
        'baseline_quantity'          => 'integer',
        'expected_inventory_version' => 'integer',
        'attempts'                   => 'integer',
        'max_attempts'               => 'integer',
        'created_by'                 => 'integer',
        'last_dispatched_at'         => 'datetime',
        'processing_started_at'      => 'datetime',
        'completed_at'               => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ProductMarketplaceMapping::class, 'mapping_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isAwaitingVerification(): bool
    {
        return in_array($this->status, ['accepted', 'awaiting_verification'], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSuperseded(): bool
    {
        return in_array($this->status, ['superseded', 'stale_external_state'], true);
    }

    public function isStale(): bool
    {
        return in_array($this->status, ['superseded', 'stale_external_state'], true);
    }

}
