<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code', 'name', 'category_id', 'location_id', 'description',
        'model', 'brand', 'serial_number', 'purchase_date',
        'purchase_price', 'condition', 'status', 'image_path',
        'qr_code_path',
    ];

    protected $appends = ['image_url', 'qr_code_url'];

    /** Lifecycle status. `disposed` is only ever set by an approved disposal. */
    public const STATUSES = ['active', 'disposed'];

    public const CONDITIONS = ['good', 'fair', 'broken', 'lost'];

    /**
     * Assets this user may see: every asset for OPM/Finance/ED, only their own
     * site for staff (all sites while a staff account has no site set yet —
     * see User::canAccessLocation()).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isSiteScoped()) {
            return $query;
        }

        $site = $user->siteLocationId();

        return $site === null
            ? $query
            : $query->where($query->qualifyColumn('location_id'), $site);
    }

    /** Still on the register — i.e. not written off. */
    public function scopeOnRegister(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), '!=', 'disposed');
    }

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

    public function scans()
    {
        return $this->hasMany(AssetScan::class);
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
