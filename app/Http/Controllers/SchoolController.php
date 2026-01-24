<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index()
    {
        $schools = School::all(); // Fetch all schools
        return view('schools.index', compact('schools'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'status' => 'required|in:Active,Ended',
            'end_date' => 'nullable|date',
        ]);

        School::create($validated);

      
    return redirect()->route('schools.index')->with('success', 'School created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'address' => 'nullable|string',
        'contact_person' => 'nullable|string',
        'phone' => 'nullable|string',
        'status' => 'required|in:Active,Ended',
        'end_date' => 'nullable|date',
    ]);

    // Find the school by ID
    $school = School::findOrFail($id);

    // Update with validated data
    $school->update($validated);

    // Redirect back with success message
    return redirect()->route('schools.index')->with('success', 'School updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
