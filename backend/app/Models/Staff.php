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
        // The one program this person belongs to. It decides which schools
        // they can see and manage (User::siteLocationIds()).
        'program_id',
    ];

    protected $appends = ['photo_path_url'];

    public function getPhotoPathUrlAttribute()
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    /** Their first location (kept for anything that reads a single one). */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Every location/school this person works at — all in one program, which
     * is where their program comes from (Program → Location → Staff).
     */
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'location_staff')->withTimestamps();
    }

    /** The login account for this person, if they have one. */
    /** The program this staff member belongs to (at most one). */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

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
