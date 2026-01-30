<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Staff;
use App\Models\Location;
use App\Models\AssetAssignment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetAssignmentController extends Controller
{
    public function index()
    {
        // Eager load the asset and location. 
        // Note: For assigned_to, you'll likely use a polymorphic relation in the Model
        $assignments = AssetAssignment::with(['asset', 'location'])->latest()->get();

        $assets = Asset::where('status', 'active')->get();
        $staffs = Staff::where('status', 'active')->get();
        $locations = Location::all();

        return view('asset-assignments.index', compact('assignments', 'assets', 'staffs', 'locations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'assigned_to_type' => 'required|in:staff,program',
            'assigned_to_id' => 'required',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'assigned_date' => 'required|date',
        ]);

        $data['status'] = 'assigned';

        $assignment = AssetAssignment::create($data);

        $asset = Asset::findOrFail($data['asset_id']);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Assignment',
            'description' => "Assigned Asset {$asset->name}",
        ]);

        return redirect()->back()->with('success', 'Asset assigned successfully!');
    }


    public function update(Request $request, AssetAssignment $assetAssignment)
    {
        $data = $request->validate([
            'status' => 'required|in:assigned,returned',
            'location_id' => 'required|exists:locations,id',
        ]);

        $assetAssignment->update($data);

        if ($request->status == 'returned') {
            $assetAssignment->asset->update(['status' => 'available']);
        }

        return redirect()->back()->with('success', 'Assignment updated.');
    }
}
