<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Location;
use App\Models\AssetVerification;
use Illuminate\Http\Request;

class AssetVerificationController extends Controller
{
    public function index()
    {
        $verifications = AssetVerification::with(['asset', 'location'])
            ->orderBy('verified_at', 'desc')
            ->paginate(15);

        return view('asset-verifications.index', compact('verifications'));
    }

    public function create()
    {
        $assets = Asset::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        
        return view('asset-verifications.create', compact('assets', 'locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'location_id' => 'required|exists:locations,id',
            'verified_by' => 'required|string|max:255',
            'quantity_verified' => 'required|integer|min:0',
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string',
            'verified_at' => 'required|date',
        ]);

        AssetVerification::create($validated);

        return redirect()->route('asset-verifications.index')
            ->with('success', 'Asset verification recorded and condition updated.');
    }
}