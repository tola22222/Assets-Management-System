<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetTransferController extends Controller
{
    public function index()
    {
        $transfers = AssetTransfer::with(['asset', 'fromLocation', 'toLocation', 'requester'])
            ->latest()
            ->get();
        $assets = Asset::where('status', 'active')->get();
        $locations = Location::all();
        return view('asset-transfers.index', compact('transfers', 'assets', 'locations'));
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
            Notification::create([
                'user_id' => User::where('role', 'operations_hr_manager')->first()->id,
                'type' => 'transfer_request',
                'message' => 'New asset transfer request for ' . ($transfer->asset->name ?? 'Asset'),
                'url' => route('asset-transfers.index'),
            ]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => ($transfer->status === 'approved' ? 'Transferred' : 'Requested transfer of') . ' asset',
        ]);

        return redirect()->route('asset-transfers.index')->with('success', 'Transfer ' . ($transfer->status === 'approved' ? 'completed' : 'requested') . ' successfully.');
    }

    public function approve(AssetTransfer $transfer)
    {
        $transfer->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
        ]);

        $this->processTransfer($transfer);

        Notification::create([
            'user_id' => $transfer->requested_by,
            'type' => 'transfer_approved',
            'message' => 'Your transfer request has been approved.',
            'url' => route('asset-transfers.index'),
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved asset transfer',
        ]);

        return redirect()->route('asset-transfers.index')->with('success', 'Transfer approved successfully.');
    }

    public function reject(Request $request, AssetTransfer $transfer)
    {
        $transfer->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return redirect()->route('asset-transfers.index')->with('success', 'Transfer rejected.');
    }

    public function destroy(AssetTransfer $transfer)
    {
        if ($transfer->status === 'approved') {
            return redirect()->route('asset-transfers.index')->with('error', 'Cannot delete an approved transfer.');
        }
        $transfer->delete();
        return redirect()->route('asset-transfers.index')->with('success', 'Transfer deleted.');
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
