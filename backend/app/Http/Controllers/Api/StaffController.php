<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetAssignment;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Staff see colleagues at their own site (everyone, while their own
        // site is not set yet — the same fail-open rule as the register).
        $query = Staff::latest();
        if ($user->isSiteScoped() && $user->siteLocationId() !== null) {
            $query->where('location_id', $user->siteLocationId());
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isOperationsHrManager(), 403, 'Only administrators can create staff members.');

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'location_id' => 'nullable|exists:locations,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('staff_photos', 'public');
        }

        $staff = Staff::create($data);

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Added staff member: '.$staff->full_name,
        ]);

        return response()->json($staff, 201);
    }

    public function update(Request $request, Staff $staff)
    {
        abort_unless(Auth::user()->isOperationsHrManager(), 403, 'Only administrators can update staff members.');

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email,'.$staff->id,
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'required|in:active,inactive',
            'location_id' => 'nullable|exists:locations,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        // A program lead answers for their school's transfers; moving them to
        // another site would leave that school with a lead who is not there.
        if (array_key_exists('location_id', $data) && (int) $data['location_id'] !== (int) $staff->location_id) {
            $led = Program::where('responsible_staff_id', $staff->id)->first();
            if ($led && (int) $led->location_id !== (int) $data['location_id']) {
                return response()->json([
                    'message' => "{$staff->full_name} leads the program \"{$led->name}\" at another site. Choose a new lead for that program before moving them.",
                    'errors' => ['location_id' => ['This staff member leads a program at their current site.']],
                ], 422);
            }
        }

        if ($request->hasFile('photo')) {
            if ($staff->photo_path) {
                Storage::disk('public')->delete($staff->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('staff_photos', 'public');
        }

        $staff->update($data);

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated details for staff: '.$staff->full_name,
        ]);

        return response()->json($staff->fresh());
    }

    public function destroy(Staff $staff)
    {
        abort_unless(Auth::user()->isOperationsHrManager(), 403, 'Only administrators can delete staff members.');

        $name = $staff->full_name;

        // Deleting quietly unlinks all of these (nullOnDelete) — a school
        // loses the lead who accepts its transfers, a login loses its site.
        $blockers = array_filter([
            'leads a program' => Program::where('responsible_staff_id', $staff->id)->exists(),
            'has a login account' => User::where('staff_id', $staff->id)->exists(),
            'has assets assigned' => AssetAssignment::where('assigned_to_type', 'staff')
                ->where('assigned_to_id', $staff->id)
                ->whereIn('status', AssetAssignment::CURRENT_STATUSES)
                ->exists(),
        ]);
        if ($blockers) {
            return response()->json([
                'message' => "Cannot delete {$name}: this staff member ".implode(', ', array_keys($blockers)).'. Reassign or remove those first, or mark them inactive.',
            ], 422);
        }

        if ($staff->photo_path) {
            Storage::disk('public')->delete($staff->photo_path);
        }

        $staff->delete();

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Removed staff member: '.$name,
        ]);

        return response()->json(['message' => 'Staff member deleted.']);
    }
}
