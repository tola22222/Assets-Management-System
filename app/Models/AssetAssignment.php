<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $fillable = [
        'asset_id',
        'assigned_to_type',
        'assigned_to_id',
        'location_id',
        'quantity',
        'assigned_date',
        'status'
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    // Helper to get the name of the recipient dynamically
    public function getRecipientNameAttribute()
    {
        if ($this->assigned_to_type === 'staff') {
            return Staff::find($this->assigned_to_id)?->full_name ?? 'Unknown Staff';
        }
        // Assuming you have a Program model, otherwise return the ID
        return "Program: " . $this->assigned_to_id;
    }

    public function getAssigneeNameAttribute()
    {
        if ($this->assigned_to_type === 'staff') {
            // Fetch from Staff model
            return \App\Models\Staff::find($this->assigned_to_id)?->full_name ?? 'Unknown Staff';
        }

        if ($this->assigned_to_type === 'program') {
            // Fetch from Program model (Assumes you have a Program model with a 'name' field)
            return \App\Models\Program::find($this->assigned_to_id)?->name ?? 'Unknown Program';
        }

        return 'N/A';
    }
}
