<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    public function index()
    {
        $assets = Asset::with('category')->latest()->get();
        $categories = AssetCategory::orderBy('name')->get();
        return view('assets.index', compact('assets', 'categories'));
    }

    public function create()
    {
        $categories = AssetCategory::orderBy('name')->get();
        return view('assets.create', compact('categories'));
    }

    private function generateQrCode(Asset $asset): void
    {
        $url = route('asset.public.show', $asset->asset_code);

        $qrImage = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->margin(1)
            ->generate($url);

        $path = 'qrcodes/' . $asset->asset_code . '.png';

        Storage::disk('public')->put($path, $qrImage);

        $asset->update(['qr_code_path' => $path]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
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

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('assets', 'public');
        }

        // Generate asset code: SHORTNAME-YYYY-XXXXXX
        $category = \App\Models\AssetCategory::find($validated['category_id']);
        $prefix = $category ? strtoupper($category->short_name) : 'AST';
        $year = date('Y');
        $count = Asset::whereYear('created_at', $year)->count() + 1;
        $validated['asset_code'] = $prefix . '-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        $asset = Asset::create($validated);

        $this->generateQrCode($asset);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Registered asset: ' . $asset->name . ' (' . $asset->asset_code . ')',
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_registered',
            'message' => 'Asset registered: ' . $asset->name . ' (' . $asset->asset_code . ')',
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
        return view('assets.edit', compact('asset', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
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

        if ($request->hasFile('image')) {
            if ($asset->image_path) {
                Storage::disk('public')->delete($asset->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('assets', 'public');
        }

        $asset->update($validated);

        if (!$asset->qr_code_path) {
            $this->generateQrCode($asset);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated asset: ' . $asset->name,
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'asset_updated',
            'message' => 'Asset updated: ' . $asset->name,
            'url' => route('assets.show', $asset->id),
        ]);

        return redirect()->route('assets.index')->with('success', 'Asset updated successfully.');
    }

    public function destroy($id)
    {
        $asset = Asset::findOrFail($id);
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

        return redirect()->route('assets.index');
    }

    public function publicShow($assetCode)
    {
        $asset = Asset::with('category')->where('asset_code', $assetCode)->firstOrFail();
        return view('assets.public-show', compact('asset'));
    }

    public function downloadQr($id)
    {
        $asset = Asset::findOrFail($id);
        if (!$asset->qr_code_path || !Storage::disk('public')->exists($asset->qr_code_path)) {
            $this->generateQrCode($asset);
        }
        $extension = pathinfo($asset->qr_code_path, PATHINFO_EXTENSION);
        return Storage::disk('public')->download($asset->qr_code_path, $asset->asset_code . '-qr.' . $extension);
    }

    public function regenerateQr($id)
    {
        $asset = Asset::findOrFail($id);
        if ($asset->qr_code_path) {
            Storage::disk('public')->delete($asset->qr_code_path);
        }
        $this->generateQrCode($asset);
        return redirect()->route('assets.index')->with('success', 'QR Code regenerated successfully.');
    }

    public function printQr($id)
    {
        $asset = Asset::findOrFail($id);
        if (!$asset->qr_code_path || !Storage::disk('public')->exists($asset->qr_code_path)) {
            $this->generateQrCode($asset);
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
