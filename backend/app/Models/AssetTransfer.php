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

    protected $fillable = [
        'asset_id', 'from_location_id', 'to_location_id',
        'requested_by', 'reason', 'rejection_reason', 'status', 'approved_by', 'transfer_date',
        'received_by', 'received_at', 'parent_transfer_id',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
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
