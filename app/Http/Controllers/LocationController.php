<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::withCount('assets')->latest()->get();
        return view('locations.index', compact('locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program,classroom,storage',
            'description' => 'nullable|string',
        ]);

        $location = Location::create($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created location: ' . $location->name,
        ]);

        return redirect()->route('assets-locations.index')->with('success', 'Location created successfully.');
    }

    public function show(Location $location)
    {
        $assets = $location->assets()->with('category')->latest()->get();
        return view('locations.show', compact('location', 'assets'));
    }

    public function edit(Location $location)
    {
        return response()->json($location);
    }

    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program,classroom,storage',
            'description' => 'nullable|string',
        ]);

        $location->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated location: ' . $location->name,
        ]);

        return redirect()->route('assets-locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        if ($location->assets()->count() > 0) {
            return redirect()->route('assets-locations.index')->with('error', 'Cannot delete location with assets.');
        }
        $location->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted location: ' . $location->name,
        ]);

        return redirect()->route('assets-locations.index')->with('success', 'Location deleted.');
    }
}
