<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // Display all locations
    public function index()
    {
        $locations = Location::latest()->get();
        return view('locations.index', compact('locations'));
    }

    // Store new location
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
        ]);

        Location::create($validated);

        return redirect()->back()->with('success', 'Location created successfully.');
    }

    // Update location
    public function update(Request $request, Location $location) // ✅ use $location for route model binding
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
        ]);

        $location->update($validated);

        return redirect()->back()->with('success', 'Location updated successfully.');
    }

    // Delete location
    public function destroy(Location $location)
    {
        $location->delete();
        return redirect()->back()->with('success', 'Location deleted successfully.');
    }
}