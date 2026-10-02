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
        'qr_code_path', 'supplier_id',
    ];

    protected $appends = ['image_url', 'qr_code_url'];

    /** Lifecycle status. `disposed` is only ever set by an approved disposal. */
    public const STATUSES = ['active', 'disposed'];

    public const CONDITIONS = ['good', 'fair', 'broken', 'lost'];

    /**
     * Most units one Add Asset (or one import row) may register at once. Each
     * unit is its own asset with its own code, QR code and history.
     */
    public const MAX_BATCH_QUANTITY = 500;

    /**
     * Assets this user may see: every asset for OPM/Finance/ED; for staff,
     * only the assets at their program's schools — and none at all while they
     * have no program (fail closed, see User::siteLocationIds()).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isSiteScoped()) {
            return $query;
        }

        // Their program's schools. An empty list matches nothing (fail closed),
        // never unplaced assets.
        return $query->whereIn($query->qualifyColumn('location_id'), $user->siteLocationIds());
    }

    /** Still on the register — i.e. not written off. */
    public function scopeOnRegister(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), '!=', 'disposed');
    }

    /**
     * Conditions that take an asset out of the available stock count. Set by
     * a verification (or QR-scan verification) — re-verifying it good or fair
     * puts it straight back, because the counts are live, never stored.
     */
    public const UNAVAILABLE_CONDITIONS = ['broken', 'lost'];

    /** On the register and usable: not disposed, not verified lost or broken. */
    public function scopeAvailable(Builder $query): Builder
    {
        $condition = $query->qualifyColumn('condition');

        // whereNotIn alone would also drop rows with no condition recorded.
        return $query->onRegister()->where(fn ($q) => $q->whereNull($condition)->orWhereNotIn($condition, self::UNAVAILABLE_CONDITIONS));
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

    /** Where it was bought from. Optional; cleared if the supplier is deleted. */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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
