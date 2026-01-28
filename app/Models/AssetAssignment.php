<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $fillable = [
        'asset_id','assigned_to_type','assigned_to_id',
        'location_id','quantity','assigned_date','status'
    ];
}
