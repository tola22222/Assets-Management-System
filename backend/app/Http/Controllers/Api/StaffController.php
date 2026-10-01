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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Staff see colleagues in their own program only (nobody, while they
        // have no program — the same fail-closed rule as the register). A
        // staff record not yet moved onto a program sees colleagues at its
        // old site, like User::siteLocationIds().
        // Each row carries its program and that program's school ids, so the
        // SPA can tell which schools a person covers (e.g. as a transfer recipient).
        $query = Staff::with(['program:id,name', 'program.locations:locations.id,locations.name'])->latest()->latest('id');
        if ($user->isSiteScoped()) {
            $programId = $user->staff?->program_id;
            $programId !== null
                ? $query->where('program_id', $programId)
                : $query->whereIn('location_id', $user->siteLocationIds());
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdministrator() || Auth::user()->hasCustomPermission('staff', 'create'), 403, 'Only administrators can create staff members.');

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            // Required: their ONE program decides which schools they can see
            // and manage (all of the program's schools). No school is picked
            // for the staff member directly.
            'program_id' => 'required|exists:programs,id',
            'location_id' => 'nullable|exists:locations,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'program_id.required' => 'Assign this staff member a program — it decides which schools they can see.',
        ]);
        $data['location_id'] = $this->homeSchool($data);

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
        abort_unless(Auth::user()->isAdministrator() || Auth::user()->hasCustomPermission('staff', 'update'), 403, 'Only administrators can update staff members.');

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email,'.$staff->id,
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'required|in:active,inactive',
            'program_id' => 'required|exists:programs,id',
            'location_id' => 'nullable|exists:locations,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'program_id.required' => 'Assign this staff member a program — it decides which schools they can see.',
        ]);

        // A program's lead belongs to that program; moving them to another
        // one would leave the first with a lead who is not in it. (A staff
        // member is in exactly one program — the column holds one value.)
        $led = Program::where('responsible_staff_id', $staff->id)->first();
        if ($led && (int) $led->id !== (int) $data['program_id']) {
            return response()->json([
                'message' => "{$staff->full_name} leads the program \"{$led->name}\". Choose a new lead for that program before moving them to another one.",
                'errors' => ['program_id' => ['This staff member leads another program.']],
            ], 422);
        }
        $data['location_id'] = $this->homeSchool($data);

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

    /**
     * The staff member's base school, kept only for display: one of their
     * program's schools (the one sent, if it belongs to the program, otherwise
     * the program's first school). Access always covers ALL the program's
     * schools — see User::siteLocationIds().
     */
    private function homeSchool(array $data): ?int
    {
        $schools = DB::table('location_program')->where('program_id', $data['program_id'])
            ->orderBy('id')->pluck('location_id')->map(fn ($id) => (int) $id)->all();

        $sent = isset($data['location_id']) ? (int) $data['location_id'] : null;

        return $sent !== null && in_array($sent, $schools, true) ? $sent : ($schools[0] ?? null);
    }

    public function destroy(Staff $staff)
    {
        abort_unless(Auth::user()->isAdministrator() || Auth::user()->hasCustomPermission('staff', 'delete'), 403, 'Only administrators can delete staff members.');

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
