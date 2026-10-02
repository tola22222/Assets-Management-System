<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AssetCodeException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\Notification;
use App\Models\User;
use App\Services\AssetCodeService;
use App\Services\AssetNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        // Staff see only the assets at their assigned location (none until
        // HR sets it); every other role sees all sites.
        $query = Asset::visibleTo($request->user())
            ->with(['category', 'location', 'supplier', 'assignments' => fn ($q) => $q->whereIn('status', AssetAssignment::CURRENT_STATUSES)->latest('assigned_date')]);

        return response()->json($query->latest()->latest('id')->get());
    }

    public function show(Request $request, Asset $asset)
    {
        abort_unless($request->user()->canAccessAsset($asset), 404);

        return response()->json($asset->load([
            'category',
            'location',
            'supplier',
            'assignments' => fn ($q) => $q->latest(),
            'verifications' => fn ($q) => $q->latest(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validateAsset($request, null);

        // How many identical units to register. Each becomes its own asset
        // with its own code (the next in the sequence), QR code and history —
        // the same as one import row per unit.
        $quantity = (int) ($request->validate([
            'quantity' => 'nullable|integer|min:1|max:'.Asset::MAX_BATCH_QUANTITY,
        ])['quantity'] ?? 1);

        // A serial number identifies one physical unit.
        if ($quantity > 1 && ! empty($validated['serial_number'])) {
            throw ValidationException::withMessages([
                'serial_number' => 'A serial number belongs to one unit. Leave it empty when adding more than one, then add each serial on its asset.',
            ]);
        }

        // A new asset is always on the register; it can only leave it through
        // a disposal request the Executive Director approves.
        if ($validated['status'] !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'A new asset must be registered as active. Disposal goes through a disposal request.',
            ]);
        }

        // Before the upload: a rejected code leaves no orphaned photo behind.
        try {
            $validated['asset_code'] = AssetCodeService::nextCode($validated['location_id'], $validated['category_id']);
        } catch (AssetCodeException $e) {
            // A site with no code / a category with no short code is a fixable
            // setup problem, not a server fault — report it on the field that
            // owns the fix instead of throwing a 500 at the register form.
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('assets', 'public');
        }

        $assets = DB::transaction(function () use ($validated, $quantity) {
            $assets = [];
            for ($i = 0; $i < $quantity; $i++) {
                $row = $validated;
                if ($i > 0) {
                    $row['asset_code'] = AssetCodeService::nextCode($validated['location_id'], $validated['category_id']);
                    // Each unit gets its own copy of the photo, so editing or
                    // replacing one unit's photo never removes another's.
                    if (! empty($validated['image_path'])) {
                        $copy = 'assets/'.Str::random(40).'.'.pathinfo($validated['image_path'], PATHINFO_EXTENSION);
                        Storage::disk('public')->copy($validated['image_path'], $copy);
                        $row['image_path'] = $copy;
                    }
                }
                $assets[] = Asset::create($row);
            }

            return $assets;
        });

        foreach ($assets as $asset) {
            AssetCodeService::generateQrCode($asset);
        }

        $first = $assets[0];
        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => $quantity > 1
                ? "Registered {$quantity} × {$first->name} ({$first->asset_code} – ".end($assets)->asset_code.')'
                : 'Registered asset: '.$first->name.' ('.$first->asset_code.')',
        ]);

        $response = $first->fresh(['category', 'location', 'supplier']);
        $response->setAttribute('created_count', $quantity);
        $response->setAttribute('created_codes', array_map(fn (Asset $a) => $a->asset_code, $assets));

        return response()->json($response, 201);
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $this->validateAsset($request, $asset);

        // Writing an asset off (or bringing a written-off one back) is the
        // Executive Director's decision via a disposal request, never an edit.
        if ($validated['status'] !== $asset->status && in_array('disposed', [$validated['status'], $asset->status], true)) {
            throw ValidationException::withMessages([
                'status' => 'Disposal status can only change through an approved disposal request.',
            ]);
        }

        // While a transfer is open the receiving site decides where the asset
        // ends up; editing the location here would race that decision.
        if ((int) $validated['location_id'] !== (int) $asset->location_id
            && AssetTransfer::involvingAsset($asset->id)->whereIn('status', AssetTransfer::OPEN_STATUSES)->exists()) {
            throw ValidationException::withMessages([
                'location_id' => 'This asset has an open transfer. Its location changes when the transfer is accepted.',
            ]);
        }

        if ($request->hasFile('image')) {
            if ($asset->image_path) {
                Storage::disk('public')->delete($asset->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('assets', 'public');
        }

        $asset->update($validated);

        if (! $asset->qr_code_path) {
            AssetCodeService::generateQrCode($asset);
        }

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated asset: '.$asset->name,
        ]);

        return response()->json($asset->fresh(['category', 'location', 'supplier']));
    }

    public function destroy(Asset $asset)
    {
        // Deleting would erase (or, for counts, fail on) the asset's audit
        // trail. An asset with any history leaves the register by disposal.
        $history = [
            'assignments' => $asset->assignments()->exists(),
            'transfers' => AssetTransfer::involvingAsset($asset->id)->exists(),
            'disposal requests' => $asset->disposals()->exists(),
            'verifications' => $asset->verifications()->exists(),
        ];
        $has = array_keys(array_filter($history));
        if ($has) {
            return response()->json([
                'message' => 'This asset has '.implode(', ', $has).' on record and cannot be deleted. Submit a disposal request instead.',
            ], 422);
        }

        $files = array_filter([$asset->qr_code_path, $asset->image_path]);
        $asset->delete();

        // Files go only once the row is gone, so a failed delete never leaves
        // a live asset without its QR code or photo.
        foreach ($files as $path) {
            Storage::disk('public')->delete($path);
        }

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted asset: '.$asset->name,
        ]);

        return response()->json(['message' => 'Asset deleted.']);
    }

    public function flagIssue(Request $request, Asset $asset)
    {
        abort_unless($request->user()->canAccessAsset($asset), 404);

        $validated = $request->validate([
            'note' => 'required|string|max:1000',
            'condition' => 'nullable|string|in:broken,lost',
        ]);

        if (! empty($validated['condition'])) {
            $asset->update(['condition' => $validated['condition']]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Flag',
            'description' => 'Flagged issue on asset: '.$asset->name.' ('.$asset->asset_code.') — '.$validated['note'],
        ]);

        $recipients = User::whereIn('role', ['operations_hr_manager', 'executive_director', 'finance_manager'])->get();
        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'type' => 'asset_flagged',
                'message' => Auth::user()->name.' flagged an issue on '.$asset->name.' ('.$asset->asset_code.'): '.$validated['note'],
                'url' => null,
            ]);
        }

        (new AssetNotificationService)->send('DAMAGE_FLAGGED', [
            'assetId' => $asset->asset_code,
            'assetDbId' => $asset->id,
            'description' => $asset->name,
            'location' => $asset->location->name ?? null,
            'category' => $asset->category->name ?? null,
            'flaggedBy' => Auth::user(),
            'note' => $validated['note'],
            'url' => route('asset.public.show', $asset->asset_code),
            'extraData' => [
                'status' => $validated['condition'] ?? 'flagged',
                'flaggedAt' => now()->format('d M Y, H:i'),
            ],
        ]);

        return response()->json($asset->fresh(['category', 'location']));
    }

    public function regenerateQr(Asset $asset)
    {
        if ($asset->qr_code_path) {
            Storage::disk('public')->delete($asset->qr_code_path);
        }
        AssetCodeService::generateQrCode($asset);

        return response()->json($asset->fresh());
    }

    /**
     * Serve the QR PNG as an attachment through the API. A plain <a download>
     * on the /storage URL is ignored whenever the SPA runs on a different
     * origin than the backend (Vite dev server), so the browser just opened it.
     */
    public function downloadQr(Request $request, Asset $asset)
    {
        abort_unless($request->user()->canAccessAsset($asset), 404);

        if (! $asset->qr_code_path || ! Storage::disk('public')->exists($asset->qr_code_path)) {
            AssetCodeService::generateQrCode($asset);
            $asset->refresh();
        }

        return Storage::disk('public')->download($asset->qr_code_path, 'qr-'.$asset->asset_code.'.png');
    }

    private function validateAsset(Request $request, ?Asset $asset): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'location_id' => 'required|exists:locations,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'status' => ['required', 'string', Rule::in(Asset::STATUSES)],
            'description' => 'nullable|string',
            'model' => 'nullable|string',
            'brand' => 'nullable|string',
            'serial_number' => ['nullable', 'string', Rule::unique('assets', 'serial_number')->ignore($asset?->id)],
            'condition' => ['nullable', 'string', Rule::in(Asset::CONDITIONS)],
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'serial_number.unique' => 'This serial number is already registered to another asset.',
        ]);
    }
}
