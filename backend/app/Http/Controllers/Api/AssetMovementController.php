<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetMovement;

class AssetMovementController extends Controller
{
    public function index()
    {
        return response()->json(
            AssetMovement::with(['asset', 'fromLocation', 'toLocation'])->latest()->get()
        );
    }

    public function destroy(AssetMovement $asset_movement)
    {
        $asset_movement->delete();
        return response()->json(['message' => 'Movement record deleted.']);
    }
}
