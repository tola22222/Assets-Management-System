<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetScan;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Notification;
use App\Models\User;
use App\Services\AssetNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in half of the QR flow. A printed tag opens the public
 * `/asset/{code}` page, which shows the asset and nothing else; acting on it
 * means signing in and landing here, where every step is written to
 * `asset_scans` against the account that took it.
 */
class QrScanController extends Controller
{
    /** A reload of the scan page within this window is the same scan, not a new one. */
    private const RESCAN_WINDOW_MINUTES = 2;

    public function scan(Request $request)
    {
        $request->validate(['asset_code' => 'required|string']);

        $asset = Asset::with(['category', 'location'])
            ->where('asset_code', $request->asset_code)
            ->first();

        if (! $asset || $this->outsideStaffSite($request->user(), $asset)) {
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        $user = $request->user();

        $scan = AssetScan::where('asset_id', $asset->id)
            ->where('user_id', $user->id)
            ->where('action', AssetScan::ACTION_SCANNED)
            ->where('created_at', '>=', now()->subMinutes(self::RESCAN_WINDOW_MINUTES))
            ->latest()
            ->first();

        if (! $scan) {
            $scan = $this->logScan($asset, $user, AssetScan::ACTION_SCANNED, ['location_id' => $asset->location_id]);

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'QR Scan',
                'description' => 'Scanned asset QR code: '.$asset->name.' ('.$asset->asset_code.')',
            ]);
        }

        return response()->json([
            'asset' => $asset,
            'scan' => $scan,
            'can_change_location' => $this->staffSiteId($user) === null,
        ]);
    }

    public function result(Request $request, $assetCode)
    {
        $asset = Asset::with([
            'category', 'location',
            'assignments' => fn ($q) => $q->whereIn('status', AssetAssignment::CURRENT_STATUSES),
            'verifications' => fn ($q) => $q->latest(),
            'scans' => fn ($q) => $q->with(['user:id,name', 'location:id,name', 'previousLocation:id,name'])->latest()->take(10),
        ])->where('asset_code', $assetCode)->firstOrFail();

        abort_if($this->outsideStaffSite($request->user(), $asset), 404);

        return response()->json($asset);
    }

    public function verify(Request $request, $assetCode)
    {
        $asset = Asset::where('asset_code', $assetCode)->firstOrFail();
        $user = $request->user();

        abort_if($this->outsideStaffSite($user, $asset), 404);

        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string',
        ]);

        $newLocationId = (int) $validated['location_id'];
        $previousLocationId = $asset->location_id !== null ? (int) $asset->location_id : null;
        $locationChanged = $previousLocationId !== $newLocationId;
        $previousCondition = $asset->condition;

        // A site-scoped staff member can confirm an asset is at their own site,
        // never send it somewhere else: moving an asset onto another site is
        // what the transfer workflow exists for, and that needs the receiving
        // site's own people to accept it.
        $staffSiteId = $this->staffSiteId($user);
        if ($staffSiteId !== null && $newLocationId !== $staffSiteId) {
            return response()->json([
                'message' => 'You can only verify assets at your own site. Ask the Operations & HR Manager to raise a transfer to move this asset.',
                'errors' => ['location_id' => ['You can only verify assets at your own site. Ask the Operations & HR Manager to raise a transfer to move this asset.']],
            ], 422);
        }

        // An open transfer already says where this asset is going, and
        // confirmReceipt() will overwrite location_id when the destination
        // accepts. Moving it underneath that request would leave the two
        // disagreeing about where the asset started.
        if ($locationChanged && AssetTransfer::where('asset_id', $asset->id)->whereIn('status', ['pending_approval', 'pending'])->exists()) {
            return response()->json([
                'message' => 'This asset has a transfer in progress. Its location will update when the receiving site accepts it.',
                'errors' => ['location_id' => ['This asset has a transfer in progress. Its location will update when the receiving site accepts it.']],
            ], 422);
        }

        [$verification, $scan] = DB::transaction(function () use ($asset, $user, $validated, $newLocationId, $previousLocationId, $locationChanged) {
            $verification = AssetVerification::create([
                'asset_id' => $asset->id,
                'location_id' => $newLocationId,
                'verified_by' => $user->id,
                'quantity_verified' => 1,
                'condition' => $validated['condition'],
                'remark' => $validated['remark'] ?? null,
                'verified_at' => now(),
            ]);

            // What the person standing next to the asset reports is its
            // condition now — including good/fair after a repair.
            $changes = [];
            if ($validated['condition'] !== $asset->condition) {
                $changes['condition'] = $validated['condition'];
            }
            if ($locationChanged) {
                $changes['location_id'] = $newLocationId;
            }
            if ($changes) {
                $asset->update($changes);
            }

            $scan = $this->logScan($asset, $user, $locationChanged ? AssetScan::ACTION_LOCATION_UPDATED : AssetScan::ACTION_VERIFIED, [
                'location_id' => $newLocationId,
                'previous_location_id' => $locationChanged ? $previousLocationId : null,
                'condition' => $validated['condition'],
                'remark' => $validated['remark'] ?? null,
                'asset_verification_id' => $verification->id,
            ]);

            $description = 'Verified asset via QR scan: '.$asset->name.' ('.$asset->asset_code.'), condition '.$validated['condition'];
            if ($locationChanged) {
                $names = Location::whereIn('id', array_filter([$previousLocationId, $newLocationId]))->pluck('name', 'id');
                $description .= ', location updated from '.($names[$previousLocationId] ?? 'none').' to '.($names[$newLocationId] ?? 'unknown');
            }

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => $locationChanged ? 'QR Location Update' : 'QR Verification',
                'description' => $description,
            ]);

            return [$verification, $scan];
        });

        // Damage or loss found during a scan must reach OPM the same way a
        // Flag does, not wait for the weekly discrepancy digest.
        if (in_array($validated['condition'], ['broken', 'lost'], true) && $previousCondition !== $validated['condition']) {
            $this->reportDamage($asset->fresh(['location', 'category']), $user, $validated['condition'], $validated['remark'] ?? null);
        }

        return response()->json([
            'verification' => $verification->fresh(['asset', 'location']),
            'scan' => $scan,
            'location_changed' => $locationChanged,
        ]);
    }

    private function logScan(Asset $asset, $user, string $action, array $extra = []): AssetScan
    {
        return AssetScan::create(array_merge([
            'asset_id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'asset_name' => $asset->name,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
        ], $extra));
    }

    /** The site a staff-role user is restricted to, or null when nothing restricts them. */
    private function staffSiteId(User $user): ?int
    {
        return $user->isSiteScoped() ? $user->siteLocationId() : null;
    }

    /**
     * Staff are scoped to their own site once one is assigned. `staff.location_id` is
     * nullable and unpopulated for most existing staff, so this fails OPEN (no
     * restriction) rather than closed when it's unset — see User::canAccessLocation().
     */
    private function outsideStaffSite(User $user, Asset $asset): bool
    {
        return ! $user->canAccessLocation($asset->location_id);
    }

    private function reportDamage(Asset $asset, User $reporter, string $condition, ?string $remark): void
    {
        $note = 'Reported '.$condition.' during a QR scan'.($remark ? ': '.$remark : '.');

        User::whereIn('role', ['operations_hr_manager', 'executive_director', 'finance_manager'])
            ->where('id', '!=', $reporter->id)
            ->get()
            ->each(fn (User $recipient) => Notification::create([
                'user_id' => $recipient->id,
                'type' => 'asset_flagged',
                'message' => $reporter->name.' reported '.$asset->name.' ('.$asset->asset_code.') as '.$condition.($remark ? ': '.$remark : '.'),
                'url' => null,
            ]));

        (new AssetNotificationService)->send('DAMAGE_FLAGGED', [
            'assetId' => $asset->asset_code,
            'assetDbId' => $asset->id,
            'description' => $asset->name,
            'location' => $asset->location->name ?? null,
            'category' => $asset->category->name ?? null,
            'flaggedBy' => $reporter,
            'note' => $note,
            'url' => route('asset.public.show', $asset->asset_code),
            'extraData' => [
                'status' => $condition,
                'flaggedAt' => now()->format('d M Y, H:i'),
            ],
        ]);
    }
}
