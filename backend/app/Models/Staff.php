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
        'status',
        'location_id',
    ];

    protected $appends = ['photo_path_url'];

    public function getPhotoPathUrlAttribute()
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /** The login account for this person, if they have one. */
    public function user()
    {
        return $this->hasOne(User::class, 'staff_id');
    }

    /** Programs this person is accountable for. */
    public function programs()
    {
        return $this->hasMany(Program::class, 'responsible_staff_id');
    }
}
