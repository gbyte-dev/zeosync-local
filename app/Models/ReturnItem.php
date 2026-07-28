<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnItem extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'shop_id',
        'order_id',
        'product_name',
        'status',
        'refund_amount',
        'reason',
        'notes',
    ];

    // Relationships
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}