<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesAndSorts;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetMaintenanceController extends Controller
{
    use PaginatesAndSorts;

    private const SORTABLE = ['status', 'reported_date', 'cost', 'created_at'];

    public function index(Request $request)
    {
        $query = AssetMaintenance::with(['asset', 'staff']);

        $this->applySearch($query, $request, ['issue'], [
            'asset' => ['name', 'asset_code'],
        ]);
        $this->applyExactFilters($query, $request, ['status']);

        return response()->json($this->paginateSorted($query, $request, self::SORTABLE));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'issue' => 'required|string',
            'reported_date' => 'required|date',
            'cost' => 'nullable|numeric',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $validated['status'] = 'pending';
        $validated['reported_by'] = Auth::id();

        $record = AssetMaintenance::create($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Reported maintenance issue for asset: '.($record->asset->name ?? ''),
        ]);

        return response()->json($record->fresh(['asset', 'staff']), 201);
    }

    public function update(Request $request, AssetMaintenance $asset_maintenance)
    {
        $validated = $request->validate([
            'issue' => 'sometimes|string',
            'reported_date' => 'sometimes|date',
            'cost' => 'nullable|numeric',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $asset_maintenance->update($validated);

        return response()->json($asset_maintenance->fresh(['asset', 'staff']));
    }

    public function start(AssetMaintenance $asset_maintenance)
    {
        $asset_maintenance->update(['status' => 'in_progress']);

        return response()->json($asset_maintenance->fresh(['asset', 'staff']));
    }

    public function complete(AssetMaintenance $asset_maintenance)
    {
        $asset_maintenance->update(['status' => 'completed', 'completed_at' => now()]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Complete Maintenance',
            'description' => 'Completed maintenance for asset: '.($asset_maintenance->asset->name ?? ''),
        ]);

        return response()->json($asset_maintenance->fresh(['asset', 'staff']));
    }

    public function destroy(AssetMaintenance $asset_maintenance)
    {
        $asset_maintenance->delete();

        return response()->json(['message' => 'Maintenance record deleted.']);
    }
}
