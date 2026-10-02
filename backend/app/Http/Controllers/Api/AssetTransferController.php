<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use App\Services\AssetStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    private const WITH = ['asset', 'units:assets.id,assets.asset_code,assets.name,assets.condition', 'fromLocation', 'toLocation', 'requester', 'receiver', 'verifier', 'assignment'];

    public function __construct(private AssetStockService $stock) {}

    /**
     * Total / Transferred / Available for the model of the chosen asset AT ONE
     * LOCATION — the transfer's From site (location_id), or where the chosen
     * unit is when none is given. Shown on the Transfer form; recounted on
     * every call.
     */
    public function stock(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);
        $asset = Asset::findOrFail($validated['asset_id']);

        // Staff only look up assets at their own sites, and see those sites'
        // numbers only — never other sites' stock.
        $user = $request->user();
        abort_unless($user->canAccessAsset($asset), 404);

        $locationId = $validated['location_id'] ?? $asset->location_id;
        abort_if($locationId !== null && ! $user->canAccessLocation((int) $locationId), 404);

        return response()->json($this->stock->forAsset($asset, null, $locationId !== null ? [(int) $locationId] : null));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = AssetTransfer::with([...self::WITH, 'returnTransfer'])->latest()->latest('id');

        // Staff see only transfers leaving or arriving at one of their
        // program's schools — none until HR assigns a program (fail closed).
        if ($user->isSiteScoped()) {
            $sites = $user->siteLocationIds();
            $query->where(fn ($q) => $q->whereIn('from_location_id', $sites)->orWhereIn('to_location_id', $sites));
        }

        $transfers = $query->get();

        // One lookup for the whole page: site id => user ids who may answer
        // for it. Doing this per row would re-query the program table 50 times.
        $confirmers = $this->confirmersByLocation(
            $transfers->pluck('to_location_id')->unique()->all()
        );

        // The SPA cannot derive the program chain itself, so each row carries
        // what its viewer may do. These are UX hints — every action re-checks
        // canReceive() server-side before changing anything.
        $transfers->each(function (AssetTransfer $transfer) use ($user, $confirmers) {
            // HR / the Accountant never get Accept or Reject (canReceive()).
            $mine = ! $user->isAdministrator() && (
                in_array($user->id, $confirmers[$transfer->to_location_id] ?? [])
                || $this->isRecipient($user, $transfer)
            );
            $transfer->can_confirm = $mine && $transfer->status === 'pending';
            $transfer->can_decline = $mine && $transfer->status === 'pending';
            // A return row is the end of the line — it can't itself be
            // returned (that would send the assets back out) or re-assigned.
            $transfer->can_return = $this->canReturn($user) && $transfer->status === 'received'
                && $transfer->returnTransfer === null && $transfer->parent_transfer_id === null;
            // A return still waiting (from before returns completed on the
            // spot): HR / the Accountant finish it through Edit → Return.
            $transfer->can_complete_return = $this->canReturn($user) && $transfer->parent_transfer_id !== null && $transfer->status === 'pending';
            $transfer->can_delete = $this->canDelete($user, $transfer);
        });

        return response()->json($transfers);
    }

    public function store(Request $request)
    {
        // The default Staff role cannot transfer — it only answers transfers.
        // A custom role granting Transfers → Create still can.
        abort_unless($request->user()->hasPermission('asset-transfers', 'create'), 403, 'You do not have permission to create a transfer.');

        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'reason' => 'nullable|string',
            'transfer_date' => 'required|date',
            // Optional: who at the destination the asset is for, chosen the
            // same way as on the Assignment form.
            'assigned_to_type' => 'nullable|in:staff,program',
            'assigned_to_id' => 'nullable|required_with:assigned_to_type|integer',
            // Units of this asset's model; checked against available stock below.
            'quantity' => 'nullable|integer|min:1',
            // The exact tagged units HR ticked. When given, they ARE the
            // transfer: quantity is their count and the receiver reviews them
            // by asset code before accepting.
            'asset_ids' => 'nullable|array|min:1',
            'asset_ids.*' => 'integer|distinct|exists:assets,id',
        ]);

        $user = $request->user();
        $ticked = isset($validated['asset_ids']) ? array_values(array_map('intval', $validated['asset_ids'])) : null;
        unset($validated['asset_ids']);

        // A bare quantity (no codes ticked) still moves real units: that many
        // free units of the model at the From location, the chosen one first,
        // so both locations' stock counts stay true. Too few free units falls
        // through to the stock check below, which refuses it.
        if ($ticked === null && (int) ($validated['quantity'] ?? 1) > 1) {
            $chosen = Asset::findOrFail($validated['asset_id']);
            $free = $this->stock->forAsset($chosen, null, [(int) $validated['from_location_id']])['available_ids'];
            if (in_array((int) $chosen->id, $free, true)) {
                $free = [(int) $chosen->id, ...array_values(array_diff($free, [(int) $chosen->id]))];
            }
            if (count($free) >= (int) $validated['quantity']) {
                $ticked = array_slice($free, 0, (int) $validated['quantity']);
            }
        }

        if ($ticked !== null) {
            // The first ticked unit is the transfer's headline asset (its model).
            $model = Asset::findOrFail($validated['asset_id']);
            $validated['asset_id'] = $ticked[0];
            $validated['quantity'] = count($ticked);
            $this->assertUnitsCanMove($user, $model, $ticked, (int) $validated['from_location_id']);
        }

        $validated['quantity'] = $validated['quantity'] ?? 1;
        $asset = Asset::findOrFail($validated['asset_id']);
        $unitIds = $ticked ?? [$asset->id];

        if (empty($validated['assigned_to_type'])) {
            unset($validated['assigned_to_type'], $validated['assigned_to_id']);
        } else {
            $this->assertRecipientAtDestination($user, $validated);
        }

        // Staff may only ask to move what is at their own site.
        abort_unless($user->canAccessAsset($asset), 403, 'You can only request transfers for assets at your own site.');

        abort_if($asset->status === 'disposed', 422, 'This asset has been disposed and cannot be transferred.');

        // The transfer starts where the asset actually is, not where the form says.
        abort_if(
            $asset->location_id !== null && (int) $validated['from_location_id'] !== (int) $asset->location_id,
            422,
            'This asset is at '.($asset->location->name ?? 'another site').', not at the selected "from" location.'
        );

        // One open transfer per asset: two could both be accepted and the
        // second acceptance would silently overwrite the first.
        abort_if(
            AssetTransfer::involvingAsset($asset->id)->whereIn('status', AssetTransfer::OPEN_STATUSES)->exists(),
            422,
            'This asset already has an open transfer. Wait for it to be accepted or rejected first.'
        );

        $this->assertDestinationCanReceive($validated['to_location_id'], $validated['assigned_to_type'] ?? null, $validated['assigned_to_id'] ?? null);

        $dispatchedByOpm = $user->isAdministrator();

        $validated['requested_by'] = $user->id;
        // OPM is the dispatch authority, so their transfer goes straight to the
        // destination for acceptance. Anyone else's is a request OPM releases
        // first. Neither shortcut moves the asset — that is the school's call.
        $validated['status'] = $dispatchedByOpm ? 'pending' : 'pending_approval';

        if ($dispatchedByOpm) {
            $validated['approved_by'] = $user->id;
        }

        // Never more than the From location has available — every location
        // (Office or school) holds its own stock, so Office's spare units never
        // cover a school sending what it has already assigned. Checked under a
        // lock, in the same transaction as the insert, so two requests can't
        // both take the last units.
        $stockSite = [(int) $validated['from_location_id']];
        $transfer = DB::transaction(function () use ($asset, $validated, $stockSite, $unitIds) {
            $this->stock->assertAvailable($asset, $validated['quantity'], 'quantity', null, $stockSite);

            $transfer = AssetTransfer::create($validated);
            $transfer->units()->sync($unitIds);

            return $transfer;
        });

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

        $this->assertDestinationCanReceive($asset_transfer->to_location_id, $asset_transfer->assigned_to_type, $asset_transfer->assigned_to_id);

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

    /** OPM or the ED refuses a request before it ever reaches the destination. */
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
        abort_unless($this->canReceive($request->user(), $asset_transfer), 403, 'Only the recipient or the receiving site\'s responsible staff can accept this transfer.');
        abort_unless($asset_transfer->status === 'pending', 422, 'Only a pending transfer can be accepted.');

        // The receiver ticks which asset codes actually arrived. Omitted means
        // all of them. Unticked codes are declined: they stay where they were.
        // They must also verify the condition the assets arrived in.
        $validated = $request->validate([
            'asset_ids' => 'nullable|array|min:1',
            'asset_ids.*' => 'integer|distinct',
            'rejection_reason' => 'nullable|string',
            'condition' => 'required|in:'.implode(',', AssetTransfer::RECEIVED_CONDITIONS),
        ], [
            'condition.required' => 'Select the condition the assets arrived in.',
        ]);

        $sent = $asset_transfer->unitIds();
        $accepted = isset($validated['asset_ids']) ? array_values(array_map('intval', $validated['asset_ids'])) : $sent;

        if (array_diff($accepted, $sent)) {
            throw ValidationException::withMessages(['asset_ids' => 'You can only accept asset codes that are on this transfer.']);
        }
        $declined = array_values(array_diff($sent, $accepted));

        $this->receive($asset_transfer, $request->user(), $accepted, $declined, $validated['rejection_reason'] ?? null, $validated['condition']);

        $name = $asset_transfer->asset->name ?? 'An asset';
        Notification::create([
            'user_id' => $asset_transfer->requested_by,
            'type' => 'transfer_received',
            'message' => ($declined
                ? count($accepted).' of '.(count($accepted) + count($declined)).' × '.$name.' accepted at '
                : $name.' was accepted at ').($asset_transfer->toLocation->name ?? 'its destination')
                .' by '.$this->personName($request->user()).'.',
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
        abort_unless($this->canReceive($request->user(), $asset_transfer), 403, 'Only the recipient or the receiving site\'s responsible staff can reject this transfer.');
        abort_unless($asset_transfer->status === 'pending', 422, 'Only a pending transfer can be rejected.');

        // The receiving staff member has to say why they turned it away.
        $validated = $request->validate(
            ['rejection_reason' => 'required|string|max:1000'],
            ['rejection_reason.required' => 'Enter the reason for rejecting this transfer.'],
        );

        $asset_transfer->update([
            'status' => 'rejected',
            // approved_by carries "who decided this transfer's fate", which is
            // the destination here rather than OPM.
            'approved_by' => $request->user()->id,
            'rejection_reason' => $validated['rejection_reason'],
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_status' => 'rejected',
        ]);

        Notification::create([
            'user_id' => $asset_transfer->requested_by,
            'type' => 'transfer_rejected',
            'message' => $this->personName($request->user()).' at '.($asset_transfer->toLocation->name ?? 'the destination')
                .' rejected the transfer of '.($asset_transfer->asset->name ?? 'an asset').'.',
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
     * HR (OPM) or the Accountant (Finance) sends an accepted transfer's asset
     * back to where it came from. Staff cannot raise a return themselves.
     *
     * This records a fresh transfer in the opposite direction rather than
     * rewinding the original, so each leg keeps its own date and reason, and
     * a send/return cycle can repeat without overwriting history.
     *
     * Because only HR / the Accountant can return, and they ARE the office
     * side, the return takes effect at once — signed off by whoever raised it:
     * the units move back, the recipient's assignments on them end, and they
     * are available stock again. There is no second "accept" step to wait on.
     */
    public function returnAsset(Request $request, AssetTransfer $asset_transfer)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string',
            'transfer_date' => 'nullable|date',
            // The condition HR / the Accountant find each code in as it comes
            // back: { asset id: good | fair | broken | lost }. Omitted codes
            // keep the condition they have.
            'conditions' => 'nullable|array',
            'conditions.*' => 'in:'.implode(',', Asset::CONDITIONS),
            'condition_remark' => 'nullable|string|max:1000',
        ]);

        // Sending an asset back is HR's or the Accountant's call, not the
        // holding site's (also guarded by the role: middleware on the route).
        abort_unless($this->canReturn($request->user()), 403, 'Only the Operations & HR Manager or the Finance Manager can return an asset.');

        $conditions = $this->returnConditions($validated, $asset_transfer->unitIds());

        // A return raised before returns completed on the spot is still
        // waiting: "Return" in the Edit dialog finishes it — every code back
        // at the origin, assignments ended, in stock.
        if ($asset_transfer->parent_transfer_id !== null && $asset_transfer->status === 'pending') {
            DB::transaction(function () use ($asset_transfer, $request, $conditions, $validated) {
                $this->receive($asset_transfer, $request->user(), $asset_transfer->unitIds(), [], null);
                $this->verifyReturned($conditions, (int) $asset_transfer->to_location_id, $request->user(), $validated['condition_remark'] ?? null);
            });

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'Return',
                'description' => 'Completed return to '.($asset_transfer->toLocation->name ?? 'origin'),
            ]);

            return response()->json($asset_transfer->fresh(self::WITH));
        }
        abort_if($asset_transfer->parent_transfer_id !== null, 422, 'This is already a return — the assets are back. It cannot be returned again.');
        abort_unless($asset_transfer->status === 'received', 422, 'Only an accepted transfer can be returned.');
        abort_if($asset_transfer->returnTransfer()->exists(), 422, 'This transfer has already been returned.');

        // A return sends the units back from where this leg delivered them. If
        // any has since moved on, returning this leg would teleport it.
        $unitIds = $asset_transfer->unitIds();
        $units = Asset::whereIn('id', $unitIds)->get();
        abort_if(
            $units->count() !== count($unitIds) || $units->contains(fn (Asset $u) => (int) $u->location_id !== (int) $asset_transfer->to_location_id),
            422,
            'Not every asset on this transfer is still at '.($asset_transfer->toLocation->name ?? 'this site').', so it cannot be returned.'
        );
        abort_if($units->contains('status', 'disposed'), 422, 'An asset on this transfer has been disposed and cannot be returned.');
        abort_if(
            AssetTransfer::whereIn('status', AssetTransfer::OPEN_STATUSES)
                ->where(fn ($q) => $q->whereIn('asset_id', $unitIds)->orWhereHas('units', fn ($u) => $u->whereIn('assets.id', $unitIds)))
                ->exists(),
            422,
            'This asset already has an open transfer.'
        );

        $return = DB::transaction(function () use ($request, $asset_transfer, $validated, $unitIds, $conditions) {
            $return = AssetTransfer::create([
                // The units that actually arrived go back — not any declined ones.
                'asset_id' => $unitIds[0] ?? $asset_transfer->asset_id,
                'from_location_id' => $asset_transfer->to_location_id,
                'to_location_id' => $asset_transfer->from_location_id,
                'requested_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'reason' => $validated['reason'] ?? null,
                'transfer_date' => $validated['transfer_date'] ?? now()->toDateString(),
                'status' => 'pending',
                'parent_transfer_id' => $asset_transfer->id,
                'quantity' => count($unitIds),
            ]);
            $return->units()->sync($unitIds);

            // Takes effect now: back at the origin, assignments ended, in stock.
            $this->receive($return, $request->user(), $unitIds, [], null);

            // …in the condition they came back in. Broken / lost ones keep
            // their record and code but are out of use from here on.
            $this->verifyReturned($conditions, (int) $asset_transfer->from_location_id, $request->user(), $validated['condition_remark'] ?? null);

            return $return;
        });

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Return',
            'description' => 'Returned asset to '.($return->toLocation->name ?? 'origin'),
        ]);

        return response()->json($return->fresh(self::WITH), 201);
    }

    /**
     * The per-code conditions sent with a return, checked: only codes on this
     * transfer, and a reason whenever one comes back broken or lost — the same
     * rule as a verification.
     *
     * @param  int[]  $unitIds
     * @return array<int, string> asset id => condition
     */
    private function returnConditions(array $validated, array $unitIds): array
    {
        $conditions = [];
        foreach ($validated['conditions'] ?? [] as $assetId => $condition) {
            $conditions[(int) $assetId] = $condition;
        }

        if (array_diff(array_keys($conditions), $unitIds)) {
            throw ValidationException::withMessages(['conditions' => 'You can only set the condition of asset codes on this transfer.']);
        }

        if (array_intersect($conditions, Asset::UNAVAILABLE_CONDITIONS) && blank($validated['condition_remark'] ?? null)) {
            throw ValidationException::withMessages(['condition_remark' => 'Enter the reason the asset is broken or lost.']);
        }

        return $conditions;
    }

    /**
     * Record the condition each returned code came back in, exactly as a
     * verification would: the asset's condition is updated (broken / lost
     * takes it out of available stock; its row and code are never deleted),
     * and an audited AssetVerification row keeps who, when, before / after,
     * how many units it took out of use, and why.
     *
     * @param  array<int, string>  $conditions  asset id => condition
     */
    private function verifyReturned(array $conditions, int $locationId, User $by, ?string $remark): void
    {
        foreach (Asset::whereIn('id', array_keys($conditions))->get() as $asset) {
            $condition = $conditions[$asset->id];

            AssetVerification::create([
                'asset_id' => $asset->id,
                'location_id' => $locationId,
                'verified_by' => $by->id,
                'quantity_verified' => 1,
                'condition' => $condition,
                'previous_condition' => $asset->condition,
                'quantity_affected' => in_array($condition, Asset::UNAVAILABLE_CONDITIONS, true) ? 1 : 0,
                'remark' => $remark,
                'verified_at' => now(),
            ]);

            if ($asset->condition !== $condition) {
                $asset->update(['condition' => $condition]);
            }
        }

        $outOfUse = count(array_intersect($conditions, Asset::UNAVAILABLE_CONDITIONS));
        if ($conditions) {
            ActivityLog::create([
                'user_id' => $by->id,
                'action' => 'Verification',
                'description' => 'Verified '.count($conditions).' returned asset(s)'.($outOfUse ? ", {$outOfUse} broken / lost" : ''),
            ]);
        }
    }

    /**
     * HR / the Accountant change who holds an accepted transfer's assets at
     * the destination (the "Assignment" part of the Edit dialog): the current
     * holder's assignments on those units end, and the new recipient — a staff
     * member or program at the destination, or nobody — gets one per unit.
     * Nothing moves; location only changes through a transfer or a return.
     */
    public function reassign(Request $request, AssetTransfer $asset_transfer)
    {
        $validated = $request->validate([
            'assigned_to_type' => 'nullable|in:staff,program',
            'assigned_to_id' => 'nullable|required_with:assigned_to_type|integer',
        ]);

        abort_if($asset_transfer->parent_transfer_id !== null, 422, 'A return cannot be edited.');
        abort_unless($asset_transfer->status === 'received', 422, 'Only an accepted transfer can be edited.');
        abort_if($asset_transfer->returnTransfer()->exists(), 422, 'This transfer has been returned and can no longer be edited.');

        $type = $validated['assigned_to_type'] ?? null;
        $id = $type ? (int) $validated['assigned_to_id'] : null;

        if ($type) {
            $this->assertRecipientAtDestination($request->user(), [
                'assigned_to_type' => $type, 'assigned_to_id' => $id, 'to_location_id' => $asset_transfer->to_location_id,
            ]);
        }

        $unitIds = $asset_transfer->unitIds();
        abort_if(
            Asset::whereIn('id', $unitIds)->where('location_id', '!=', $asset_transfer->to_location_id)->exists(),
            422,
            'Not every asset on this transfer is still at '.($asset_transfer->toLocation->name ?? 'the destination').', so its assignment cannot be changed here.'
        );

        // Only this transfer's own recipient may be holding them: a unit
        // assigned to someone else since is in use, not stock to hand over.
        $heldElsewhere = AssetAssignment::whereIn('asset_id', $unitIds)
            ->whereIn('status', ['assigned', 'active', 'overdue'])
            ->when($asset_transfer->assigned_to_type, fn ($q) => $q->where(fn ($w) => $w
                ->where('assigned_to_type', '!=', $asset_transfer->assigned_to_type)
                ->orWhere('assigned_to_id', '!=', $asset_transfer->assigned_to_id)))
            ->exists();
        abort_if($heldElsewhere, 422, 'An asset on this transfer is assigned to someone else at '.($asset_transfer->toLocation->name ?? 'the destination').'. End that assignment first.');

        DB::transaction(function () use ($asset_transfer, $unitIds, $type, $id) {
            // The previous holder no longer holds these units.
            if ($asset_transfer->assigned_to_type) {
                AssetAssignment::whereIn('asset_id', $unitIds)
                    ->where('assigned_to_type', $asset_transfer->assigned_to_type)
                    ->where('assigned_to_id', $asset_transfer->assigned_to_id)
                    ->whereIn('status', ['assigned', 'active', 'overdue'])
                    ->update(['status' => 'returned']);
            }

            $asset_transfer->update(['assigned_to_type' => $type, 'assigned_to_id' => $id, 'assignment_id' => null]);

            if ($type) {
                // Same per-unit assignment an acceptance creates.
                $this->assignOnReceipt($asset_transfer->fresh());
            }
        });

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Update',
            'description' => 'Changed assignment on transfer of '.($asset_transfer->asset->name ?? 'asset')
                .' to '.($asset_transfer->fresh()->recipient_name ?? 'no one'),
        ]);

        return response()->json($asset_transfer->fresh(self::WITH));
    }

    public function destroy(Request $request, AssetTransfer $asset_transfer)
    {
        abort_unless($this->canDelete($request->user(), $asset_transfer, false), 403, 'Only the person who raised this request, or the Operations & HR Manager, can delete it.');

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
     * A transfer that names a staff member or program is also an assignment,
     * so it takes the same roles as the Assignment form (OPM and Finance). The
     * recipient has to exist and the destination has to be one of their
     * program's schools; this stops a mismatched pair being sent by hand.
     */
    private function assertRecipientAtDestination(User $user, array $validated): void
    {
        abort_unless($user->isOperationsHrManager() || $user->isFinanceManager() || $user->hasCustomPermission('asset-transfers', 'create'), 403, 'Only the Operations & HR Manager or the Finance Manager can transfer an asset to a staff member or program.');

        $recipient = $validated['assigned_to_type'] === 'staff'
            ? Staff::find($validated['assigned_to_id'])
            : Program::find($validated['assigned_to_id']);

        if ($recipient === null) {
            throw ValidationException::withMessages(['assigned_to_id' => 'The selected recipient does not exist.']);
        }

        // The destination must be one of the recipient's schools: a program's
        // linked schools, or a staff member's program's schools (their old
        // single site if they have no program yet). None recorded = allowed.
        $schools = $this->recipientSchoolIds($validated['assigned_to_type'], $recipient);

        if ($schools !== [] && ! in_array((int) $validated['to_location_id'], $schools, true)) {
            throw ValidationException::withMessages([
                'assigned_to_id' => ($recipient->full_name ?? $recipient->name).' does not work at '.(Location::find($validated['to_location_id'])?->name ?? 'the destination').'.',
            ]);
        }
    }

    /** @return int[] the schools a staff member or program covers */
    private function recipientSchoolIds(string $type, Staff|Program $recipient): array
    {
        $programId = $type === 'program' ? $recipient->id : $recipient->program_id;

        if ($programId !== null) {
            return DB::table('location_program')->where('program_id', $programId)
                ->pluck('location_id')->map(fn ($id) => (int) $id)->all();
        }

        return $recipient->location_id !== null ? [(int) $recipient->location_id] : [];
    }

    /**
     * Mark a transfer accepted by $by: accepted codes move to the destination
     * (and are assigned to the named recipient, or — for a return — released
     * from the outbound transfer's recipient); declined codes stay put.
     *
     * $condition is the receiving staff member's verification (new / good /
     * fair); null when HR completes a return, which nobody verifies.
     *
     * @param  int[]  $accepted
     * @param  int[]  $declined
     */
    private function receive(AssetTransfer $transfer, User $by, array $accepted, array $declined, ?string $reason, ?string $condition = null): void
    {
        DB::transaction(function () use ($transfer, $by, $accepted, $declined, $reason, $condition) {
            $transfer->update([
                'status' => 'received',
                'received_by' => $by->id,
                'received_at' => now(),
                // Why some codes were not accepted, when any were left out.
                'rejection_reason' => $declined ? $reason : $transfer->rejection_reason,
                ...($condition !== null ? [
                    'verified_by' => $by->id,
                    'verified_at' => now(),
                    'verification_status' => 'accepted',
                    'received_condition' => $condition,
                ] : []),
            ]);

            if ($transfer->units()->exists()) {
                $transfer->units()->updateExistingPivot($accepted, ['status' => 'accepted']);
                if ($declined) {
                    $transfer->units()->updateExistingPivot($declined, ['status' => 'declined']);
                }
            }

            // Only the accepted codes arrive.
            Asset::whereIn('id', $accepted)->update(['location_id' => $transfer->to_location_id]);

            $this->assignOnReceipt($transfer);
        });
    }

    /**
     * Every ticked unit must be the same model as the chosen asset, sit at the
     * From site, be usable (not disposed, lost or broken), be one the sender
     * may move, not be assigned to anyone there, and not already be
     * travelling on another open transfer.
     *
     * @param  int[]  $unitIds
     */
    private function assertUnitsCanMove(User $user, Asset $model, array $unitIds, int $fromLocationId): void
    {
        $units = Asset::whereIn('id', $unitIds)->get();
        // Assigned units are in use at the From site, not stock it can send.
        $held = $this->stock->heldUnitIds($unitIds);

        $problems = [];
        foreach ($units as $unit) {
            $why = match (true) {
                $unit->name !== $model->name || (int) $unit->category_id !== (int) $model->category_id => 'is a different model',
                ! $user->canAccessAsset($unit) => 'is not at your site',
                (int) $unit->location_id !== $fromLocationId => 'is not at the selected "from" location',
                $unit->status === 'disposed' => 'has been disposed',
                in_array($unit->condition, Asset::UNAVAILABLE_CONDITIONS, true) => 'is recorded as '.$unit->condition,
                in_array((int) $unit->id, $held, true) => 'is assigned to someone',
                AssetTransfer::involvingAsset($unit->id)->whereIn('status', AssetTransfer::OPEN_STATUSES)->exists() => 'already has an open transfer',
                default => null,
            };
            if ($why) {
                $problems[] = "{$unit->asset_code} {$why}";
            }
        }

        if ($problems) {
            throw ValidationException::withMessages(['asset_ids' => 'These assets cannot be sent: '.implode('; ', $problems).'.']);
        }
    }

    /**
     * On acceptance, the units change hands:
     *  - a return leg ends the assignments its outbound transfer created, so
     *    those units are back in stock;
     *  - a transfer that named a staff member or program assigns them its
     *    units — one assignment per ticked asset code, so the register shows
     *    exactly which laptops that person holds. (A transfer raised through
     *    the API with a bare quantity instead of ticked codes gets a single
     *    assignment for that quantity.)
     * Quantities are a pool per model (AssetStockService), so a new assignment
     * adds to what others hold rather than replacing it.
     */
    private function assignOnReceipt(AssetTransfer $transfer): void
    {
        $parent = $transfer->parentTransfer;
        if ($parent?->assigned_to_type) {
            AssetAssignment::whereIn('asset_id', $parent->unitIds())
                ->where('assigned_to_type', $parent->assigned_to_type)
                ->where('assigned_to_id', $parent->assigned_to_id)
                ->whereIn('status', ['assigned', 'active', 'overdue'])
                ->update(['status' => 'returned']);
        }

        if (! $transfer->assigned_to_type) {
            return;
        }

        // One assignment per ACCEPTED code when the transfer lists its codes;
        // declined codes never arrived, so nobody holds them here.
        $perUnit = $transfer->units()->count() === (int) ($transfer->quantity ?? 1);
        $lines = $perUnit ? array_map(fn ($id) => [$id, 1], $transfer->unitIds()) : [[$transfer->asset_id, $transfer->quantity ?? 1]];
        // A unit verified broken or lost is out of use: it keeps its code and
        // its place at the site, but nobody is assigned it.
        $outOfUse = Asset::whereIn('id', array_column($lines, 0))->whereIn('condition', Asset::UNAVAILABLE_CONDITIONS)->pluck('id')->all();
        $lines = array_values(array_filter($lines, fn ($line) => ! in_array($line[0], $outOfUse)));
        if ($lines === []) {
            return;
        }

        $assignment = null;
        foreach ($lines as [$assetId, $quantity]) {
            $created = AssetAssignment::create([
                'asset_id' => $assetId,
                'assigned_to_type' => $transfer->assigned_to_type,
                'assigned_to_id' => $transfer->assigned_to_id,
                'location_id' => $transfer->to_location_id,
                'quantity' => $quantity,
                // The condition the receiving staff member verified.
                'condition' => $transfer->received_condition,
                'assigned_date' => now()->toDateString(),
                'status' => 'assigned',
            ]);
            $assignment ??= $created;
        }

        $transfer->update(['assignment_id' => $assignment->id]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Assign',
            'description' => 'Assigned asset to '.$assignment->recipient_name,
        ]);

        if ($assignment->assigned_to_type === 'staff') {
            User::where('staff_id', $assignment->assigned_to_id)
                ->where('id', '!=', Auth::id())
                ->get()
                ->each(fn (User $holder) => Notification::create([
                    'user_id' => $holder->id,
                    'type' => 'asset_assigned',
                    'message' => ($transfer->asset->name ?? 'An asset').' has been assigned to you.',
                    'url' => '/app/asset-assignments',
                ]));
        }
    }

    /**
     * Refuse to create a transfer nobody at the destination could ever accept.
     *
     * Failing here, loudly, beats letting the row sit pending forever with no
     * indication of why nothing happens. The fix is to give one of that site's
     * programs a responsible staff member who has a login account.
     */
    private function assertDestinationCanReceive(int $locationId, ?string $recipientType = null, ?int $recipientId = null): void
    {
        // A named recipient with a login can answer for it themselves.
        if ($this->confirmerIdsFor($locationId) !== [] || $this->recipientUserIds($recipientType, $recipientId) !== []) {
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

        // One row per (school, program running there) — a program can run at
        // several schools, and its responsible staff answer for every one.
        $staffByLocation = DB::table('location_program')
            ->join('programs', 'programs.id', '=', 'location_program.program_id')
            ->whereIn('location_program.location_id', $locationIds)
            ->whereNotNull('programs.responsible_staff_id')
            ->get(['location_program.location_id', 'programs.responsible_staff_id']);

        // A locked or deactivated account cannot sign in to accept anything,
        // so it must not count as someone who can answer for a site — nor can
        // an HR or Accountant account, which never accepts (canReceive()).
        $userIdsByStaff = User::whereIn('staff_id', $staffByLocation->pluck('responsible_staff_id')->unique())
            ->where('is_active', true)
            ->where('is_locked', false)
            ->whereNotIn('role', ['operations_hr_manager', 'finance_manager'])
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

    /** Requester or OPM, and (when $checkStatus) only while the request is deletable. */
    private function canDelete(User $user, AssetTransfer $transfer, bool $checkStatus = true): bool
    {
        if ($checkStatus && ! in_array($transfer->status, ['pending_approval', 'rejected'], true)) {
            return false;
        }

        return $user->isAdministrator() || $user->hasCustomPermission('asset-transfers', 'delete')
            || (int) $transfer->requested_by === (int) $user->id;
    }

    /**
     * Who may accept or reject: the destination's responsible staff, or the
     * staff member the transfer names as recipient (for a program recipient,
     * that program's responsible staff) — so the person receiving the laptops
     * can review the asset codes and answer for them.
     */
    private function canReceive(User $user, AssetTransfer $transfer): bool
    {
        // HR and the Accountant send and return; they never accept or reject.
        if ($user->isAdministrator()) {
            return false;
        }

        return in_array($user->id, $this->confirmerIdsFor($transfer->to_location_id))
            || $this->isRecipient($user, $transfer);
    }

    /**
     * Returns (and the Edit dialog) are HR's or the Accountant's — or anyone
     * a custom role grants Transfers → Update. Deliberately separate from the
     * "office never accepts" rule in canReceive(), which stays role-based.
     */
    private function canReturn(User $user): bool
    {
        return $user->isAdministrator() || $user->hasCustomPermission('asset-transfers', 'update');
    }

    private function isRecipient(User $user, AssetTransfer $transfer): bool
    {
        return match ($transfer->assigned_to_type) {
            'staff' => $user->staff_id !== null && (int) $user->staff_id === (int) $transfer->assigned_to_id,
            'program' => in_array((int) $transfer->assigned_to_id, $user->ledProgramIds(), true),
            default => false,
        };
    }

    /** @return int[] active, unlocked login accounts of a transfer's named recipient */
    private function recipientUserIds(?string $type, ?int $id): array
    {
        $staffId = match ($type) {
            'staff' => $id,
            'program' => Program::whereKey($id)->value('responsible_staff_id'),
            default => null,
        };

        if ($staffId === null) {
            return [];
        }

        return User::where('staff_id', $staffId)->where('is_active', true)->where('is_locked', false)
            ->whereNotIn('role', ['operations_hr_manager', 'finance_manager'])
            ->pluck('id')->map(fn ($uid) => (int) $uid)->all();
    }

    /** Tell whoever can answer — the destination's responsible staff and the named recipient. */
    private function notifyDestination(AssetTransfer $transfer, string $type = 'transfer_incoming'): void
    {
        $count = (int) ($transfer->quantity ?? 1);
        $message = ($transfer->asset->name ?? 'An asset').($count > 1 ? " ×{$count}" : '').' is being transferred to '
            .($transfer->recipient_name ?? $transfer->toLocation->name ?? 'your site').' — review the asset codes, then accept or reject it.';

        $userIds = array_unique([
            ...$this->confirmerIdsFor($transfer->to_location_id),
            ...$this->recipientUserIds($transfer->assigned_to_type, $transfer->assigned_to_id),
        ]);

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'message' => $message,
                'url' => '/app/asset-transfers',
            ]);
        }
    }

    /** Who answered, as the notification shows it: their staff name, else the login's name. */
    private function personName(User $user): string
    {
        return $user->staff?->full_name ?: $user->name;
    }

    /** Tell OPM a request is waiting on their approval. */
    private function notifyApprovers(AssetTransfer $transfer): void
    {
        User::whereIn('role', ['operations_hr_manager', 'finance_manager', 'executive_director'])
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
