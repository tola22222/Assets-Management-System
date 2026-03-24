<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetApiController extends Controller
{
    public function index()
    {
        $assets = Asset::with('category')->latest()->get();
        return response()->json(['status' => true, 'data' => $assets]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category_id'   => 'required|exists:asset_categories,id',
            'purchase_date' => 'nullable|date',
            'purchase_price'=> 'nullable|numeric',
            'status'        => 'required|string',
            'description'   => 'nullable|string',
            'model'         => 'nullable|string',
            'brand'         => 'nullable|string',
            'serial_number' => 'nullable|string',
            'condition'     => 'nullable|string',
        ]);

        // Logic to generate Asset Code: PEY-SHORTNAME-0001
        $category = AssetCategory::findOrFail($validated['category_id']);
        $shortName = $category->short_name ?? 'GEN';
        $count = Asset::where('category_id', $category->id)->count() + 1;
        
        $validated['asset_code'] = 'PEY-' . strtoupper($shortName) . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $asset = Asset::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Asset registered successfully.',
            'data' => $asset
        ], 201);
    }

    public function show($id)
    {
        $asset = Asset::with('category')->findOrFail($id);
        return response()->json(['status' => true, 'data' => $asset]);
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category_id'   => 'required|exists:asset_categories,id',
            'purchase_date' => 'nullable|date',
            'purchase_price'=> 'nullable|numeric',
            'status'        => 'required|string',
            'description'   => 'nullable|string',
            'model'         => 'nullable|string',
            'brand'         => 'nullable|string',
            'serial_number' => 'nullable|string',
            'condition'     => 'nullable|string',
        ]);

        $asset->update($validated);
        return response()->json(['status' => true, 'message' => 'Asset updated successfully.', 'data' => $asset]);
    }

    public function destroy($id)
    {
        Asset::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Asset deleted successfully.']);
    }
}