<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetCategoryController extends Controller
{
    public function index()
    {
        return response()->json(AssetCategory::withCount('assets')->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name',
            'short_name' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        $category = AssetCategory::create($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created category: ' . $category->name,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, AssetCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name,' . $category->id,
            'short_name' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated category: ' . $category->name,
        ]);

        return response()->json($category);
    }

    public function destroy(AssetCategory $category)
    {
        $category->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted category: ' . $category->name,
        ]);

        return response()->json(['message' => 'Category deleted.']);
    }
}
