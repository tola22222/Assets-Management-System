<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = [
        'stock_code', 'name', 'category', 'unit', 'balance',
        'min_threshold', 'max_threshold', 'location_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'min_threshold' => 'decimal:2',
        'max_threshold' => 'decimal:2',
    ];

    protected $appends = ['status'];

    /**
     * LOW/NORMAL/HIGH is always computed live from balance vs thresholds —
     * never stored — so it can't drift out of sync after a Stock-In/Out.
     */
    public function getStatusAttribute(): string
    {
        if ($this->min_threshold !== null && $this->balance <= $this->min_threshold) {
            return 'low';
        }
        if ($this->max_threshold !== null && $this->balance >= $this->max_threshold) {
            return 'high';
        }

        return 'normal';
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function transactions()
    {
        return $this->hasMany(StockTransaction::class);
    }
}
