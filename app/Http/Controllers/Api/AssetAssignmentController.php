<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetAssignment;
use App\Models\AssetStock;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
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

        $validated['status'] = 'assigned';
        $assignment = AssetAssignment::create($validated);

        $stock = AssetStock::where('asset_id', $validated['asset_id'])
            ->where('location_id', $validated['location_id'])
            ->first();

        if ($stock && $stock->quantity >= $validated['quantity']) {
            $stock->decrement('quantity', $validated['quantity']);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Assign',
            'description' => 'Assigned asset to ' . $assignment->recipient_name,
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_assigned',
            'message' => 'Asset assigned to ' . $assignment->recipient_name,
            'url' => null,
        ]);

        return response()->json($assignment->fresh(['asset', 'location']), 201);
    }

    public function update(Request $request, AssetAssignment $assetAssignment)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'due_date' => 'nullable|date',
            'status' => 'required|in:assigned,active,returned',
        ]);

        $assetAssignment->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated assignment for ' . $assetAssignment->recipient_name,
        ]);

        return response()->json($assetAssignment->fresh(['asset', 'location']));
    }

    public function cancel(AssetAssignment $assetAssignment)
    {
        $assetAssignment->update(['status' => 'returned']);

        $stock = AssetStock::firstOrCreate(
            ['asset_id' => $assetAssignment->asset_id, 'location_id' => $assetAssignment->location_id],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $assetAssignment->quantity);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Cancel',
            'description' => 'Cancelled assignment for ' . $assetAssignment->recipient_name,
        ]);

        return response()->json($assetAssignment->fresh());
    }

    public function returnAsset(Request $request, AssetAssignment $assetAssignment)
    {
        $validated = $request->validate([
            'condition' => 'required|string',
            'remark' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $data = ['status' => 'returned'];

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('assignments', 'public');
        }

        $assetAssignment->update($data);

        $stock = AssetStock::firstOrCreate(
            ['asset_id' => $assetAssignment->asset_id, 'location_id' => $assetAssignment->location_id],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $assetAssignment->quantity);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Return',
            'description' => 'Processed return for ' . $assetAssignment->recipient_name,
        ]);

        return response()->json($assetAssignment->fresh());
    }

    public function history(AssetAssignment $assetAssignment)
    {
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
