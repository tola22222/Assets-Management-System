<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::latest()->paginate(10);
        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:categories|max:255']);

        Category::create($request->all());

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created new category: ' . $request->name,
        ]);

        return redirect()->route('categories.index')->with('success', 'Category created!');
    }

    public function update(Request $request, Category $category)
    {
        $request->validate(['name' => 'required|string|max:255|unique:categories,name,'.$category->id]);

        $oldName = $category->name;
        $category->update($request->all());

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => "Renamed category from '$oldName' to '$category->name'",
        ]);

        return redirect()->route('categories.index')->with('success', 'Category updated!');
    }

    public function destroy(Category $category)
    {
        $name = $category->name;
        $category->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted category: ' . $name,
        ]);

        return back()->with('success', 'Category removed.');
    }
}