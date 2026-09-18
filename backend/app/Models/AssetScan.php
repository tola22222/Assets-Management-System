<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One QR scan event: who scanned which asset, and what they did with it.
 * Written only by Api\QrScanController; read by the QR Scans report.
 */
class AssetScan extends Model
{
    public const ACTION_SCANNED = 'scanned';

    public const ACTION_VERIFIED = 'verified';

    public const ACTION_LOCATION_UPDATED = 'location_updated';

    protected $fillable = [
        'asset_id', 'asset_code', 'asset_name', 'user_id', 'user_name', 'action',
        'location_id', 'previous_location_id', 'condition', 'remark', 'asset_verification_id',
    ];

    /**
     * One line saying who did what, e.g.
     * "Sokha — Location updated (PEPY Office → Kralanh): Office Chair (PEY-SR-FAF-0001)".
     * Reads the location relations, so eager-load them when calling this in a loop.
     */
    public function summary(): string
    {
        $what = match ($this->action) {
            self::ACTION_VERIFIED => 'Verified ('.$this->condition.')',
            self::ACTION_LOCATION_UPDATED => 'Location updated ('.($this->previousLocation->name ?? 'none').' → '.($this->location->name ?? 'unknown').')',
            default => 'QR scanned',
        };

        return ($this->user_name ?: 'Unknown user').' — '.$what.': '.$this->asset_name.' ('.$this->asset_code.')';
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function previousLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'previous_location_id');
    }
}
