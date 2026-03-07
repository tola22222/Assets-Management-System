<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::latest()->get();
        return response()->json([
            'status' => true,
            'data' => $locations
        ], 200);
    }

    // ✅ GET BY ID
    public function show($id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json([
                'status' => false,
                'message' => 'Location not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $location
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
        ]);

        $location = Location::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Location created successfully',
            'data' => $location
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:office,lab,program',
        ]);

        $location->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Location updated successfully',
            'data' => $location
        ], 200);
    }

    public function destroy($id)
    {
        $location = Location::findOrFail($id);
        $location->delete();

        return response()->json([
            'status' => true,
            'message' => 'Location deleted successfully'
        ], 200);
    }
}