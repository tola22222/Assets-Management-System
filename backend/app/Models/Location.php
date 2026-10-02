<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'code', 'type', 'description', 'school_id'];

    /**
     * The program this location/school belongs to — ONE, chosen on the
     * Location form (Program → Location → Staff). Stored in the same
     * location_program table as Program::locations(); a location saved before
     * the one-program rule may still list several until it is next edited.
     */
    public function programs()
    {
        return $this->belongsToMany(Program::class, 'location_program')->withTimestamps();
    }

    public function assetStocks()
    {
        return $this->hasMany(AssetStock::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
