<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetVerification;
use App\Models\AssetAssignment;
use App\Models\Location;
use App\Models\ActivityLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetVerificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->isOperationsHrManager()) {
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
        $assets = Asset::where('status', 'active')->get();
        $locations = Location::all();
        return view('asset-verifications.index', compact('verifications', 'assets', 'locations'));
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

        if ($validated['condition'] === 'broken' || $validated['condition'] === 'lost') {
            $asset = Asset::find($validated['asset_id']);
            if ($asset) {
                $asset->update(['condition' => $validated['condition']]);
            }
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
            'url' => route('asset-verifications.index'),
        ]);

        return redirect()->route('asset-verifications.index')->with('success', 'Verification completed successfully.');
    }

    public function show(AssetVerification $assetVerification)
    {
        $assetVerification->load(['asset', 'location', 'verifiedBy']);
        return view('asset-verifications.show', compact('assetVerification'));
    }

    public function complete(AssetVerification $assetVerification)
    {
        $assetVerification->update(['verified_at' => now()]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Complete Verification',
            'description' => 'Completed verification',
        ]);

        return redirect()->route('asset-verifications.index')->with('success', 'Verification completed.');
    }

    public function destroy(AssetVerification $assetVerification)
    {
        $assetVerification->delete();
        return redirect()->route('asset-verifications.index')->with('success', 'Verification deleted.');
    }
}
