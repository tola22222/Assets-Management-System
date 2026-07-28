<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Notification;
use App\Services\AssetCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function index()
    {
        $assets = Asset::with(['category', 'location'])->latest()->get();
        $categories = AssetCategory::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        return view('assets.index', compact('assets', 'categories', 'locations'));
    }

    public function create()
    {
        $categories = AssetCategory::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        return view('assets.create', compact('categories', 'locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'location_id' => 'required|exists:locations,id',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            // A newly registered asset is always active — "disposed" is only
            // ever reachable via an approved AssetDisposal request, never by
            // typing it into this form (see AssetDisposalController::approve()).
            'status' => 'required|in:active',
            'description' => 'nullable|string',
            'model' => 'nullable|string',
            'brand' => 'nullable|string',
            'serial_number' => 'nullable|string',
            'condition' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

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
            'url' => route('assets.show', $asset->id),
        ]);

        return redirect()->route('assets.index')->with('success', 'Asset registered successfully.');
    }

    public function show($id)
    {
        $asset = Asset::with(['category', 'stocks.location', 'assignments' => function ($q) {
            $q->latest();
        }, 'verifications' => function ($q) {
            $q->latest();
        }])->findOrFail($id);

        return view('assets.show', compact('asset'));
    }

    public function edit($id)
    {
        $asset = Asset::findOrFail($id);
        $categories = AssetCategory::all();
        $locations = Location::orderBy('name')->get();

        return view('assets.edit', compact('asset', 'categories', 'locations'));
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'location_id' => 'required|exists:locations,id',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'status' => 'required|in:active,disposed',
            'description' => 'nullable|string',
            'model' => 'nullable|string',
            'brand' => 'nullable|string',
            'serial_number' => 'nullable|string',
            'condition' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        // "disposed" can only be reached via an approved AssetDisposal request
        // (AssetDisposalController::approve()) — never by editing the asset
        // directly. Editing an already-disposed asset's other fields is fine;
        // what's blocked is *escalating* an active asset to disposed here.
        abort_if(
            $validated['status'] === 'disposed' && $asset->status !== 'disposed',
            422,
            'An asset can only be marked disposed through an approved disposal request.'
        );

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

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_updated',
            'message' => 'Asset updated: '.$asset->name,
            'url' => route('assets.show', $asset->id),
        ]);

        return redirect()->route('assets.index')->with('success', 'Asset updated successfully.');
    }

    public function destroy($id)
    {
        // Assets can never be deleted outright — retirement via an approved
        // AssetDisposal request is the only removal path (Asset Checking &
        // Counting Manual). This keeps the historical record intact for audit.
        abort(403, 'Assets cannot be deleted directly. Submit a disposal request instead.');
    }

    public function publicShow($assetCode)
    {
        $asset = Asset::with(['category', 'location', 'assignments' => function ($q) {
            $q->with('assignee')->latest();
        }])->where('asset_code', $assetCode)->firstOrFail();
        $locations = \App\Models\Location::all();

        return view('assets.public-show', compact('asset', 'locations'));
    }

    public function publicUpdateCondition(Request $request, $assetCode)
    {
        $asset = Asset::where('asset_code', $assetCode)->firstOrFail();

        $validated = $request->validate([
            'condition' => 'required|in:good,fair,broken,lost',
            'remark' => 'nullable|string|max:500',
        ]);

        $asset->update(['condition' => $validated['condition']]);

        \App\Models\AssetVerification::create([
            'asset_id' => $asset->id,
            'verified_by' => 'Public Scan',
            'location_id' => $request->location_id,
            'quantity_verified' => 1,
            'condition' => $validated['condition'],
            'remark' => $validated['remark'] ?? null,
            'verified_at' => now(),
        ]);

        return redirect()->route('asset.public.show', $assetCode)
            ->with('success', 'Condition updated successfully.');
    }

    public function downloadQr($id)
    {
        $asset = Asset::findOrFail($id);
        if (! $asset->qr_code_path || ! Storage::disk('public')->exists($asset->qr_code_path)) {
            AssetCodeService::generateQrCode($asset);
        }
        $extension = pathinfo($asset->qr_code_path, PATHINFO_EXTENSION);

        return Storage::disk('public')->download($asset->qr_code_path, $asset->asset_code.'-qr.'.$extension);
    }

    public function regenerateQr($id)
    {
        $asset = Asset::findOrFail($id);
        if ($asset->qr_code_path) {
            Storage::disk('public')->delete($asset->qr_code_path);
        }
        AssetCodeService::generateQrCode($asset);

        return redirect()->route('assets.index')->with('success', 'QR Code regenerated successfully.');
    }

    public function printQr($id)
    {
        $asset = Asset::findOrFail($id);
        if (! $asset->qr_code_path || ! Storage::disk('public')->exists($asset->qr_code_path)) {
            AssetCodeService::generateQrCode($asset);
        }
        $qrUrl = $asset->qr_code_url;

        return view('assets.print-qr', compact('asset', 'qrUrl'));
    }

    // private function generateQrCode(Asset $asset)
    // {
    //     try {
    //         $filename = 'qrcodes/' . $asset->asset_code . '.png';

    //         $qrSize = \App\Models\Setting::where('key', 'qr_size')->value('value') ?? 300;

    //         $builder = new Builder(
    //             writer: new PngWriter(),
    //             data: route('asset.public.show', $asset->asset_code),
    //             encoding: new Encoding('UTF-8'),
    //             size: (int) $qrSize,
    //             margin: 10,
    //         );

    //         $builder->build()->saveToFile(Storage::disk('public')->path($filename));

    //         $asset->update(['qr_code_path' => $filename]);
    //     } catch (\Exception $e) {
    //         \Log::error('QR Code generation failed for asset ' . $asset->asset_code . ': ' . $e->getMessage());
    //     }
    // }
}
