<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index()
    {
        $assets = Asset::with('category')->latest()->get();
        $categories = AssetCategory::orderBy('name')->get();
        return view('assets.index', compact('assets', 'categories'));
    }

    public function create()
    {
        $categories = \App\Models\AssetCategory::orderBy('name')->get();
        return view('assets.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_code' => 'required|unique:assets',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'status' => 'required|string',
            // Add other validations as needed
        ]);

        Asset::create($request->all());
        return redirect()->route('assets.index')->with('success', 'Asset registered successfully.');
    }

    public function edit($id)
    {
        $asset = Asset::findOrFail($id);
        $categories = AssetCategory::all();
        return view('assets.edit', compact('asset', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $validated = $request->validate([
            'asset_code' => 'required|unique:assets,asset_code,' . $id,
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
        ]);

        $asset->update($request->all());
        return redirect()->route('assets.index')->with('success', 'Asset updated successfully.');
    }

    public function destroy($id)
    {
        $asset = Asset::findOrFail($id);
        $asset->delete();
        return redirect()->route('assets.index');
    }
}
