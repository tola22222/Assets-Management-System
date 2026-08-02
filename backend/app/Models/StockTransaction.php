<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $fillable = [
        'stock_item_id', 'type', 'quantity', 'reason', 'transaction_date', 'recorded_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
