<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Notification;
use App\Models\User;
use App\Services\AssetCodeService;
use App\Services\AssetNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function index()
    {
        return response()->json(Asset::with(['category', 'location'])->latest()->get());
    }

    public function show(Asset $asset)
    {
        return response()->json($asset->load([
            'category',
            'location',
            'assignments' => fn ($q) => $q->latest(),
            'verifications' => fn ($q) => $q->latest(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validateAsset($request);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('assets', 'public');
        }

        $validated['asset_code'] = AssetCodeService::nextCode($validated['location_id'], $validated['category_id']);

        $asset = Asset::create($validated);
        AssetCodeService::generateQrCode($asset);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Registered asset: '.$asset->name.' ('.$asset->asset_code.')',
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_registered',
            'message' => 'Asset registered: '.$asset->name.' ('.$asset->asset_code.')',
            'url' => null,
        ]);

        return response()->json($asset->fresh(['category', 'location']), 201);
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $this->validateAsset($request);

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

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated asset: '.$asset->name,
        ]);

        return response()->json($asset->fresh(['category', 'location']));
    }

    public function destroy(Asset $asset)
    {
        if ($asset->qr_code_path) {
            Storage::disk('public')->delete($asset->qr_code_path);
        }
        if ($asset->image_path) {
            Storage::disk('public')->delete($asset->image_path);
        }
        $asset->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted asset: '.$asset->name,
        ]);

        return response()->json(['message' => 'Asset deleted.']);
    }

    public function flagIssue(Request $request, Asset $asset)
    {
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

    private function validateAsset(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'location_id' => 'required|exists:locations,id',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'status' => 'required|string',
            'description' => 'nullable|string',
            'model' => 'nullable|string',
            'brand' => 'nullable|string',
            'serial_number' => 'nullable|string',
            'condition' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);
    }
}
