<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetVerification;
use App\Models\Notification;
use App\Models\Location;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrScanController extends Controller
{
    public function index()
    {
        return view('qr-scan.index');
    }

    public function scan(Request $request)
    {
        $request->validate(['asset_code' => 'required|string']);

        $asset = Asset::with(['category', 'location'])
            ->where('asset_code', $request->asset_code)
            ->first();

        if (!$asset) {
            return redirect()->route('qr-scan.index')->with('error', 'Asset not found.');
        }

        // Log the scan
        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'qr_scan',
            'message' => 'QR scanned: ' . $asset->name . ' (' . $asset->asset_code . ')',
            'url' => route('qr-scan.result', $asset->asset_code),
        ]);

        return redirect()->route('qr-scan.result', $asset->asset_code);
    }

    public function result($assetCode)
    {
        $asset = Asset::with(['category', 'location', 'assignments' => function ($q) {
            $q->where('status', 'active');
        }, 'verifications' => function ($q) {
            $q->latest();
        }])->where('asset_code', $assetCode)->firstOrFail();

        $locations = Location::all();
        return view('qr-scan.result', compact('asset', 'locations'));
    }

    public function verify(Request $request, $assetCode)
    {
        $asset = Asset::where('asset_code', $assetCode)->firstOrFail();

        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string',
        ]);

        AssetVerification::create([
            'asset_id' => $asset->id,
            'location_id' => $validated['location_id'],
            'verified_by' => Auth::id(),
            'quantity_verified' => 1,
            'condition' => $validated['condition'],
            'remark' => $validated['remark'] ?? null,
            'verified_at' => now(),
        ]);

        if (in_array($validated['condition'], ['broken', 'lost'])) {
            $asset->update(['condition' => $validated['condition']]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'QR Verification',
            'description' => 'Verified asset via QR scan: ' . $asset->name . ' (' . $asset->asset_code . ')',
        ]);

        return redirect()->route('qr-scan.result', $assetCode)
            ->with('success', 'Asset verified successfully via QR scan.');
    }
}
