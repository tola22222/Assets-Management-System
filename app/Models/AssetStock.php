<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetStock extends Model
{
    protected $table = 'asset_stock';

    protected $fillable = ['asset_id','location_id','quantity'];
}
