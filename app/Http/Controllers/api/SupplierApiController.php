<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierApiController extends Controller
{
    // GET all suppliers
    public function index()
    {
        $suppliers = Supplier::latest()->get();
        return response()->json([
            'status' => true,
            'data' => $suppliers
        ], 200);
    }

    // POST create new supplier
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::create($data);

        // Log the activity
        ActivityLog::create([
            'user_id'     => Auth::id() ?? 1, // Fallback to 1 for testing
            'action'      => 'Create',
            'description' => 'Added new supplier via API: ' . $request->name,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Supplier created!',
            'data'    => $supplier
        ], 201);
    }

    // PUT/PATCH update supplier
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $supplier->update($data);

        ActivityLog::create([
            'user_id'     => Auth::id() ?? 1,
            'action'      => 'Update',
            'description' => 'Updated supplier via API: ' . $supplier->name,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Supplier updated!',
            'data'    => $supplier
        ], 200);
    }

    // DELETE supplier
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $name = $supplier->name;
        $supplier->delete();

        ActivityLog::create([
            'user_id'     => Auth::id() ?? 1,
            'action'      => 'Delete',
            'description' => 'Removed supplier via API: ' . $name,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Supplier deleted successfully.'
        ], 200);
    }
}