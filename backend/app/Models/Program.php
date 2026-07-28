<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['name', 'description'];

    // If you want to see which assets are assigned to this program
    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class, 'assigned_to_id')
                    ->where('assigned_to_type', 'program');
    }
}