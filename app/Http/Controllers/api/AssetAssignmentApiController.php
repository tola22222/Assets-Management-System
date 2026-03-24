<?php
namespace App\Http\Controllers\Api; // FIXED: Uppercase 'A'

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetAssignmentApiController extends Controller
{
    public function index()
    {
        $assignments = AssetAssignment::with(['asset', 'location'])->latest()->get();
        return response()->json(['status' => true, 'data' => $assignments]);
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

        try {
            $result = DB::transaction(function () use ($data) {
                // 1. Lock Stock for update
                $stock = DB::table('asset_stock')
                    ->where('asset_id', $data['asset_id'])
                    ->where('location_id', $data['location_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $data['quantity']) {
                    throw new \Exception('Insufficient stock available at this location.');
                }

                // 2. Create Assignment
                $data['status'] = 'assigned';
                $assignment = AssetAssignment::create($data);

                // 3. Decrease Stock
                DB::table('asset_stock')
                    ->where('id', $stock->id)
                    ->decrement('quantity', $data['quantity']);

                // 4. Log Movement
                DB::table('asset_movements')->insert([
                    'asset_id' => $data['asset_id'],
                    'from_location_id' => $data['location_id'],
                    'movement_type' => 'stock_out',
                    'quantity' => $data['quantity'],
                    'reference_no' => 'ASSIGN-' . $assignment->id,
                    'created_by' => Auth::id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 5. Activity Log
                ActivityLog::create([
                    'user_id' => Auth::id() ?? 1,
                    'action' => 'Assignment',
                    'description' => "Assigned asset ID {$data['asset_id']} (Qty: {$data['quantity']})",
                ]);

                return $assignment;
            });

            return response()->json([
                'status' => true, 
                'message' => 'Asset assigned successfully', 
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => $e->getMessage()
            ], 400);
        }
    }
}