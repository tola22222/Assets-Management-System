<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'code', 'type', 'description', 'school_id'];

    public function assetStocks()
    {
        return $this->hasMany(AssetStock::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
