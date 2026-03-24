<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;

class ProgramApiController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => Program::latest()->get()
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:programs,name',
            'description' => 'nullable|string'
        ]);

        $program = Program::create($data);
        return response()->json(['status' => true, 'data' => $program], 201);
    }

    public function update(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:programs,name,' . $id,
            'description' => 'nullable|string'
        ]);

        $program->update($data);
        return response()->json(['status' => true, 'data' => $program], 200);
    }

    public function destroy($id)
    {
        $program = Program::findOrFail($id);
        
        if ($program->assignments()->where('status', 'assigned')->exists()) {
            return response()->json([
                'status' => false, 
                'message' => 'Cannot delete program with active asset assignments.'
            ], 400);
        }

        $program->delete();
        return response()->json(['status' => true, 'message' => 'Deleted'], 200);
    }
}