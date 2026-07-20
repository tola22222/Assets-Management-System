<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetDisposal;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetDisposalController extends Controller
{
    public function index()
    {
        return response()->json(
            AssetDisposal::with(['asset', 'requester', 'reviewer'])->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'recommended_action' => 'required|in:repair,disposal,replacement',
            'reason' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

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
                'message' => 'Disposal request submitted for ' . ($disposal->asset->name ?? 'an asset'),
                'url' => null,
            ]);
        });

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Requested ' . $validated['recommended_action'] . ' for asset ' . ($disposal->asset->name ?? ''),
        ]);

        return response()->json($disposal->fresh(['asset', 'requester']), 201);
    }

    public function approve(AssetDisposal $asset_disposal)
    {
        abort_unless(Auth::user()->canApproveDisposal(), 403, 'Only the Executive Director can approve disposal requests.');

        $asset_disposal->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        if ($asset_disposal->recommended_action === 'disposal') {
            $asset_disposal->asset->update(['status' => 'disposed']);
        }

        Notification::create([
            'user_id' => $asset_disposal->requested_by,
            'type' => 'disposal_approved',
            'message' => 'Your disposal request for ' . ($asset_disposal->asset->name ?? 'an asset') . ' has been approved.',
            'url' => null,
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved ' . $asset_disposal->recommended_action . ' for asset ' . ($asset_disposal->asset->name ?? ''),
        ]);

        return response()->json($asset_disposal->fresh(['asset', 'reviewer']));
    }

    public function reject(Request $request, AssetDisposal $asset_disposal)
    {
        abort_unless(Auth::user()->canApproveDisposal(), 403, 'Only the Executive Director can reject disposal requests.');

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
            'message' => 'Your disposal request for ' . ($asset_disposal->asset->name ?? 'an asset') . ' has been rejected.',
            'url' => null,
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Reject',
            'description' => 'Rejected ' . $asset_disposal->recommended_action . ' for asset ' . ($asset_disposal->asset->name ?? ''),
        ]);

        return response()->json($asset_disposal->fresh());
    }

    public function destroy(AssetDisposal $asset_disposal)
    {
        if ($asset_disposal->status !== 'pending') {
            return response()->json(['message' => 'Cannot delete a reviewed disposal request.'], 422);
        }
        $asset_disposal->delete();
        return response()->json(['message' => 'Disposal request deleted.']);
    }
}
