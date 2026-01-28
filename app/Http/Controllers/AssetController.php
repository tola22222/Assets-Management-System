<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Product;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $assets = Asset::all(); // or Asset::get()
        return view('assets.index', compact('assets'));
    }

    public function create()
    {
        // This will point to your "Add New Asset" form
        return view('assets.create');
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'serial_number'   => 'required|unique:assets',
            'product_id'      => 'required|exists:products,id',
            'asset_tag'       => 'required|unique:assets',
            'condition'       => 'required|in:New,Good,Fair,Broken',
            'status'          => 'required|in:In Stock,In Use,In Transit,Disposed',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',

            'invoice'         => 'nullable|file|mimes:pdf,jpg,png|max:2048',
            'warranty_doc'    => 'nullable|file|mimes:pdf,jpg,png|max:2048',
            'image'           => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('invoice')) {
            $validated['invoice_path'] =
                $request->file('invoice')->store('assets/invoices', 'public');
        }

        if ($request->hasFile('warranty_doc')) {
            $validated['warranty_doc_path'] =
                $request->file('warranty_doc')->store('assets/warranty', 'public');
        }

        if ($request->hasFile('image')) {
            $validated['image_path'] =
                $request->file('image')->store('assets/photos', 'public');
        }

        Asset::create($validated);

        return redirect()->route('assets.index')
            ->with('success', 'Asset registered successfully.');
    }


    /**
     * Display the specified resource.
     */
    /**
     * Show a single asset detail.
     */
    public function show(Asset $asset)
    {
        return view('assets.show', compact('asset'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Asset $asset)
    {
        // Delete physical files from storage before deleting database record
        if ($asset->invoice_path) Storage::disk('public')->delete($asset->invoice_path);
        if ($asset->image_path) Storage::disk('public')->delete($asset->image_path);
        if ($asset->warranty_doc_path) Storage::disk('public')->delete($asset->warranty_doc_path);

        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }
}
