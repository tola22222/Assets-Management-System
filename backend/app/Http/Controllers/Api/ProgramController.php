<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesAndSorts;
use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    use PaginatesAndSorts;

    private const SORTABLE = ['name', 'created_at'];

    public function index(Request $request)
    {
        $query = Program::query();

        $this->applySearch($query, $request, ['name', 'description']);

        return response()->json($this->paginateSorted($query, $request, self::SORTABLE));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:programs,name',
            'description' => 'nullable|string',
        ]);

        return response()->json(Program::create($validated), 201);
    }

    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:programs,name,'.$program->id,
            'description' => 'nullable|string',
        ]);

        $program->update($validated);

        return response()->json($program->fresh());
    }

    public function destroy(Program $program)
    {
        if ($program->assignments()->where('status', 'assigned')->exists()) {
            return response()->json(['message' => 'Cannot delete program with active asset assignments.'], 422);
        }

        $program->delete();

        return response()->json(['message' => 'Program deleted.']);
    }
}
