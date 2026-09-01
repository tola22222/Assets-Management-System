<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index()
    {
        return response()->json(Location::withCount('assets')->orderBy('name')->get());
    }

    public function show(Location $location)
    {
        $location->load(['assets.category']);

        return response()->json($location);
    }

    public function store(Request $request)
    {
        $location = Location::create($this->validateLocation($request));

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created location: '.$location->name,
        ]);

        return response()->json($location, 201);
    }

    public function update(Request $request, Location $location)
    {
        $location->update($this->validateLocation($request, $location));

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated location: '.$location->name,
        ]);

        return response()->json($location);
    }

    public function destroy(Location $location)
    {
        if ($location->assets()->count() > 0) {
            return response()->json(['message' => 'Cannot delete location with assets.'], 422);
        }

        // staff.location_id is nullOnDelete, so deleting a site does not fail
        // — it quietly blanks the site of everyone posted there. That is not a
        // cosmetic loss: a staff user with no location_id is deliberately
        // unrestricted (see AssetVerificationController/QrScanController), so
        // dropping a site silently widens what its staff can see and verify.
        $staffCount = Staff::where('location_id', $location->id)->count();
        if ($staffCount > 0) {
            return response()->json([
                'message' => "Cannot delete this site: {$staffCount} staff member(s) are assigned to it. Move them to another site first.",
            ], 422);
        }

        $location->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted location: '.$location->name,
        ]);

        return response()->json(['message' => 'Location deleted.']);
    }

    /**
     * The site code is not optional: it is the [SITE] segment of every asset
     * tag (PEY-[SITE]-[CATEGORY]-####), and AssetCodeService::nextCode()
     * refuses to register or import an asset at a location without one. This
     * field used to be missing from the validator entirely, so every site
     * added through this screen was saved code-less and then failed at asset
     * registration/import time with "Asset location must be an approved site
     * with a site code."
     *
     * @return array<string, mixed>
     */
    private function validateLocation(Request $request, ?Location $location = null): array
    {
        // Upper-cased before the unique rule runs, not after: asset tags are
        // always upper-case, so "sr" and "SR" are the same site — but sqlite
        // compares them case-sensitively and would let the duplicate through
        // validation only to fail on the unique index at insert time.
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9]{2,4}$/',
                Rule::unique('locations', 'code')->ignore($location?->id),
            ],
            'type' => 'required|in:office,lab,program',
            'description' => 'nullable|string',
        ], [
            'code.regex' => 'The site code must be 2-4 letters or numbers (for example SR).',
            'code.unique' => 'That site code is already used by another location.',
        ]);
    }
}
