<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::with(['location', 'responsibleStaff'])->latest()->get();

        // A lead who has no login account cannot actually accept a transfer, so
        // the site is just as stuck as if it had no lead at all — but nothing
        // on screen said so. Surfacing it here is what makes that visible.
        $staffWithLogin = User::whereIn('staff_id', $programs->pluck('responsible_staff_id')->filter())
            ->pluck('staff_id')
            ->all();

        $programs->each(function (Program $program) use ($staffWithLogin) {
            $program->responsible_staff_has_login = $program->responsible_staff_id !== null
                && in_array($program->responsible_staff_id, $staffWithLogin);
        });

        return response()->json($programs);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $this->assertStaffBelongsToSchool($validated);

        $program = Program::create($validated);

        return response()->json($program->fresh(['location', 'responsibleStaff']), 201);
    }

    public function update(Request $request, Program $program)
    {
        $validated = $this->validated($request, $program);
        $this->assertStaffBelongsToSchool($validated);

        $program->update($validated);

        return response()->json($program->fresh(['location', 'responsibleStaff']));
    }

    public function destroy(Program $program)
    {
        if ($program->assignments()->where('status', 'assigned')->exists()) {
            return response()->json(['message' => 'Cannot delete program with active asset assignments.'], 422);
        }

        $program->delete();

        return response()->json(['message' => 'Program deleted.']);
    }

    /**
     * School and responsible staff are required, not optional extras: a site's
     * ability to accept an asset transfer is resolved through them, so a
     * program saved without them would quietly leave its school unable to
     * receive anything.
     */
    private function validated(Request $request, ?Program $program = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255|unique:programs,name'.($program ? ','.$program->id : ''),
            'description' => 'nullable|string',
            'location_id' => 'required|exists:locations,id',
            // A staff member leads at most one program, so accountability for a
            // site's assets always points at exactly one person. Backed by a
            // unique index on the column.
            'responsible_staff_id' => [
                'required',
                'exists:staff,id',
                Rule::unique('programs', 'responsible_staff_id')->ignore($program?->id),
            ],
        ], [
            'responsible_staff_id.unique' => 'This staff member is already responsible for another program.',
        ]);
    }

    /** The person accountable for a site's program has to work at that site. */
    private function assertStaffBelongsToSchool(array $validated): void
    {
        $staff = Staff::find($validated['responsible_staff_id']);

        if ($staff && $staff->location_id !== null && $staff->location_id === (int) $validated['location_id']) {
            return;
        }

        throw ValidationException::withMessages([
            'responsible_staff_id' => 'The responsible staff member must be based at the selected school.',
        ]);
    }
}
