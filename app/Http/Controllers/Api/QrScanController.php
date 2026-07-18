<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetVerification;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrScanController extends Controller
{
    public function scan(Request $request)
    {
        $request->validate(['asset_code' => 'required|string']);

        $asset = Asset::with(['category', 'stocks.location'])
            ->where('asset_code', $request->asset_code)
            ->first();

        if (!$asset) {
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'qr_scan',
            'message' => 'QR scanned: ' . $asset->name . ' (' . $asset->asset_code . ')',
            'url' => null,
        ]);

        return response()->json($asset);
    }

    public function result($assetCode)
    {
        $asset = Asset::with([
            'category', 'stocks.location',
            'assignments' => fn ($q) => $q->where('status', 'active'),
            'verifications' => fn ($q) => $q->latest(),
        ])->where('asset_code', $assetCode)->firstOrFail();

        return response()->json($asset);
    }

    public function verify(Request $request, $assetCode)
    {
        $asset = Asset::where('asset_code', $assetCode)->firstOrFail();

        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string',
        ]);

        $verification = AssetVerification::create([
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

        return response()->json($verification->fresh(['asset', 'location']));
    }
}
