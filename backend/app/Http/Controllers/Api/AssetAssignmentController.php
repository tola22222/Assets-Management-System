<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // OPM, Finance (who create and edit assignments) and the ED see every
        // assignment; staff see the ones made to them.
        if (! $user->isStaff()) {
            $assignments = AssetAssignment::with(['asset', 'location'])->latest()->get();
        } else {
            $assignments = AssetAssignment::where('assigned_to_type', 'staff')
                ->where('assigned_to_id', $user->staff_id)
                ->with(['asset', 'location'])
                ->latest()
                ->get();
        }

        return response()->json($assignments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'assigned_to_type' => 'required|in:staff,program',
            'assigned_to_id' => 'required|integer',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'assigned_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:assigned_date',
        ]);

        if ($validated['assigned_to_type'] === 'staff') {
            Staff::findOrFail($validated['assigned_to_id']);
        } else {
            Program::findOrFail($validated['assigned_to_id']);
        }

        if (Asset::whereKey($validated['asset_id'])->value('status') === 'disposed') {
            return response()->json(['message' => 'This asset has been disposed and cannot be assigned.'], 422);
        }

        if (AssetAssignment::where('asset_id', $validated['asset_id'])->whereIn('status', AssetAssignment::CURRENT_STATUSES)->exists()) {
            return response()->json(['message' => 'This asset is already assigned. It must be returned or the assignment cancelled before it can be assigned again.'], 422);
        }

        $validated['status'] = 'assigned';
        $assignment = AssetAssignment::create($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Assign',
            'description' => 'Assigned asset to '.$assignment->recipient_name,
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_assigned',
            'message' => 'Asset assigned to '.$assignment->recipient_name,
            'url' => null,
        ]);

        // …and tell the person who now holds it, if they have a login.
        if ($assignment->assigned_to_type === 'staff') {
            User::where('staff_id', $assignment->assigned_to_id)
                ->where('id', '!=', Auth::id())
                ->get()
                ->each(fn (User $holder) => Notification::create([
                    'user_id' => $holder->id,
                    'type' => 'asset_assigned',
                    'message' => ($assignment->asset->name ?? 'An asset').' has been assigned to you.',
                    'url' => '/app/asset-assignments',
                ]));
        }

        return response()->json($assignment->fresh(['asset', 'location']), 201);
    }

    public function update(Request $request, AssetAssignment $assetAssignment)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'due_date' => 'nullable|date',
            'status' => 'required|in:assigned,active,returned',
        ]);

        // Re-opening a returned assignment must not give the asset two holders.
        if ($assetAssignment->status === 'returned' && $validated['status'] !== 'returned'
            && AssetAssignment::where('asset_id', $assetAssignment->asset_id)
                ->where('id', '!=', $assetAssignment->id)
                ->whereIn('status', AssetAssignment::CURRENT_STATUSES)
                ->exists()) {
            return response()->json(['message' => 'This asset is already assigned to someone else, so this assignment cannot be re-opened.'], 422);
        }

        $assetAssignment->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated assignment for '.$assetAssignment->recipient_name,
        ]);

        return response()->json($assetAssignment->fresh(['asset', 'location']));
    }

    public function cancel(AssetAssignment $assetAssignment)
    {
        $assetAssignment->update(['status' => 'returned']);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Cancel',
            'description' => 'Cancelled assignment for '.$assetAssignment->recipient_name,
        ]);

        return response()->json($assetAssignment->fresh());
    }

    public function returnAsset(Request $request, AssetAssignment $assetAssignment)
    {
        $validated = $request->validate([
            'condition' => ['required', 'string', \Illuminate\Validation\Rule::in(Asset::CONDITIONS)],
            'remark' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $data = ['status' => 'returned'];

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('assignments', 'public');
        }

        $assetAssignment->update($data);

        // The condition it came back in is the asset's condition now.
        $assetAssignment->asset?->update(['condition' => $validated['condition']]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Return',
            'description' => 'Processed return for '.$assetAssignment->recipient_name,
        ]);

        return response()->json($assetAssignment->fresh());
    }

    public function history(Request $request, AssetAssignment $assetAssignment)
    {
        $user = $request->user();
        $ownAssignment = $assetAssignment->assigned_to_type === 'staff' && (int) $assetAssignment->assigned_to_id === (int) $user->staff_id;
        abort_unless($ownAssignment || $user->canAccessLocation($assetAssignment->asset?->location_id), 404);

        $history = AssetAssignment::where('asset_id', $assetAssignment->asset_id)
            ->with(['location'])
            ->latest()
            ->get();

        return response()->json($history);
    }

    public function destroy(AssetAssignment $assetAssignment)
    {
        if ($assetAssignment->status !== 'returned') {
            return response()->json(['message' => 'Cannot delete active assignment.'], 422);
        }

        $assetAssignment->delete();

        return response()->json(['message' => 'Assignment deleted.']);
    }
}
