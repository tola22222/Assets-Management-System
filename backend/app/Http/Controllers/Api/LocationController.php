<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Program;
use App\Models\Staff;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // ?scope=destinations is the transfer form's "To" list: every site's
        // name, so staff can request a transfer out of their site — but only
        // names, nothing about what sits at those sites.
        if ($request->query('scope') === 'destinations') {
            return response()->json(Location::latest()->latest('id')->get(['id', 'name', 'code', 'type']));
        }

        // Everything else (Locations page, filters): staff get only their own
        // site — none until HR sets it — and counts only cover what they may see.
        return response()->json(
            Location::withCount(['assets' => fn ($q) => $q->visibleTo($user)])
                ->with('programs:programs.id,programs.name')
                ->when($user->isSiteScoped(), fn ($q) => $q->whereKey($user->siteLocationIds()))
                // Newest first, like every list in the app.
                ->latest()
                ->latest('id')
                ->get()
        );
    }

    public function show(Request $request, Location $location)
    {
        // Staff may open only their own site.
        $user = $request->user();
        abort_unless($user->canAccessLocation($location->id), 404);

        $location->load(['assets' => fn ($q) => $q->visibleTo($user)->with('category')]);

        return response()->json($location);
    }

    public function store(Request $request)
    {
        $programIds = $this->validateProgram($request);
        $location = DB::transaction(function () use ($request, $programIds) {
            $location = Location::create($this->validateLocation($request));
            $this->linkPrograms($location, $programIds);

            return $location;
        });

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created location: '.$location->name,
        ]);

        return response()->json($location->load('programs:programs.id,programs.name'), 201);
    }

    public function update(Request $request, Location $location)
    {
        $programIds = $this->validateProgram($request);
        $validated = $this->validateLocation($request, $location);

        // Staff working here get their program through this location, so it
        // can't switch to another program while staff of a different program
        // are still assigned here — they would hold locations of two programs.
        $stranded = Staff::whereHas('locations', fn ($q) => $q->whereKey($location->id))
            ->whereNotNull('program_id')
            ->whereNotIn('program_id', $programIds)
            ->pluck('full_name');
        if ($stranded->isNotEmpty()) {
            return response()->json([
                'message' => 'Staff assigned here belong to another program ('.$stranded->implode(', ').'). Move them to other locations first, or keep their program.',
                'errors' => ['program_id' => ['Staff here belong to another program.']],
            ], 422);
        }

        DB::transaction(function () use ($location, $validated, $programIds) {
            $location->update($validated);
            $this->linkPrograms($location, $programIds);
        });

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated location: '.$location->name,
        ]);

        return response()->json($location->load('programs:programs.id,programs.name'));
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

        // Transfers and assignments cascade on delete and verifications / stock
        // restrict it — either way a site with history must stay.
        $references = array_filter([
            'transfers' => AssetTransfer::where('from_location_id', $location->id)->orWhere('to_location_id', $location->id)->count(),
            'assignments' => AssetAssignment::where('location_id', $location->id)->count(),
            'verifications' => AssetVerification::where('location_id', $location->id)->count(),
            'stock items' => StockItem::where('location_id', $location->id)->count(),
            'programs' => Program::whereHas('locations', fn ($q) => $q->whereKey($location->id))->count(),
        ]);
        if ($references) {
            $list = implode(', ', array_map(fn ($n, $what) => "{$n} {$what}", $references, array_keys($references)));

            return response()->json([
                'message' => "Cannot delete this site: it still has {$list} on record.",
            ], 422);
        }

        $location->delete();

        ActivityLog::createAndNotify([
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
    /**
     * Required: the ONE program this location belongs to. Staff assigned here
     * take their program from it. (A one-element program_ids array from an
     * older caller is accepted too.)
     *
     * @return int[] the program id, as a one-item list for linkPrograms()
     */
    private function validateProgram(Request $request): array
    {
        if (! $request->filled('program_id') && is_array($request->input('program_ids'))) {
            $ids = array_values($request->input('program_ids'));
            if (count($ids) > 1) {
                throw ValidationException::withMessages(['program_id' => 'A location belongs to one program only.']);
            }
            $request->merge(['program_id' => $ids[0] ?? null]);
        }

        $validated = $request->validate([
            'program_id' => 'required|integer|exists:programs,id',
        ], [
            'program_id.required' => 'Select the program this location belongs to.',
        ]);

        return [(int) $validated['program_id']];
    }

    /**
     * Save the location's programs. A program whose "first school"
     * (programs.location_id, kept for older readers) was this location and
     * no longer is moves to its next school — otherwise Program's saved hook
     * would quietly link it back.
     */
    private function linkPrograms(Location $location, array $programIds): void
    {
        $location->programs()->sync($programIds);

        Program::where('location_id', $location->id)->whereNotIn('id', $programIds)->get()
            ->each(fn (Program $program) => $program->updateQuietly([
                'location_id' => $program->locations()->orderBy('location_program.id')->value('locations.id'),
            ]));
        Program::whereIn('id', $programIds)->whereNull('location_id')->update(['location_id' => $location->id]);
    }

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
