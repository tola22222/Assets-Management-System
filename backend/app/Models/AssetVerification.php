<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetVerification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'asset_id',
        'location_id',
        'verified_by',
        'quantity_verified',
        'condition',
        'remark',
        'verified_at',
        'image_path',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'verified_at' => 'date',
    ];

    /**
     * Get the asset being verified.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the location where the verification took place.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who performed the verification.
     * Note: 'verified_by' stores the user ID
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // OR if using Staff model:
    // public function verifiedBy(): BelongsTo
    // {
    //     return $this->belongsTo(Staff::class, 'verified_by');
    // }

    /**
     * Accessor to get verified by name
     */
    public function getVerifiedByNameAttribute(): string
    {
        if ($this->verified_by) {
            $user = User::find($this->verified_by);
            return $user ? $user->name : 'Unknown User';
        }
        return '—';
    }
}