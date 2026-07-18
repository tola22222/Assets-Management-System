<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index()
    {
        return response()->json(Staff::latest()->get());
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create staff members.');

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('staff_photos', 'public');
        }

        $staff = Staff::create($data);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Added staff member: ' . $staff->full_name,
        ]);

        return response()->json($staff, 201);
    }

    public function update(Request $request, Staff $staff)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can update staff members.');

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email,' . $staff->id,
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'required|in:active,inactive',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            if ($staff->photo_path) {
                Storage::disk('public')->delete($staff->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('staff_photos', 'public');
        }

        $staff->update($data);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated details for staff: ' . $staff->full_name,
        ]);

        return response()->json($staff->fresh());
    }

    public function destroy(Staff $staff)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete staff members.');

        $name = $staff->full_name;

        if ($staff->photo_path) {
            Storage::disk('public')->delete($staff->photo_path);
        }

        $staff->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Removed staff member: ' . $name,
        ]);

        return response()->json(['message' => 'Staff member deleted.']);
    }
}
