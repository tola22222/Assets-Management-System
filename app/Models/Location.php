<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
     protected $fillable = ['school_id','name','type'];

    public function school()
    {
        return $this->belongsTo(PartnerSchool::class);
    }
}
