<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetStock;
use App\Models\Location;
use App\Models\Staff;
use App\Models\Program;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetAssignmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->isOperationsHrManager()) {
            $assignments = AssetAssignment::with(['asset', 'assignee', 'location'])->latest()->get();
        } else {
            $assignments = AssetAssignment::where('assigned_to_type', 'staff')
                ->where('assigned_to_id', $user->staff_id)
                ->with(['asset', 'location'])
                ->latest()
                ->get();
        }
        $assets = Asset::where('status', 'active')->get();
        $locations = Location::all();
        $staffList = Staff::where('status', 'active')->get();
        $programs = Program::all();
        return view('asset-assignments.index', compact('assignments', 'assets', 'locations', 'staffList', 'programs'));
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

        // Verify polymorphic relation
        if ($validated['assigned_to_type'] === 'staff') {
            $assignee = Staff::findOrFail($validated['assigned_to_id']);
        } else {
            $assignee = Program::findOrFail($validated['assigned_to_id']);
        }

        $validated['status'] = 'assigned';

        $assignment = AssetAssignment::create($validated);

        // Decrement stock
        $stock = AssetStock::where('asset_id', $validated['asset_id'])
            ->where('location_id', $validated['location_id'])
            ->first();

        if ($stock && $stock->quantity >= $validated['quantity']) {
            $stock->decrement('quantity', $validated['quantity']);
        }

        $recipientName = $assignment->recipient_name;

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Assign',
            'description' => 'Assigned asset to ' . $recipientName,
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_assigned',
            'message' => 'Asset assigned to ' . $recipientName,
            'url' => route('asset-assignments.index'),
        ]);

        return redirect()->route('asset-assignments.index')->with('success', 'Asset assigned successfully.');
    }

    public function show(AssetAssignment $assetAssignment)
    {
        $assetAssignment->load(['asset', 'assignee', 'location', 'returns']);
        return view('asset-assignments.show', compact('assetAssignment'));
    }

    public function edit(AssetAssignment $assetAssignment)
    {
        return response()->json($assetAssignment->load(['asset', 'assignee']));
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

        return redirect()->route('asset-assignments.index')->with('success', 'Assignment updated successfully.');
    }

    public function cancel(AssetAssignment $assetAssignment)
    {
        $assetAssignment->update(['status' => 'returned']);

        // Restore stock
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

        return redirect()->route('asset-assignments.index')->with('success', 'Assignment cancelled.');
    }

    public function returnAsset(Request $request, AssetAssignment $assetAssignment)
    {
        $validated = $request->validate([
            'condition' => 'required|string',
            'remark' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $data = [
            'status' => 'returned',
        ];

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('assignments', 'public');
        }

        $assetAssignment->update($data);

        // Restore stock
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

        return redirect()->route('asset-assignments.index')->with('success', 'Asset returned successfully.');
    }

    public function history(AssetAssignment $assetAssignment)
    {
        $history = AssetAssignment::where('asset_id', $assetAssignment->asset_id)
            ->with(['assignee', 'location'])
            ->latest()
            ->get();
        return response()->json($history);
    }

    public function destroy(AssetAssignment $assetAssignment)
    {
        if ($assetAssignment->status === 'returned') {
            $assetAssignment->delete();
            return redirect()->route('asset-assignments.index')->with('success', 'Assignment deleted.');
        }
        return redirect()->route('asset-assignments.index')->with('error', 'Cannot delete active assignment.');
    }
}
