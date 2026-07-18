<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetVerification;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetVerificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $verifications = AssetVerification::with(['asset', 'location', 'verifiedBy'])->latest()->get();
        } else {
            $assignedAssetIds = AssetAssignment::where('assigned_to_type', 'staff')
                ->where('assigned_to_id', $user->staff_id)
                ->pluck('asset_id');
            $verifications = AssetVerification::whereIn('asset_id', $assignedAssetIds)
                ->with(['asset', 'location', 'verifiedBy'])
                ->latest()
                ->get();
        }

        return response()->json($verifications);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'location_id' => 'required|exists:locations,id',
            'quantity_verified' => 'required|integer|min:1',
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $validated['verified_by'] = Auth::id();
        $validated['verified_at'] = now();

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('verifications', 'public');
        }

        $verification = AssetVerification::create($validated);

        if (in_array($validated['condition'], ['broken', 'lost'])) {
            Asset::where('id', $validated['asset_id'])->update(['condition' => $validated['condition']]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Verification',
            'description' => 'Verified asset: ' . ($verification->asset->name ?? ''),
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_verified',
            'message' => 'Verified asset: ' . ($verification->asset->name ?? '') . ' (' . $validated['condition'] . ')',
            'url' => null,
        ]);

        return response()->json($verification->fresh(['asset', 'location']), 201);
    }

    public function complete(AssetVerification $asset_verification)
    {
        $asset_verification->update(['verified_at' => now()]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Complete Verification',
            'description' => 'Completed verification',
        ]);

        return response()->json($asset_verification->fresh());
    }

    public function destroy(AssetVerification $asset_verification)
    {
        $asset_verification->delete();
        return response()->json(['message' => 'Verification deleted.']);
    }
}
