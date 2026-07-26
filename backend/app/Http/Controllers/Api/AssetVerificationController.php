<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesAndSorts;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetVerification;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetVerificationController extends Controller
{
    use PaginatesAndSorts;

    private const SORTABLE = ['condition', 'created_at'];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = AssetVerification::with(['asset', 'location', 'verifiedBy']);

        if (! $user->isOperationsHrManager()) {
            $assignedAssetIds = AssetAssignment::where('assigned_to_type', 'staff')
                ->where('assigned_to_id', $user->staff_id)
                ->pluck('asset_id');
            $query->whereIn('asset_id', $assignedAssetIds);
        }

        $this->applySearch($query, $request, ['remark'], [
            'asset' => ['name', 'asset_code'],
            'location' => ['name'],
        ]);
        $this->applyExactFilters($query, $request, ['condition']);

        if ($request->filled('completed')) {
            $request->query('completed') === 'yes'
                ? $query->whereNotNull('verified_at')
                : $query->whereNull('verified_at');
        }

        return response()->json($this->paginateSorted($query, $request, self::SORTABLE));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'location_id' => 'required|exists:locations,id',
            'quantity_verified' => 'required|integer|min:1',
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $validated['verified_by'] = Auth::id();
        $validated['verified_at'] = now();

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('verifications', 'public');
        }

        $verification = AssetVerification::create($validated);

        if (in_array($validated['condition'], ['broken', 'lost'])) {
            Asset::where('id', $validated['asset_id'])->update(['condition' => $validated['condition']]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Verification',
            'description' => 'Verified asset: '.($verification->asset->name ?? ''),
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_verified',
            'message' => 'Verified asset: '.($verification->asset->name ?? '').' ('.$validated['condition'].')',
            'url' => null,
        ]);

        return response()->json($verification->fresh(['asset', 'location']), 201);
    }

    public function update(Request $request, AssetVerification $asset_verification)
    {
        $validated = $request->validate([
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string',
        ]);

        $asset_verification->update($validated);

        if (in_array($validated['condition'], ['broken', 'lost'])) {
            $asset_verification->asset()->update(['condition' => $validated['condition']]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update Verification',
            'description' => 'Updated verification condition to '.$validated['condition'].' for asset: '.($asset_verification->asset->name ?? ''),
        ]);

        return response()->json($asset_verification->fresh(['asset', 'location']));
    }

    public function complete(AssetVerification $asset_verification)
    {
        $asset_verification->update(['verified_at' => now()]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Complete Verification',
            'description' => 'Completed verification',
        ]);

        return response()->json($asset_verification->fresh());
    }

    public function destroy(AssetVerification $asset_verification)
    {
        $asset_verification->delete();

        return response()->json(['message' => 'Verification deleted.']);
    }
}
