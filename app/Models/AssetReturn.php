<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetReturn extends Model
{
    protected $table = 'asset_returns';

    protected $fillable = [
        'assignment_id', 'asset_id', 'returned_by', 'condition',
        'damage_notes', 'image_path', 'staff_signature', 'status',
        'approved_by', 'admin_notes', 'return_date'
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function assignment()
    {
        return $this->belongsTo(AssetAssignment::class, 'assignment_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
