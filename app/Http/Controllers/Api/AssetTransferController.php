<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetMovement;
use App\Models\AssetTransfer;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetTransferController extends Controller
{
    public function index()
    {
        return response()->json(
            AssetTransfer::with(['asset', 'fromLocation', 'toLocation', 'requester'])->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'reason' => 'nullable|string',
            'transfer_date' => 'required|date',
        ]);

        $validated['requested_by'] = Auth::id();
        $validated['status'] = Auth::user()->isOperationsHrManager() ? 'approved' : 'pending';

        $transfer = AssetTransfer::create($validated);

        if ($transfer->status === 'approved') {
            $this->processTransfer($transfer);
        } else {
            $admin = User::where('role', 'operations_hr_manager')->first();
            if ($admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'transfer_request',
                    'message' => 'New asset transfer request for ' . ($transfer->asset->name ?? 'Asset'),
                    'url' => null,
                ]);
            }
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => ($transfer->status === 'approved' ? 'Transferred' : 'Requested transfer of') . ' asset',
        ]);

        return response()->json($transfer->fresh(['asset', 'fromLocation', 'toLocation', 'requester']), 201);
    }

    public function approve(AssetTransfer $asset_transfer)
    {
        $asset_transfer->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
        ]);

        $this->processTransfer($asset_transfer);

        Notification::create([
            'user_id' => $asset_transfer->requested_by,
            'type' => 'transfer_approved',
            'message' => 'Your transfer request has been approved.',
            'url' => null,
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved asset transfer',
        ]);

        return response()->json($asset_transfer->fresh(['asset', 'fromLocation', 'toLocation']));
    }

    public function reject(AssetTransfer $asset_transfer)
    {
        $asset_transfer->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return response()->json($asset_transfer->fresh());
    }

    public function destroy(AssetTransfer $asset_transfer)
    {
        if ($asset_transfer->status === 'approved') {
            return response()->json(['message' => 'Cannot delete an approved transfer.'], 422);
        }
        $asset_transfer->delete();
        return response()->json(['message' => 'Transfer deleted.']);
    }

    private function processTransfer(AssetTransfer $transfer)
    {
        DB::transaction(function () use ($transfer) {
            AssetMovement::create([
                'asset_id' => $transfer->asset_id,
                'from_location_id' => $transfer->from_location_id,
                'to_location_id' => $transfer->to_location_id,
                'movement_type' => 'transfer',
                'quantity' => 1,
                'reference_no' => 'TRF-' . $transfer->id,
                'created_by' => Auth::id(),
            ]);
        });
    }
}
