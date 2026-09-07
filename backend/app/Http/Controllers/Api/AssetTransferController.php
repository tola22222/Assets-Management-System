<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Moving an asset between sites is a request the RECEIVING site answers.
 *
 * Statuses:
 *   pending_approval -> raised by someone other than OPM; waits on OPM
 *   pending          -> waits on the destination site's responsible staff to
 *                       accept or reject it
 *   received         -> the destination accepted; only now does the asset's
 *                       own location_id change
 *   rejected         -> refused, by OPM before dispatch or by the destination
 *
 * Who may answer for a site is resolved through the program chain — School ->
 * Program -> Responsible Staff — never by role. OPM is not a confirmer at a
 * school it does not run a program for, which is the whole point: the office
 * cannot mark its own delivery as received on the school's behalf.
 */
class AssetTransferController extends Controller
{
    private const WITH = ['asset', 'fromLocation', 'toLocation', 'requester', 'receiver'];

    public function index(Request $request)
    {
        $transfers = AssetTransfer::with([...self::WITH, 'returnTransfer'])->latest()->get();
        $user = $request->user();

        // One lookup for the whole page: site id => user ids who may answer
        // for it. Doing this per row would re-query the program table 50 times.
        $confirmers = $this->confirmersByLocation(
            $transfers->pluck('to_location_id')->unique()->all()
        );

        // The SPA cannot derive the program chain itself, so each row carries
        // what its viewer may do. These are UX hints — every action re-checks
        // canReceive() server-side before changing anything.
        $transfers->each(function (AssetTransfer $transfer) use ($user, $confirmers) {
            $mine = in_array($user->id, $confirmers[$transfer->to_location_id] ?? []);
            $transfer->can_confirm = $mine && $transfer->status === 'pending';
            $transfer->can_decline = $mine && $transfer->status === 'pending';
            $transfer->can_return = $mine && $transfer->status === 'received' && $transfer->returnTransfer === null;
        });

        return response()->json($transfers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'reason' => 'nullable|string',
            'transfer_date' => 'required|date',
        ]);

        $this->assertDestinationCanReceive($validated['to_location_id']);

        $user = $request->user();
        $dispatchedByOpm = $user->isOperationsHrManager();

        $validated['requested_by'] = $user->id;
        // OPM is the dispatch authority, so their transfer goes straight to the
        // destination for acceptance. Anyone else's is a request OPM releases
        // first. Neither shortcut moves the asset — that is the school's call.
        $validated['status'] = $dispatchedByOpm ? 'pending' : 'pending_approval';

        if ($dispatchedByOpm) {
            $validated['approved_by'] = $user->id;
        }

        $transfer = AssetTransfer::create($validated);

        if ($dispatchedByOpm) {
            $this->notifyDestination($transfer);
        } else {
            $this->notifyApprovers($transfer);
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Create',
            'description' => $dispatchedByOpm ? 'Sent asset transfer to destination' : 'Requested transfer of asset',
        ]);

        return response()->json($transfer->fresh(self::WITH), 201);
    }

    /**
     * OPM releases someone else's request. This only hands the request to the
     * destination; it neither completes the transfer nor moves the asset.
     */
    public function approve(AssetTransfer $asset_transfer)
    {
        abort_if($asset_transfer->requested_by === Auth::id(), 403, 'You cannot approve your own transfer request.');
        abort_unless($asset_transfer->status === 'pending_approval', 422, 'Only a request awaiting approval can be approved.');

        $this->assertDestinationCanReceive($asset_transfer->to_location_id);

        $asset_transfer->update([
            'status' => 'pending',
            'approved_by' => Auth::id(),
        ]);

        Notification::create([
            'user_id' => $asset_transfer->requested_by,
            'type' => 'transfer_approved',
            'message' => 'Your transfer request was approved and sent to '.($asset_transfer->toLocation->name ?? 'the destination').' for acceptance.',
            'url' => '/app/asset-transfers',
        ]);

        $this->notifyDestination($asset_transfer);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved asset transfer request',
        ]);

        return response()->json($asset_transfer->fresh(self::WITH));
    }

    /** OPM refuses a request before it ever reaches the destination. */
    public function reject(Request $request, AssetTransfer $asset_transfer)
    {
        $validated = $request->validate(['rejection_reason' => 'nullable|string']);

        // Deciding your own request is the thing the independent-review rule
        // exists to prevent, and rejecting is a decision too.
        abort_if($asset_transfer->requested_by === Auth::id(), 403, 'You cannot reject your own transfer request.');
        abort_unless($asset_transfer->status === 'pending_approval', 422, 'Only a request awaiting approval can be rejected here.');

        $asset_transfer->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        Notification::create([
            'user_id' => $asset_transfer->requested_by,
            'type' => 'transfer_rejected',
            'message' => 'Your transfer request has been rejected.',
            'url' => '/app/asset-transfers',
        ]);

        return response()->json($asset_transfer->fresh(self::WITH));
    }

    /**
     * The receiving site accepts the delivery. This is the only place an
     * asset's location_id changes, so the register never claims an asset has
     * arrived somewhere nobody has acknowledged.
     */
    public function confirmReceipt(Request $request, AssetTransfer $asset_transfer)
    {
        abort_unless($this->canReceive($request->user(), $asset_transfer), 403, 'Only the receiving site\'s responsible staff can accept this transfer.');
        abort_unless($asset_transfer->status === 'pending', 422, 'Only a pending transfer can be accepted.');

        $asset_transfer->update([
            'status' => 'received',
            'received_by' => $request->user()->id,
            'received_at' => now(),
        ]);

        $asset_transfer->asset()->update(['location_id' => $asset_transfer->to_location_id]);

        Notification::create([
            'user_id' => $asset_transfer->requested_by,
            'type' => 'transfer_received',
            'message' => ($asset_transfer->asset->name ?? 'An asset').' was accepted at '.($asset_transfer->toLocation->name ?? 'its destination').'.',
            'url' => '/app/asset-transfers',
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Receive',
            'description' => 'Accepted asset transfer',
        ]);

        return response()->json($asset_transfer->fresh(self::WITH));
    }

    /**
     * The receiving site turns the delivery away. Distinct from reject()
     * above: that one is OPM refusing to release a request, this one is the
     * destination refusing to take the asset. The asset never moves either way.
     */
    public function decline(Request $request, AssetTransfer $asset_transfer)
    {
        $validated = $request->validate(['rejection_reason' => 'nullable|string']);

        abort_unless($this->canReceive($request->user(), $asset_transfer), 403, 'Only the receiving site\'s responsible staff can reject this transfer.');
        abort_unless($asset_transfer->status === 'pending', 422, 'Only a pending transfer can be rejected.');

        $asset_transfer->update([
            'status' => 'rejected',
            // approved_by carries "who decided this transfer's fate", which is
            // the destination here rather than OPM.
            'approved_by' => $request->user()->id,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        Notification::create([
            'user_id' => $asset_transfer->requested_by,
            'type' => 'transfer_rejected',
            'message' => ($asset_transfer->toLocation->name ?? 'The destination').' rejected the transfer of '.($asset_transfer->asset->name ?? 'an asset').'.',
            'url' => '/app/asset-transfers',
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Reject',
            'description' => 'Rejected incoming asset transfer',
        ]);

        return response()->json($asset_transfer->fresh(self::WITH));
    }

    /**
     * The holding site has finished with the asset and is sending it back.
     *
     * This raises a fresh transfer in the opposite direction rather than
     * rewinding the original, so each leg keeps its own date, reason and
     * acceptance, and a send/return cycle can repeat without overwriting
     * history. It starts at `pending` because the site is returning the asset
     * to where it came from — there is nothing for OPM to release, and the
     * origin's own responsible staff accept it on arrival.
     */
    public function returnAsset(Request $request, AssetTransfer $asset_transfer)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string',
            'transfer_date' => 'nullable|date',
        ]);

        abort_unless($this->canReceive($request->user(), $asset_transfer), 403, 'Only the site holding this asset can return it.');
        abort_unless($asset_transfer->status === 'received', 422, 'Only an accepted transfer can be returned.');
        abort_if($asset_transfer->returnTransfer()->exists(), 422, 'This transfer has already been returned.');

        $this->assertDestinationCanReceive($asset_transfer->from_location_id);

        $return = AssetTransfer::create([
            'asset_id' => $asset_transfer->asset_id,
            'from_location_id' => $asset_transfer->to_location_id,
            'to_location_id' => $asset_transfer->from_location_id,
            'requested_by' => $request->user()->id,
            'reason' => $validated['reason'] ?? null,
            'transfer_date' => $validated['transfer_date'] ?? now()->toDateString(),
            'status' => 'pending',
            'parent_transfer_id' => $asset_transfer->id,
        ]);

        $this->notifyDestination($return, 'transfer_return');

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Return',
            'description' => 'Returned asset to '.($return->toLocation->name ?? 'origin'),
        ]);

        return response()->json($return->fresh(self::WITH), 201);
    }

    public function destroy(AssetTransfer $asset_transfer)
    {
        // Once the destination has been asked to accept it, the request is
        // theirs to answer — rejecting is the audit-visible way to kill it.
        if (! in_array($asset_transfer->status, ['pending_approval', 'rejected'])) {
            return response()->json([
                'message' => 'Only a request awaiting approval, or a rejected one, can be deleted.',
            ], 422);
        }

        $asset_transfer->delete();

        return response()->json(['message' => 'Transfer deleted.']);
    }

    /**
     * Refuse to create a transfer nobody at the destination could ever accept.
     *
     * Failing here, loudly, beats letting the row sit pending forever with no
     * indication of why nothing happens. The fix is to give one of that site's
     * programs a responsible staff member who has a login account.
     */
    private function assertDestinationCanReceive(int $locationId): void
    {
        if ($this->confirmerIdsFor($locationId) !== []) {
            return;
        }

        $name = Location::find($locationId)?->name ?? 'The destination';

        abort(422, $name.' has no responsible staff who can accept a transfer. Assign a responsible staff member (with a login account) to one of its programs first.');
    }

    /**
     * The login accounts that answer for a site: the responsible staff of the
     * programs running there.
     *
     * Role is deliberately not part of this. If the office's own program lead
     * happens to be an OPM-role user then they answer for the office — but
     * that same person is not a confirmer at a school they run no program for,
     * which is what stops HR accepting a delivery on a school's behalf.
     */
    private function confirmerIdsFor(?int $locationId): array
    {
        if ($locationId === null) {
            return [];
        }

        return $this->confirmersByLocation([$locationId])[$locationId] ?? [];
    }

    /** @return array<int, int[]> location id => user ids who answer for it */
    private function confirmersByLocation(array $locationIds): array
    {
        $locationIds = array_values(array_filter($locationIds));

        if ($locationIds === []) {
            return [];
        }

        $staffByLocation = Program::whereIn('location_id', $locationIds)
            ->whereNotNull('responsible_staff_id')
            ->get(['location_id', 'responsible_staff_id']);

        $userIdsByStaff = User::whereIn('staff_id', $staffByLocation->pluck('responsible_staff_id')->unique())
            ->get(['id', 'staff_id'])
            ->groupBy('staff_id')
            ->map(fn ($users) => $users->pluck('id')->all());

        $out = [];

        foreach ($staffByLocation as $program) {
            foreach ($userIdsByStaff[$program->responsible_staff_id] ?? [] as $userId) {
                $out[$program->location_id][] = $userId;
            }
        }

        return array_map('array_unique', $out);
    }

    private function canReceive(User $user, AssetTransfer $transfer): bool
    {
        return in_array($user->id, $this->confirmerIdsFor($transfer->to_location_id));
    }

    /** Tell the destination's responsible staff there is something to answer. */
    private function notifyDestination(AssetTransfer $transfer, string $type = 'transfer_incoming'): void
    {
        $message = ($transfer->asset->name ?? 'An asset').' is being transferred to '
            .($transfer->toLocation->name ?? 'your site').' — accept or reject it.';

        foreach ($this->confirmerIdsFor($transfer->to_location_id) as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'message' => $message,
                'url' => '/app/asset-transfers',
            ]);
        }
    }

    /** Tell OPM a request is waiting on their approval. */
    private function notifyApprovers(AssetTransfer $transfer): void
    {
        User::where('role', 'operations_hr_manager')
            ->where('id', '!=', $transfer->requested_by)
            ->get()
            ->each(fn (User $admin) => Notification::create([
                'user_id' => $admin->id,
                'type' => 'transfer_request',
                'message' => 'New asset transfer request for '.($transfer->asset->name ?? 'Asset'),
                'url' => '/app/asset-transfers',
            ]));
    }
}
