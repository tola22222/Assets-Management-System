<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetDisposalController extends Controller
{
    public function index()
    {
        $disposals = AssetDisposal::with(['asset', 'requester', 'reviewer'])
            ->latest()
            ->get();
        $assets = Asset::where('status', 'active')->get();
        return view('asset-disposals.index', compact('disposals', 'assets'));
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
            return back()->with('error', 'This asset already has a pending disposal request.');
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
                'url' => route('asset-disposals.index'),
            ]);
        });

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Requested ' . $validated['recommended_action'] . ' for asset ' . ($disposal->asset->name ?? ''),
        ]);

        return redirect()->route('asset-disposals.index')->with('success', 'Disposal request submitted for Executive Director approval.');
    }

    public function approve(AssetDisposal $assetDisposal)
    {
        abort_unless(Auth::user()->canApproveDisposal(), 403, 'Only the Executive Director can approve disposal requests.');

        $assetDisposal->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        if ($assetDisposal->recommended_action === 'disposal') {
            $assetDisposal->asset->update(['status' => 'disposed']);
        }

        Notification::create([
            'user_id' => $assetDisposal->requested_by,
            'type' => 'disposal_approved',
            'message' => 'Your disposal request for ' . ($assetDisposal->asset->name ?? 'an asset') . ' has been approved.',
            'url' => route('asset-disposals.index'),
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved ' . $assetDisposal->recommended_action . ' for asset ' . ($assetDisposal->asset->name ?? ''),
        ]);

        return redirect()->route('asset-disposals.index')->with('success', 'Disposal request approved.');
    }

    public function reject(Request $request, AssetDisposal $assetDisposal)
    {
        abort_unless(Auth::user()->canApproveDisposal(), 403, 'Only the Executive Director can reject disposal requests.');

        $validated = $request->validate([
            'review_notes' => 'nullable|string',
        ]);

        $assetDisposal->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        Notification::create([
            'user_id' => $assetDisposal->requested_by,
            'type' => 'disposal_rejected',
            'message' => 'Your disposal request for ' . ($assetDisposal->asset->name ?? 'an asset') . ' has been rejected.',
            'url' => route('asset-disposals.index'),
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Reject',
            'description' => 'Rejected ' . $assetDisposal->recommended_action . ' for asset ' . ($assetDisposal->asset->name ?? ''),
        ]);

        return redirect()->route('asset-disposals.index')->with('success', 'Disposal request rejected.');
    }

    public function destroy(AssetDisposal $assetDisposal)
    {
        if ($assetDisposal->status !== 'pending') {
            return redirect()->route('asset-disposals.index')->with('error', 'Cannot delete a reviewed disposal request.');
        }
        $assetDisposal->delete();
        return redirect()->route('asset-disposals.index')->with('success', 'Disposal request deleted.');
    }
}
