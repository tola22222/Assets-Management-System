<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Notification;
use App\Services\AssetCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function index()
    {
        return response()->json(Asset::with('category')->latest()->get());
    }

    public function show(Asset $asset)
    {
        return response()->json($asset->load([
            'category',
            'stocks.location',
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

        $category = AssetCategory::find($validated['category_id']);
        $validated['asset_code'] = AssetCodeService::nextCode($category);

        $asset = Asset::create($validated);
        AssetCodeService::generateQrCode($asset);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Registered asset: ' . $asset->name . ' (' . $asset->asset_code . ')',
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_registered',
            'message' => 'Asset registered: ' . $asset->name . ' (' . $asset->asset_code . ')',
            'url' => null,
        ]);

        return response()->json($asset->fresh('category'), 201);
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

        if (!$asset->qr_code_path) {
            AssetCodeService::generateQrCode($asset);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated asset: ' . $asset->name,
        ]);

        return response()->json($asset->fresh('category'));
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
            'description' => 'Deleted asset: ' . $asset->name,
        ]);

        return response()->json(['message' => 'Asset deleted.']);
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
