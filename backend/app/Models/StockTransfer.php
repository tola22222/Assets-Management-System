<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        'transferred_by',
        'transfer_date',
        'status',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function items() {
        return $this->hasMany(StockTransferItem::class);
    }
}
