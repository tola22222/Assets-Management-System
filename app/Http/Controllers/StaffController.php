<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index()
    {
        $staffs = Staff::latest()->get();
        return view('staff.index', compact('staffs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'nullable|email|unique:staff,email',
            'phone'     => 'nullable|string|max:20',
            'position'  => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'photo'     => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('staff_photos', 'public');
        }

        Staff::create($data);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Added staff member: ' . $request->full_name,
        ]);

        return redirect()->route('staff.index')->with('success', 'Staff member created!');
    }

    public function update(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'nullable|email|unique:staff,email,' . $staff->id,
            'phone'     => 'nullable|string|max:20',
            'position'  => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status'    => 'required|in:active,inactive',
            'photo'     => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
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

        return redirect()->route('staff.index')->with('success', 'Staff updated successfully!');
    }

    public function destroy(Staff $staff)
    {
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

        return back()->with('success', 'Staff member deleted.');
    }
}