<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single asset movement between two locations.
 *
 * Lifecycle: pending_approval -> pending -> received, with `rejected` reachable
 * from either pending state. `pending_approval` waits on OPM and only exists
 * for transfers raised by someone else; `pending` waits on the DESTINATION
 * site's responsible staff. The asset's own location_id is not touched until
 * the destination accepts — see Api\AssetTransferController::confirmReceipt().
 */
class AssetTransfer extends Model
{
    protected $table = 'asset_transfers';

    /** Still waiting on someone — OPM (pending_approval) or the destination (pending). */
    public const OPEN_STATUSES = ['pending_approval', 'pending'];

    protected $fillable = [
        'asset_id', 'from_location_id', 'to_location_id',
        'requested_by', 'reason', 'rejection_reason', 'status', 'approved_by', 'transfer_date',
        'received_by', 'received_at', 'parent_transfer_id',
        // Staff verification: who answered, when, accepted/rejected, and the
        // condition the assets arrived in (new / good / fair).
        'verified_by', 'verified_at', 'verification_status', 'received_condition',
        // Optional: the staff member / program the transfer is for, and the
        // assignment written for them on acceptance.
        'assigned_to_type', 'assigned_to_id', 'assignment_id', 'quantity',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'received_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /** Conditions the receiving staff member picks from when accepting. */
    public const RECEIVED_CONDITIONS = ['new', 'good', 'fair'];

    protected $appends = ['recipient_name'];

    /** The assignment created when the destination accepted; null until then. */
    public function assignment()
    {
        return $this->belongsTo(AssetAssignment::class, 'assignment_id');
    }

    /** Who the transfer is for, or null for a plain site-to-site move. */
    public function getRecipientNameAttribute(): ?string
    {
        return match ($this->assigned_to_type) {
            'staff' => Staff::find($this->assigned_to_id)?->full_name ?? 'Unknown Staff',
            'program' => Program::find($this->assigned_to_id)?->name ?? 'Unknown Program',
            default => null,
        };
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * The exact tagged units this transfer moves (asset_transfer_items) — what
     * the receiving staff member reviews, by asset code, before accepting.
     */
    public function units()
    {
        // pivot.status: null (not decided yet), 'accepted' or 'declined' —
        // the receiver ticks which codes actually arrived.
        return $this->belongsToMany(Asset::class, 'asset_transfer_items')->withPivot('status')->withTimestamps();
    }

    /**
     * Ids of the units this transfer moves — or, once accepted, the units that
     * were accepted (declined codes never moved). Falls back to asset_id for a
     * row with no unit lines (created directly, not through the form).
     *
     * @return int[]
     */
    public function unitIds(): array
    {
        $ids = $this->units()
            ->where(fn ($q) => $q->whereNull('asset_transfer_items.status')->orWhere('asset_transfer_items.status', 'accepted'))
            ->pluck('assets.id')->map(fn ($id) => (int) $id)->all();

        return $ids ?: ($this->units()->exists() ? [] : array_filter([(int) $this->asset_id]));
    }

    /** Transfers that move $assetId, whether as the headline asset or as a unit line. */
    public function scopeInvolvingAsset($query, int $assetId)
    {
        return $query->where(fn ($q) => $q->where('asset_id', $assetId)
            ->orWhereHas('units', fn ($u) => $u->whereKey($assetId)));
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** The staff member's login that accepted or rejected the transfer. */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** The outbound transfer this one reverses, when this row is a return. */
    public function parentTransfer()
    {
        return $this->belongsTo(AssetTransfer::class, 'parent_transfer_id');
    }

    /** The return raised against this transfer, if any. */
    public function returnTransfer()
    {
        return $this->hasOne(AssetTransfer::class, 'parent_transfer_id');
    }

    /** Raised by someone other than OPM and not yet released by them. */
    public function scopeAwaitingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    /** Sitting with the destination site, waiting to be accepted or rejected. */
    public function scopeAwaitingDestination($query)
    {
        return $query->where('status', 'pending');
    }
}
