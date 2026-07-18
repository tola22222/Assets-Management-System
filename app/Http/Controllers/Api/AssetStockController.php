<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetStock;
use Illuminate\Http\Request;

class AssetStockController extends Controller
{
    public function index()
    {
        return response()->json(AssetStock::with(['asset', 'location'])->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $stock = AssetStock::where('asset_id', $validated['asset_id'])
            ->where('location_id', $validated['location_id'])
            ->first();

        if ($stock) {
            $stock->increment('quantity', $validated['quantity']);
        } else {
            $stock = AssetStock::create($validated);
        }

        return response()->json($stock->load(['asset', 'location']), 201);
    }

    public function update(Request $request, AssetStock $asset_stock)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $asset_stock->update($validated);

        return response()->json($asset_stock->load(['asset', 'location']));
    }

    public function destroy(AssetStock $asset_stock)
    {
        $asset_stock->delete();
        return response()->json(['message' => 'Stock record removed.']);
    }
}
