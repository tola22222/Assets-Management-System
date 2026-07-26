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
    private const SORTABLE = [
        'name', 'asset_code', 'brand', 'model', 'serial_number',
        'purchase_date', 'purchase_price', 'condition', 'status', 'created_at',
    ];

    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        return response()->json($query->paginate($perPage)->withQueryString());
    }

    /** Uncapped list of the current search/filter results, for CSV export. */
    public function export(Request $request)
    {
        $assets = $this->filteredQuery($request)->limit(5000)->get();

        return response()->json(['data' => $assets]);
    }

    private function filteredQuery(Request $request)
    {
        $query = Asset::with(['category', 'location', 'currentAssignment'])
            ->withMax('verifications', 'verified_at');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('location', fn ($l) => $l->where('name', 'like', "%{$search}%"));
            });
        }

        foreach (['category_id', 'location_id', 'status', 'condition'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = $request->query('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'created_at';
        }

        return $query->orderBy($sortBy, $sortDir);
    }

    public function show(Asset $asset)
    {
        return response()->json($asset->load([
            'category',
            'location',
            'currentAssignment',
            'assignments' => fn ($q) => $q->latest(),
            'verifications' => fn ($q) => $q->latest(),
            'transfers' => fn ($q) => $q->latest()->with(['fromLocation', 'toLocation']),
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

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:assets,id',
        ]);

        $assets = Asset::whereIn('id', $validated['ids'])->get();

        foreach ($assets as $asset) {
            if ($asset->qr_code_path) {
                Storage::disk('public')->delete($asset->qr_code_path);
            }
            if ($asset->image_path) {
                Storage::disk('public')->delete($asset->image_path);
            }
            $asset->delete();
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Bulk deleted '.$assets->count().' assets: '.$assets->pluck('asset_code')->join(', '),
        ]);

        return response()->json(['message' => $assets->count().' assets deleted.', 'deleted' => $assets->pluck('id')]);
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
            'warranty_expiry' => 'nullable|date',
            'warranty_provider' => 'nullable|string|max:255',
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
