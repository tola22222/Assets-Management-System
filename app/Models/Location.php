<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'type', 'description'];

    public function assetStocks()
    {
        return $this->hasMany(AssetStock::class);
    }
}
