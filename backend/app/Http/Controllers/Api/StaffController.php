<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetAssignment;
use App\Models\Location;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use App\Services\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        // has_login: whether a user account is linked — without one the person
        // can't sign in, so can't accept a transfer (shown as "No login").
        $query = Staff::with(['program:id,name', 'program.locations:locations.id,locations.name', 'locations:locations.id,locations.name'])
            ->withExists('user as has_login')
            ->latest()->latest('id');
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

        // A single location_id from an older caller counts as a one-item list.
        if (! $request->has('location_ids') && $request->filled('location_id')) {
            $request->merge(['location_ids' => [$request->input('location_id')]]);
        }

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            // Program → Location → Staff: the staff member works at one or
            // more locations, all in one program — their program comes from
            // them (programOfLocations()); it is never picked by hand.
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'integer|distinct|exists:locations,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'location_ids.required' => 'Select the location(s)/school(s) this staff member works at.',
        ]);
        [$data, $locationIds] = $this->withLocations($data);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = ImageCompressor::store($request->file('photo'), 'staff_photos');
        }

        $staff = DB::transaction(function () use ($data, $locationIds) {
            $staff = Staff::create($data);
            $staff->locations()->sync($locationIds);

            return $staff;
        });
        $this->leadIfLeaderless($staff->fresh());

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

        // A single location_id from an older caller counts as a one-item list.
        if (! $request->has('location_ids') && $request->filled('location_id')) {
            $request->merge(['location_ids' => [$request->input('location_id')]]);
        }

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:staff,email,'.$staff->id,
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'required|in:active,inactive',
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'integer|distinct|exists:locations,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'location_ids.required' => 'Select the location(s)/school(s) this staff member works at.',
        ]);
        [$data, $locationIds] = $this->withLocations($data, $staff);

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

        if ($request->hasFile('photo')) {
            if ($staff->photo_path) {
                Storage::disk('public')->delete($staff->photo_path);
            }
            $data['photo_path'] = ImageCompressor::store($request->file('photo'), 'staff_photos');
        }

        DB::transaction(function () use ($staff, $data, $locationIds) {
            $staff->update($data);
            $staff->locations()->sync($locationIds);
        });
        $this->leadIfLeaderless($staff->fresh());

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated details for staff: '.$staff->full_name,
        ]);

        return response()->json($staff->fresh());
    }

    /**
     * A program is created without a Responsible Staff (Program → Location →
     * Staff), so the first active staff member to join a program that has
     * none becomes it — someone at its schools can then accept transfers.
     * Never replaces a lead, never makes anyone lead two programs; HR can
     * change it on the program's Edit form.
     */
    private function leadIfLeaderless(Staff $staff): void
    {
        if ($staff->program_id === null || $staff->status !== 'active') {
            return;
        }

        if (Program::where('responsible_staff_id', $staff->id)->exists()) {
            return;
        }

        $taken = Program::whereKey($staff->program_id)->whereNull('responsible_staff_id')
            ->update(['responsible_staff_id' => $staff->id]);

        if ($taken) {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Update',
                'description' => "{$staff->full_name} became Responsible Staff of program #{$staff->program_id} (first staff member to join it)",
            ]);
        }
    }

    /**
     * The staff member's locations and the program they come from (Program →
     * Location → Staff): every location must belong to a program, and all of
     * them to the SAME one — that program is the staff member's.
     *
     * A location saved before the one-program rule may still list several
     * programs; then the program they all share is used, keeping the staff
     * member's current one when it is among them.
     *
     * @return array{0: array, 1: int[]} data with program_id / location_id set, location ids
     */
    private function withLocations(array $data, ?Staff $staff = null): array
    {
        $locationIds = array_values(array_map('intval', $data['location_ids']));
        unset($data['location_ids']);

        $programsByLocation = DB::table('location_program')->whereIn('location_id', $locationIds)
            ->get(['location_id', 'program_id'])
            ->groupBy('location_id')
            ->map(fn ($rows) => $rows->pluck('program_id')->map(fn ($id) => (int) $id)->all());

        $missing = array_diff($locationIds, $programsByLocation->keys()->map(fn ($id) => (int) $id)->all());
        if ($missing) {
            throw ValidationException::withMessages([
                'location_ids' => Location::whereKey($missing)->pluck('name')->implode(', ').' has no program yet. Set its program on the Locations page first.',
            ]);
        }

        $shared = array_values(array_intersect(...array_values($programsByLocation->all())));
        if ($shared === []) {
            throw ValidationException::withMessages([
                'location_ids' => 'These locations belong to different programs. A staff member works in one program only — choose locations of the same program.',
            ]);
        }

        $current = $staff?->program_id !== null ? (int) $staff->program_id : null;
        if (count($shared) > 1 && ! in_array($current, $shared, true)) {
            throw ValidationException::withMessages([
                'location_ids' => Location::whereKey($locationIds[0])->value('name').' still belongs to several programs. Edit it on the Locations page to keep one program first.',
            ]);
        }

        $data['program_id'] = count($shared) === 1 ? $shared[0] : $current;
        $data['location_id'] = $locationIds[0];

        return [$data, $locationIds];
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
