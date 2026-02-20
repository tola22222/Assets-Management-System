<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Staff;
use App\Models\Location;
use App\Models\Program;
use App\Models\AssetAssignment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetAssignmentController extends Controller
{
    public function index()
    {
        $assignments = AssetAssignment::with(['asset', 'location'])->latest()->get();

        $assets = Asset::where('status', 'active')->get();
        $staffs = Staff::where('status', 'active')->get();
        $locations = Location::all();
        $programs = Program::all();

        return view('asset-assignments.index', compact('assignments', 'assets', 'staffs', 'locations', 'programs'));
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

        DB::transaction(function () use ($data) {

            // 1️⃣ Get stock for this asset + location
            $stock = DB::table('asset_stock')
                ->where('asset_id', $data['asset_id'])
                ->where('location_id', $data['location_id'])
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->quantity < $data['quantity']) {
                throw new \Exception('Not enough stock available.');
            }

            // 2️⃣ Create assignment
            $data['status'] = 'assigned';
            $assignment = AssetAssignment::create($data);

            // 3️⃣ Decrease stock
            DB::table('asset_stock')
                ->where('id', $stock->id)
                ->update([
                    'quantity' => $stock->quantity - $data['quantity'],
                    'updated_at' => now()
                ]);

            // 4️⃣ Log movement
            DB::table('asset_movements')->insert([
                'asset_id' => $data['asset_id'],
                'from_location_id' => $data['location_id'],
                'to_location_id' => null,
                'movement_type' => 'stock_out',
                'quantity' => $data['quantity'],
                'reference_no' => 'ASSIGN-' . $assignment->id,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5️⃣ Activity log
            ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Assignment',
                'description' => "Assigned asset ID {$data['asset_id']} (Qty: {$data['quantity']})",
            ]);
        });

        return redirect()->back()->with('success', 'Asset assigned and stock updated!');
    }

    public function update(Request $request, AssetAssignment $assetAssignment)
    {
        $data = $request->validate([
            'status' => 'required|in:assigned,returned',
            'location_id' => 'required|exists:locations,id',
        ]);

        DB::transaction(function () use ($data, $assetAssignment) {

            // Update assignment
            $assetAssignment->update($data);

            // If returned, update asset status safely
            if ($data['status'] === 'returned') {
                $asset = $assetAssignment->asset;

                // Get ENUM values for status
                $enumValues = $this->getAssetEnumValues('status');

                // Set status to 'available' if allowed, otherwise 'active'
                $asset->status = in_array('available', $enumValues) ? 'available' : 'active';
                $asset->save();

                // Increase stock back
                $stock = DB::table('asset_stock')
                    ->where('asset_id', $asset->id)
                    ->where('location_id', $assetAssignment->location_id)
                    ->first();

                if ($stock) {
                    DB::table('asset_stock')
                        ->where('id', $stock->id)
                        ->update([
                            'quantity' => $stock->quantity + $assetAssignment->quantity,
                            'updated_at' => now()
                        ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Assignment updated.');
    }

    /**
     * Helper to get ENUM values for a column in assets table
     */
    private function getAssetEnumValues($column)
    {
        $result = DB::select("SHOW COLUMNS FROM assets WHERE Field = ?", [$column]);
        if (!$result) return [];

        $type = $result[0]->Type ?? '';
        preg_match("/^enum\('(.*)'\)$/", $type, $matches);
        return $matches ? explode("','", $matches[1]) : [];
    }
}
