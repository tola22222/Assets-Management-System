<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A program runs at one school and has one staff member accountable for it.
 *
 * That accountability is load-bearing: AssetTransferController resolves who
 * may accept a delivery at a site by looking up the responsible staff of the
 * programs running there, so a program with no responsible staff leaves its
 * school unable to receive anything.
 */
class Program extends Model
{
    protected $fillable = ['name', 'description', 'location_id', 'responsible_staff_id'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function responsibleStaff()
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    // If you want to see which assets are assigned to this program
    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class, 'assigned_to_id')
            ->where('assigned_to_type', 'program');
    }
}
