<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::latest()->get();
        return view('locations.index', compact('locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
        ]);

        Location::create($validated);
        return redirect()->back()->with('success', 'Location created successfully.');
    }

    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
        ]);

        $location->update($validated);
        return redirect()->back()->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        // Optional: Check if location has stock before deleting
        $location->delete();
        return redirect()->back()->with('success', 'Location removed successfully.');
    }
}