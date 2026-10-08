<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Supplier;
use App\Services\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        // Not part of the Staff role's default permissions; a custom role can
        // still grant it (PermissionRegistry::BASELINE).
        abort_unless($request->user()->hasPermission('suppliers', 'view'), 403, 'You do not have permission to view suppliers.');

        return response()->json(Supplier::latest()->latest('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);
        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = ImageCompressor::store($request->file('image'), 'suppliers');
        }

        $supplier = Supplier::create($data);

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Added new supplier: '.$supplier->name,
        ]);

        return response()->json($supplier, 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);
        unset($data['image']);

        // A new photo replaces the old one; without one the photo is kept.
        if ($request->hasFile('image')) {
            if ($supplier->image_path) {
                Storage::disk('public')->delete($supplier->image_path);
            }
            $data['image_path'] = ImageCompressor::store($request->file('image'), 'suppliers');
        }

        $supplier->update($data);

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated supplier: '.$supplier->name,
        ]);

        return response()->json($supplier->fresh());
    }

    public function destroy(Supplier $supplier)
    {
        $name = $supplier->name;
        $image = $supplier->image_path;
        $supplier->delete();
        if ($image) {
            Storage::disk('public')->delete($image);
        }

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Removed supplier: '.$name,
        ]);

        return response()->json(['message' => 'Supplier deleted.']);
    }
}
