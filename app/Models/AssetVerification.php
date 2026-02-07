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
    ];

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
}