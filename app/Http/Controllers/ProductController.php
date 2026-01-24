<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->latest()->paginate(10);
        $categories = Category::orderBy('name')->get();
        return view('products.index', compact('products', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'asset_type' => 'required|in:Fixed,Consumable',
        ]);

        Product::create($data);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => "Added product: {$request->name} ({$request->asset_type})",
        ]);

        return redirect()->route('products.index')->with('success', 'Product added successfully!');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'asset_type' => 'required|in:Fixed,Consumable',
        ]);

        $product->update($data);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => "Updated product: {$product->name}",
        ]);

        return redirect()->route('products.index')->with('success', 'Product updated!');
    }

    public function destroy(Product $product)
    {
        $name = $product->name;
        $product->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => "Removed product: $name",
        ]);

        return back()->with('success', 'Product deleted.');
    }
}