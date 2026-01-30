<?php

namespace App\Http\Controllers;
use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    public function index()
    {
        $categories = AssetCategory::latest()->get();
        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        AssetCategory::create($validated);

        return redirect()->route('categories.index')->with('success', 'Category created successfully.');
    }

    public function show(AssetCategory $assetCategory)
    {
        return view('categories.show', compact('assetCategory'));
    }

    public function edit(AssetCategory $assetCategory)
    {
        return view('categories.edit', compact('assetCategory'));
    }

    public function update(Request $request, $id) // Using ID for maximum reliability with custom naming
{
    $category = AssetCategory::findOrFail($id);
    
    $validated = $request->validate([
        'name' => 'required|string|max:255|unique:asset_categories,name,' . $id,
        'description' => 'nullable|string',
    ]);

    $category->update($validated);

    return redirect()->route('categories.index')->with('success', 'Category updated successfully.');
}
    public function destroy(AssetCategory $assetCategory)
    {
        $assetCategory->delete();
        return redirect()->route('categories.index')->with('success', 'Category deleted successfully.');
    }
}