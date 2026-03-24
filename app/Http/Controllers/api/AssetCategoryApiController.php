<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetCategoryApiController extends Controller
{
    public function index()
    {
        $categories = AssetCategory::latest()->get();
        return response()->json([
            'status' => true,
            'data' => $categories
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories',
            'description' => 'nullable|string',
            'short_name' => 'nullable|string|max:10',
        ]);

        $category = AssetCategory::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully.',
            'data' => $category
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $category = AssetCategory::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name,' . $id,
            'short_name' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully.',
            'data' => $category
        ], 200);
    }

    public function destroy($id)
    {
        $category = AssetCategory::findOrFail($id);
        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully.'
        ], 200);
    }
}