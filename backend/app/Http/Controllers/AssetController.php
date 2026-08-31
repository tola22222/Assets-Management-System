<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetVerification;
use App\Models\Location;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function publicShow($assetCode)
    {
        $asset = Asset::with(['category', 'location', 'assignments' => function ($q) {
            $q->with('assignee')->latest();
        }])->where('asset_code', $assetCode)->firstOrFail();
        $locations = Location::all();

        return view('assets.public-show', compact('asset', 'locations'));
    }

    public function publicUpdateCondition(Request $request, $assetCode)
    {
        $asset = Asset::where('asset_code', $assetCode)->firstOrFail();

        $validated = $request->validate([
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string|max:500',
        ]);

        $asset->update(['condition' => $validated['condition']]);

        AssetVerification::create([
            'asset_id' => $asset->id,
            'verified_by' => 'Public Scan',
            'location_id' => $request->location_id,
            'quantity_verified' => 1,
            'condition' => $validated['condition'],
            'remark' => $validated['remark'] ?? null,
            'verified_at' => now(),
        ]);

        return redirect()->route('asset.public.show', $assetCode)
            ->with('success', 'Condition updated successfully.');
    }
}
