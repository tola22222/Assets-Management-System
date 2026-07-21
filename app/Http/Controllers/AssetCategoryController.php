<?php

namespace App\Http\Controllers;
use App\Models\AssetCategory;
use App\Services\AssetCodeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetCategoryController extends Controller
{
    public function index()
    {
        $categories = AssetCategory::latest()->get();
        return view('categories.index', ['categories' => $categories, 'categoryCodes' => AssetCodeService::CATEGORY_CODES]);
    }

    public function create()
    {
        return view('categories.create', ['categoryCodes' => AssetCodeService::CATEGORY_CODES]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_name' => ['nullable', 'string', Rule::in(AssetCodeService::CATEGORY_CODES)],
        ]);

        AssetCategory::create($validated);

        return redirect()->route('categories.index')->with('success', 'Category created successfully.');
    }

    public function show(AssetCategory $assetCategory)
    {
        return view('categories.show', ['category' => $assetCategory]);
    }

    public function edit(AssetCategory $assetCategory)
    {
        return view('categories.edit', ['category' => $assetCategory, 'categoryCodes' => AssetCodeService::CATEGORY_CODES]);
    }

    public function update(Request $request, $id) // Using ID for maximum reliability with custom naming
{
    $category = AssetCategory::findOrFail($id);

    $validated = $request->validate([
        'name' => 'required|string|max:255|unique:asset_categories,name,' . $id,
        'short_name' => ['nullable', 'string', Rule::in(AssetCodeService::CATEGORY_CODES)],
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