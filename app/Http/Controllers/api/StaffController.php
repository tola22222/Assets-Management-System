<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index() {
        return response()->json(['status' => true, 'data' => Staff::latest()->get()], 200);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'nullable|email|unique:staff,email',
            'phone'     => 'nullable|string',
            'position'  => 'nullable|string',
            'photo'     => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('staff_photos', 'public');
        }

        $staff = Staff::create($data);
        return response()->json(['status' => true, 'data' => $staff], 201);
    }

    public function show($id) {
        $staff = Staff::find($id);
        return $staff ? response()->json(['status' => true, 'data' => $staff]) 
                     : response()->json(['status' => false, 'message' => 'Not Found'], 404);
    }

    public function update(Request $request, $id) {
        $staff = Staff::findOrFail($id);
        
        $data = $request->validate([
            'full_name' => 'required|string',
            'email'     => 'nullable|email|unique:staff,email,'.$id,
            'status'    => 'required|in:active,inactive',
        ]);

        if ($request->hasFile('photo')) {
            if ($staff->photo_path) Storage::disk('public')->delete($staff->photo_path);
            $data['photo_path'] = $request->file('photo')->store('staff_photos', 'public');
        }

        $staff->update($data);
        return response()->json(['status' => true, 'data' => $staff], 200);
    }

    public function destroy($id) {
        $staff = Staff::findOrFail($id);
        if ($staff->photo_path) Storage::disk('public')->delete($staff->photo_path);
        $staff->delete();
        return response()->json(['status' => true, 'message' => 'Deleted'], 200);
    }
}