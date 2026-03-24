<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetStockApiController extends Controller
{
    // List all stock levels with Asset and Location names
    public function index()
    {
        $stocks = AssetStock::with(['asset', 'location'])->latest()->get();
        return response()->json(['status' => true, 'data' => $stocks]);
    }

    // Add or Increase Stock (The "Receive" logic)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id'    => 'required|exists:assets,id',
            'location_id' => 'required|exists:locations,id',
            'quantity'    => 'required|integer|min:1'
        ]);

        // 1. Find existing record or create a new one with 0 qty
        $stock = AssetStock::firstOrCreate(
            [
                'asset_id'    => $validated['asset_id'],
                'location_id' => $validated['location_id']
            ],
            ['quantity' => 0]
        );

        // 2. Increment safely at the Database level
        $stock->increment('quantity', $validated['quantity']);

        return response()->json([
            'status' => true,
            'message' => 'Stock updated successfully.',
            'data' => $stock->refresh() // Refresh to show NEW total to Flutter
        ], 201);
    }

    // Manual Edit (Overwriting a mistake)
    public function update(Request $request, $id)
    {
        $stock = AssetStock::findOrFail($id);
        $validated = $request->validate(['quantity' => 'required|integer|min:0']);
        
        $stock->update(['quantity' => $validated['quantity']]);
        
        return response()->json(['status' => true, 'data' => $stock]);
    }

    public function destroy($id)
    {
        AssetStock::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Stock record deleted.']);
    }
}