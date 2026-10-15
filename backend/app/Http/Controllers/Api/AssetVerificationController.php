<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetVerification;
use App\Services\AssetStockService;
use App\Services\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetVerificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Staff see verifications recorded at their program's schools — none
        // until HR assigns a program (fail closed, the same rule as the register).
        // Everyone else (OPM, Finance, ED) sees every site.
        $verifications = AssetVerification::with(['asset', 'location', 'verifiedBy'])
            ->when($user->isSiteScoped(), fn ($q) => $q->whereIn('location_id', $user->siteLocationIds()))
            ->latest()
            ->latest('id')
            ->get();

        return response()->json($verifications);
    }

    public function store(Request $request, AssetStockService $stock)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'location_id' => 'required|exists:locations,id',
            'quantity_verified' => 'required|integer|min:1',
            'condition' => 'required|in:good,fair,broken,lost',
            // Broken or lost takes the unit out of use: say why, for the record.
            'remark' => 'nullable|string|required_if:condition,broken,lost',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'remark.required_if' => 'Enter the reason the asset is broken or lost.',
        ]);

        // A verification confirms the asset where the register has it. Moving
        // it between locations is a transfer, so the stock of both locations
        // and the transfer history stay right.
        $asset = Asset::with('location')->findOrFail($validated['asset_id']);

        // Staff verify only at their own sites — the same rule as the QR scan.
        $user = $request->user();
        if ($user->isSiteScoped() && (! $user->canAccessAsset($asset) || ! $user->canAccessLocation((int) $validated['location_id']))) {
            return response()->json(['message' => 'Asset not in your site'], 403);
        }

        if ($asset->location_id !== null && (int) $asset->location_id !== (int) $validated['location_id']) {
            return response()->json([
                'message' => 'This asset is recorded at '.($asset->location->name ?? 'another location').'. Verify it there, or transfer it to the new location first.',
                'errors' => ['location_id' => ['This asset is recorded at '.($asset->location->name ?? 'another location').'.']],
            ], 422);
        }

        // Never more than the units of this model the location has: 10 Dell
        // laptops there means a count of 1 to 10.
        // (At least 1: the asset being verified, even one with no location yet.)
        $total = max(1, $stock->forAsset($asset, null, [(int) $validated['location_id']])['total']);
        if ($validated['quantity_verified'] > $total) {
            $message = "Only {$total} of \"{$asset->name}\" at ".($asset->location->name ?? 'this location')." — you entered {$validated['quantity_verified']}.";

            return response()->json(['message' => $message, 'errors' => ['quantity_verified' => [$message]]], 422);
        }

        $validated['verified_by'] = Auth::id();
        // Audit: what it was before, and whether this took it out of use. The
        // asset row and its code stay — only the condition changes.
        $validated['previous_condition'] = $asset->condition;
        $validated['quantity_affected'] = in_array($validated['condition'], Asset::UNAVAILABLE_CONDITIONS, true) ? 1 : 0;
        $validated['verified_at'] = now();

        if ($request->hasFile('image')) {
            $validated['image_path'] = ImageCompressor::store($request->file('image'), 'verifications');
        }

        $verification = AssetVerification::create($validated);

        // The counted condition is the asset's condition now, good or bad.
        Asset::where('id', $validated['asset_id'])->update(['condition' => $validated['condition']]);

        // One entry: the activity log, plus the same line in the bell for the
        // admins and the verifier (this used to notify only the verifier).
        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Verification',
            'description' => 'Verified asset: '.($verification->asset->name ?? '').' ('.$validated['condition'].')',
        ]);

        return response()->json($verification->fresh(['asset', 'location']), 201);
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
