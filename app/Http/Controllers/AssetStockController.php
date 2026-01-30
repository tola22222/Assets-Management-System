<?php

namespace App\Http\Controllers;

use App\Models\AssetStock;
use App\Models\Asset; // Assuming you have this
use App\Models\Location; // Assuming you have this
use Illuminate\Http\Request;

class AssetStockController extends Controller
{
   public function index()
    {
        // We eager load 'asset' and 'location' relationships to prevent errors
        $stocks = AssetStock::with(['asset', 'location'])->latest()->get();
        
        // We need these for the "Add New" dropdowns in your modal
        $assets = Asset::all();
        $locations = Location::all();

        return view('asset-stocks.index', compact('stocks', 'assets', 'locations'));
    }

   public function store(Request $request)
{
    $validated = $request->validate([
        'asset_id' => 'required|exists:assets,id',
        'location_id' => 'required|exists:locations,id',
        'quantity' => 'required|integer|min:0'
    ]);

    // Check if this asset already exists at this location
    $stock = AssetStock::where('asset_id', $validated['asset_id'])
                       ->where('location_id', $validated['location_id'])
                       ->first();

    if ($stock) {
        // Increment the existing quantity
        $stock->increment('quantity', $validated['quantity']);
        $message = 'Stock quantity updated successfully.';
    } else {
        // Create a new record if it doesn't exist
        AssetStock::create($validated);
        $message = 'New stock record created successfully.';
    }

    return redirect()->back()->with('success', $message);
}

    public function update(Request $request, AssetStock $asset_stock)
    {
        $validated = $request->validate([
            'asset_id' => 'required',
            'location_id' => 'required',
            'quantity' => 'required|integer|min:0',
        ]);

        $asset_stock->update($validated);
        return redirect()->back()->with('success', 'Stock updated.');
    }

    public function destroy(AssetStock $asset_stock)
    {
        $asset_stock->delete();
        return redirect()->back()->with('success', 'Stock record removed.');
    }
}
