<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'photo_path',
        'position',
        'hire_date',
        'status'
    ];

    protected $appends = ['photo_path_url'];

    public function getPhotoPathUrlAttribute() {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }
}
