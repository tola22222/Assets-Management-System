<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\Notification;
use App\Models\User;
use App\Services\AssetNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetDisposalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Staff see requests about assets at their own site only.
        $disposals = AssetDisposal::with(['asset', 'requester', 'reviewer'])
            ->when($user->isSiteScoped(), fn ($q) => $q->whereHas('asset', fn ($a) => $a->visibleTo($user)))
            ->latest()
            ->get()
            ->each(function (AssetDisposal $disposal) use ($user) {
                // UX hints only — every action re-checks server-side.
                $disposal->can_delete = $disposal->status === 'pending' && $this->isOwnerOrOpm($user, $disposal);
                $disposal->can_review = $disposal->status === 'pending' && $user->canApproveDisposal()
                    && (int) $disposal->requested_by !== (int) $user->id;
            });

        return response()->json($disposals);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'recommended_action' => 'required|in:repair,disposal,replacement',
            'reason' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $asset = Asset::findOrFail($validated['asset_id']);

        // Staff may only raise a request about something at their own site.
        abort_unless($request->user()->canAccessLocation($asset->location_id), 403, 'You can only submit requests for assets at your own site.');

        if ($asset->status === 'disposed') {
            return response()->json(['message' => 'This asset has already been disposed.'], 422);
        }

        if (AssetDisposal::where('asset_id', $validated['asset_id'])->pending()->exists()) {
            return response()->json(['message' => 'This asset already has a pending disposal request.'], 422);
        }

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('disposals', 'public');
        }

        $validated['requested_by'] = Auth::id();
        $disposal = AssetDisposal::create($validated);

        User::where(function ($q) {
            $q->where('role', 'operations_hr_manager')->orWhere('role', 'executive_director');
        })->get()->each(function ($approver) use ($disposal) {
            Notification::create([
                'user_id' => $approver->id,
                'type' => 'disposal_request',
                'message' => 'Disposal request submitted for '.($disposal->asset->name ?? 'an asset'),
                'url' => null,
            ]);
        });

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Requested '.$validated['recommended_action'].' for asset '.($disposal->asset->name ?? ''),
        ]);

        (new AssetNotificationService)->send('DISPOSAL_REQUEST', [
            'assetId' => $disposal->asset->asset_code ?? null,
            'assetDbId' => $disposal->asset_id,
            'description' => $disposal->asset->name ?? null,
            'category' => $disposal->asset->category->name ?? null,
            'location' => $disposal->asset->location->name ?? null,
            'note' => $validated['reason'],
            'url' => route('asset.public.show', $disposal->asset->asset_code ?? ''),
            'extraData' => [
                'recommendedAction' => $disposal->recommended_action,
                'requestedBy' => Auth::user()->name,
            ],
        ]);

        return response()->json($disposal->fresh(['asset', 'requester']), 201);
    }

    public function approve(AssetDisposal $asset_disposal)
    {
        abort_unless(Auth::user()->canApproveDisposal(), 403, 'Only the Executive Director can approve disposal requests.');
        $this->assertReviewable($asset_disposal);

        $asset = $asset_disposal->asset;

        // A write-off while the asset is mid-transfer would leave the
        // receiving site accepting something that no longer exists.
        if ($asset_disposal->recommended_action === 'disposal'
            && AssetTransfer::where('asset_id', $asset_disposal->asset_id)->whereIn('status', AssetTransfer::OPEN_STATUSES)->exists()) {
            abort(422, 'This asset has an open transfer. It must be accepted or rejected before the asset can be disposed.');
        }

        $asset_disposal->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        if ($asset && $asset_disposal->recommended_action === 'disposal') {
            $asset->update(['status' => 'disposed']);

            // A written-off asset is no longer with anyone.
            AssetAssignment::where('asset_id', $asset->id)
                ->whereIn('status', AssetAssignment::CURRENT_STATUSES)
                ->update(['status' => 'returned']);
        } elseif ($asset && $asset->condition !== 'lost') {
            // Repair / replacement approved: the asset needs attention until
            // OPM records it as repaired, so it shows on the dashboard.
            $asset->update(['condition' => 'broken']);
        }

        Notification::create([
            'user_id' => $asset_disposal->requested_by,
            'type' => 'disposal_approved',
            'message' => 'Your disposal request for '.($asset_disposal->asset->name ?? 'an asset').' has been approved.',
            'url' => null,
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved '.$asset_disposal->recommended_action.' for asset '.($asset_disposal->asset->name ?? ''),
        ]);

        return response()->json($asset_disposal->fresh(['asset', 'reviewer']));
    }

    public function reject(Request $request, AssetDisposal $asset_disposal)
    {
        abort_unless(Auth::user()->canApproveDisposal(), 403, 'Only the Executive Director can reject disposal requests.');
        $this->assertReviewable($asset_disposal);

        $validated = $request->validate([
            'review_notes' => 'nullable|string',
        ]);

        $asset_disposal->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        Notification::create([
            'user_id' => $asset_disposal->requested_by,
            'type' => 'disposal_rejected',
            'message' => 'Your disposal request for '.($asset_disposal->asset->name ?? 'an asset').' has been rejected.',
            'url' => null,
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Reject',
            'description' => 'Rejected '.$asset_disposal->recommended_action.' for asset '.($asset_disposal->asset->name ?? ''),
        ]);

        return response()->json($asset_disposal->fresh());
    }

    public function destroy(Request $request, AssetDisposal $asset_disposal)
    {
        abort_unless($this->isOwnerOrOpm($request->user(), $asset_disposal), 403, 'Only the person who submitted this request, or the Operations & HR Manager, can delete it.');

        if ($asset_disposal->status !== 'pending') {
            return response()->json(['message' => 'Cannot delete a reviewed disposal request.'], 422);
        }
        $asset_disposal->delete();

        return response()->json(['message' => 'Disposal request deleted.']);
    }

    /**
     * A decision is final: only a pending request can be approved or
     * rejected, and never by the person who submitted it — the manual's
     * independent review would mean nothing otherwise.
     */
    private function assertReviewable(AssetDisposal $disposal): void
    {
        abort_unless($disposal->status === 'pending', 422, 'This request has already been reviewed.');
        abort_if((int) $disposal->requested_by === (int) Auth::id(), 403, 'You cannot review your own disposal request.');
    }

    private function isOwnerOrOpm(User $user, AssetDisposal $disposal): bool
    {
        return $user->isOperationsHrManager() || (int) $disposal->requested_by === (int) $user->id;
    }
}
