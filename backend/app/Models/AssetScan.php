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
