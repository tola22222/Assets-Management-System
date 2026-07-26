<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetMaintenance extends Model
{
    protected $table = 'asset_maintenance_records';

    protected $fillable = [
        'asset_id', 'issue', 'reported_date', 'status', 'cost', 'staff_id', 'reported_by', 'completed_at',
    ];

    protected $casts = [
        'reported_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
