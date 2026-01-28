<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        'school_id',
        'transferred_by',
        'transfer_date',
        'status',
    ];
    public function school() {
        return $this->belongsTo(School::class);
    }

    public function user() {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function items() {
        return $this->hasMany(StockTransferItem::class);
    }
}
