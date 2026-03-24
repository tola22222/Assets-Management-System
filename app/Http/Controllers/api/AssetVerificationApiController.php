<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetVerificationApiController extends Controller
{
    public function index()
    {
        $verifications = AssetVerification::with(['asset', 'location'])
            ->latest('verified_at')
            ->get();

        return response()->json(['status' => true, 'data' => $verifications]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id'          => 'required|exists:assets,id',
            'location_id'       => 'required|exists:locations,id',
            'verified_by'       => 'required|string|max:255',
            'quantity_verified' => 'required|integer|min:0',
            'condition'         => 'required|in:good,fair,broken,lost',
            'remark'            => 'nullable|string',
            'verified_at'       => 'required|date',
        ]);

        $verification = AssetVerification::create($validated);

        // Logic: Automatically update the main Asset's condition based on this report
        Asset::where('id', $validated['asset_id'])->update([
            'condition' => $validated['condition']
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Verification recorded and asset condition updated.',
            'data' => $verification
        ], 201);
    }
}