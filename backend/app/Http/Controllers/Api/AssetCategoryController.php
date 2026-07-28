<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetCategory;
use App\Services\AssetCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AssetCategoryController extends Controller
{
    public function index()
    {
        return response()->json(AssetCategory::withCount('assets')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $request->merge(['short_name' => $this->normalizeCode($request->short_name)]);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name',
            'short_name' => ['nullable', 'regex:'.AssetCodeService::CODE_FORMAT, Rule::unique('asset_categories', 'short_name')],
            'description' => 'nullable|string',
        ], [
            'short_name.regex' => 'Short code must be 2-6 letters or numbers (e.g. MOV, ELEC).',
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
        $request->merge(['short_name' => $this->normalizeCode($request->short_name)]);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name,' . $category->id,
            'short_name' => ['nullable', 'regex:'.AssetCodeService::CODE_FORMAT, Rule::unique('asset_categories', 'short_name')->ignore($category->id)],
            'description' => 'nullable|string',
        ], [
            'short_name.regex' => 'Short code must be 2-6 letters or numbers (e.g. MOV, ELEC).',
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

    private function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }
}
