<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    public function index()
    {
        return response()->json(Location::withCount('assetStocks')->latest()->get());
    }

    public function show(Location $location)
    {
        $location->load('assetStocks.asset');
        return response()->json($location);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
            'description' => 'nullable|string',
        ]);

        $location = Location::create($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created location: ' . $location->name,
        ]);

        return response()->json($location, 201);
    }

    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
            'description' => 'nullable|string',
        ]);

        $location->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated location: ' . $location->name,
        ]);

        return response()->json($location);
    }

    public function destroy(Location $location)
    {
        if ($location->assetStocks()->count() > 0) {
            return response()->json(['message' => 'Cannot delete location with assets.'], 422);
        }

        $location->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted location: ' . $location->name,
        ]);

        return response()->json(['message' => 'Location deleted.']);
    }
}
