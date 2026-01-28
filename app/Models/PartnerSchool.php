<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerSchool extends Model
{
    protected $fillable = [
        'school_code','school_name','province','district',
        'contact_person','contact_phone','status'
    ];

    public function locations()
    {
        return $this->hasMany(Location::class);
    }
}
