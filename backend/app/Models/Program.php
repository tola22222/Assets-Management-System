<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A program runs at one or more schools (locations()) and has one staff member
 * accountable for it (responsibleStaff()). Staff belong to exactly one program
 * (staff.program_id) and can see and manage every school it is linked to.
 *
 * That accountability is load-bearing: AssetTransferController resolves who
 * may accept a delivery at a site by looking up the responsible staff of the
 * programs running there, so a program with no responsible staff leaves its
 * schools unable to receive anything.
 *
 * programs.location_id is kept as the program's first school for anything
 * that still reads a single school; location_program is the full list.
 */
class Program extends Model
{
    protected $fillable = ['name', 'description', 'location_id', 'responsible_staff_id'];

    protected static function booted(): void
    {
        // A program created with just a location_id (seeders, older callers)
        // still gets that school linked.
        static::saved(function (Program $program) {
            if ($program->location_id && ! $program->locations()->whereKey($program->location_id)->exists()) {
                $program->locations()->attach($program->location_id);
            }
        });
    }

    /** The program's first school (see class docblock). */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /** Every school/location this program runs at. */
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'location_program')->withTimestamps();
    }

    /** The staff members who belong to this program. */
    public function members()
    {
        return $this->hasMany(Staff::class);
    }

    public function responsibleStaff()
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    /**
     * Programs this user may see. Staff see only the one program they belong
     * to (or lead) — none until HR assigns one. Everyone else sees every program.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isSiteScoped()) {
            return $query;
        }

        $ids = array_values(array_unique(array_filter([
            (int) ($user->staff?->program_id ?? 0),
            ...$user->ledProgramIds(),
        ])));

        return $query->whereIn($query->qualifyColumn('id'), $ids);
    }

    // If you want to see which assets are assigned to this program
    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class, 'assigned_to_id')
            ->where('assigned_to_type', 'program');
    }
}
