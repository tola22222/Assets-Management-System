<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name'];

    // Relationship to Assets (for future use)
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
