<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    private const WITH = ['location', 'locations', 'responsibleStaff'];

    public function index(Request $request)
    {
        // Staff see only the program they belong to.
        $programs = Program::visibleTo($request->user())->with(self::WITH)->latest()->latest('id')->get();

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

    /**
     * A program is created on its own — just a name and description. Its
     * schools are chosen on each Location (Program → Location → Staff), and
     * its Responsible Staff is set later through Edit, once staff exist.
     */
    public function store(Request $request)
    {
        [$validated, $schools] = $this->validated($request);
        if ($validated['responsible_staff_id'] ?? null) {
            $this->assertLeadCanJoin($validated['responsible_staff_id'], null);
        }

        $program = DB::transaction(function () use ($validated, $schools) {
            $program = Program::create($validated + ($schools ? ['location_id' => $schools[0]] : []));
            $this->link($program, $schools);

            return $program;
        });

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created program: '.$program->name,
        ]);

        return response()->json($program->fresh(self::WITH), 201);
    }

    public function update(Request $request, Program $program)
    {
        [$validated, $schools] = $this->validated($request, $program);
        if ($validated['responsible_staff_id'] ?? null) {
            $this->assertLeadCanJoin($validated['responsible_staff_id'], $program);
        }

        DB::transaction(function () use ($program, $validated, $schools) {
            $program->update($validated + ($schools ? ['location_id' => $schools[0]] : []));
            $this->link($program, $schools);
        });

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated program: '.$program->name,
        ]);

        return response()->json($program->fresh(self::WITH));
    }

    public function destroy(Program $program)
    {
        if ($program->assignments()->where('status', 'assigned')->exists()) {
            return response()->json(['message' => 'Cannot delete program with active asset assignments.'], 422);
        }

        $program->delete();

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted program: '.$program->name,
        ]);

        return response()->json(['message' => 'Program deleted.']);
    }

    /**
     * Name and description. Schools are set from the Location form now, so
     * location_ids is optional (still honoured for older API callers), and the
     * Responsible Staff — who accepts transfers at the program's schools — is
     * optional, set through Edit.
     *
     * @return array{0: array, 1: int[]} validated fields, school ids (may be empty)
     */
    private function validated(Request $request, ?Program $program = null): array
    {
        if (! $request->filled('location_ids') && $request->filled('location_id')) {
            $request->merge(['location_ids' => [$request->input('location_id')]]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:programs,name'.($program ? ','.$program->id : ''),
            'description' => 'nullable|string',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|distinct|exists:locations,id',
            // A staff member leads at most one program, so accountability for a
            // program's assets always points at exactly one person. Backed by a
            // unique index on the column.
            'responsible_staff_id' => [
                'nullable',
                'exists:staff,id',
                Rule::unique('programs', 'responsible_staff_id')->ignore($program?->id),
            ],
        ], [
            'responsible_staff_id.unique' => 'This staff member is already responsible for another program.',
        ]);

        $schools = array_values(array_map('intval', $validated['location_ids'] ?? []));

        // A location belongs to one program only.
        $elsewhere = DB::table('location_program')
            ->join('locations', 'locations.id', '=', 'location_program.location_id')
            ->whereIn('location_program.location_id', $schools)
            ->when($program, fn ($q) => $q->where('location_program.program_id', '!=', $program->id))
            ->pluck('locations.name')->unique();
        if ($elsewhere->isNotEmpty()) {
            throw ValidationException::withMessages([
                'location_ids' => $elsewhere->implode(', ').' already belongs to another program. A location belongs to one program only.',
            ]);
        }
        unset($validated['location_ids'], $validated['location_id']);

        return [$validated, $schools];
    }

    /**
     * A staff member belongs to ONE program. The lead must already be in this
     * program, or in none yet (saving puts them in it) — never in another.
     */
    private function assertLeadCanJoin(int $staffId, ?Program $program): void
    {
        $current = Staff::whereKey($staffId)->value('program_id');

        if ($current !== null && (int) $current !== (int) $program?->id) {
            throw ValidationException::withMessages([
                'responsible_staff_id' => 'This staff member already belongs to another program. A staff member can only be in one program.',
            ]);
        }
    }

    /** Save the program's schools (when sent) and put its lead in it. */
    private function link(Program $program, array $schools): void
    {
        if ($schools) {
            $program->locations()->sync($schools);
        }
        if ($program->responsible_staff_id) {
            Staff::whereKey($program->responsible_staff_id)->update(['program_id' => $program->id]);
        }
    }
}
