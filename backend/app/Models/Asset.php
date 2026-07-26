<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code', 'name', 'category_id', 'location_id', 'description',
        'model', 'brand', 'serial_number', 'purchase_date',
        'purchase_price', 'warranty_expiry', 'warranty_provider', 'condition', 'status', 'image_path',
        'qr_code_path',
    ];

    protected $appends = ['image_url', 'qr_code_url', 'current_assignee'];

    /** Thresholds for the "Assets by Model" grouped report's stock-level badge. */
    public const STOCK_LEVEL_MEDIUM_MIN = 5;

    public const STOCK_LEVEL_HIGH_MIN = 20;

    public static function stockLevelFor(int $total): string
    {
        return match (true) {
            $total >= self::STOCK_LEVEL_HIGH_MIN => 'high',
            $total >= self::STOCK_LEVEL_MEDIUM_MIN => 'medium',
            default => 'low',
        };
    }

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    public function getQrCodeUrlAttribute()
    {
        return $this->qr_code_path ? asset('storage/'.$this->qr_code_path) : null;
    }

    public function getCurrentAssigneeAttribute()
    {
        if ($this->relationLoaded('currentAssignment')) {
            return $this->currentAssignment?->recipient_name;
        }

        return $this->currentAssignment()->first()?->recipient_name;
    }

    public function currentAssignment()
    {
        return $this->hasOne(AssetAssignment::class)
            ->whereIn('status', ['assigned', 'active'])
            ->latest('assigned_date');
    }

    public function getPublicUrlAttribute()
    {
        return route('asset.public.show', $this->asset_code);
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function stocks()
    {
        return $this->hasMany(AssetStock::class);
    }

    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function verifications()
    {
        return $this->hasMany(AssetVerification::class);
    }

    public function transfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    public function disposals()
    {
        return $this->hasMany(AssetDisposal::class);
    }
}
