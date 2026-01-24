<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::all(); // Fetch all suppliers
        return view('suppliers.index', compact('suppliers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        Supplier::create($data);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Added new supplier: ' . $request->name,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
   public function edit(Supplier $supplier)
{
    // If using a modal, you might not need this, 
    // but it's good practice for standard Laravel flow.
    return response()->json($supplier);
}

public function update(Request $request, Supplier $supplier)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:20',
        'address' => 'nullable|string',
    ]);

    $supplier->update($data);

    // Record the activity
    ActivityLog::create([
        'user_id' => Auth::id(),
        'action' => 'Update',
        'description' => 'Updated supplier details for: ' . $supplier->name,
    ]);

    return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully!');
}
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $name = $supplier->name;
        $supplier->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Removed supplier: ' . $name,
        ]);

        return back()->with('success', 'Supplier deleted.');
    }
}
