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
        $categories = AssetCategory::orderBy('name')->get();
        return view('categories.index', ['categories' => $categories, 'categoryCodes' => AssetCodeService::CATEGORY_CODES]);
    }

    public function create()
    {
        return view('categories.create', ['categoryCodes' => AssetCodeService::CATEGORY_CODES]);
    }

    public function store(Request $request)
    {
        $request->merge(['short_name' => $this->normalizeCode($request->short_name)]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_name' => ['nullable', 'regex:'.AssetCodeService::CODE_FORMAT, Rule::unique('asset_categories', 'short_name')],
        ], [
            'short_name.regex' => 'Short code must be 2-6 letters or numbers (e.g. MOV, ELEC).',
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

    $request->merge(['short_name' => $this->normalizeCode($request->short_name)]);

    $validated = $request->validate([
        'name' => 'required|string|max:255|unique:asset_categories,name,' . $id,
        'short_name' => ['nullable', 'regex:'.AssetCodeService::CODE_FORMAT, Rule::unique('asset_categories', 'short_name')->ignore($id)],
        'description' => 'nullable|string',
    ], [
        'short_name.regex' => 'Short code must be 2-6 letters or numbers (e.g. MOV, ELEC).',
    ]);

    $category->update($validated);

    return redirect()->route('categories.index')->with('success', 'Category updated successfully.');
}
    public function destroy(AssetCategory $assetCategory)
    {
        $assetCategory->delete();
        return redirect()->route('categories.index')->with('success', 'Category deleted successfully.');
    }

    private function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }
}