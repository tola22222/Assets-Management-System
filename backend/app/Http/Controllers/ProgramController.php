<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::latest()->get();
        return view('programs.index', compact('programs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:programs,name',
            'description' => 'nullable|string'
        ]);

        Program::create($request->all());

        return redirect()->back()->with('success', 'Program created successfully!');
    }

    public function update(Request $request, Program $program)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:programs,name,' . $program->id,
            'description' => 'nullable|string'
        ]);

        $program->update($request->all());

        return redirect()->back()->with('success', 'Program updated successfully!');
    }

    public function destroy(Program $program)
    {
        // Check if assets are currently assigned to this program before deleting
        if ($program->assignments()->where('status', 'assigned')->exists()) {
            return redirect()->back()->with('error', 'Cannot delete program with active asset assignments.');
        }

        $program->delete();
        return redirect()->back()->with('success', 'Program deleted successfully!');
    }
}