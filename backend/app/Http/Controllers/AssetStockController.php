<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Services\AssetCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Receive Assets": every physical unit tracked by this system (vehicles,
 * furniture, computers, equipment) gets its own Asset record, asset_code,
 * and QR code — quantity is never tracked by incrementing a counter on a
 * shared row. Receiving N units of the same item creates N individual
 * assets, all linked by a shared reference_no on their stock_in
 * AssetMovement rows so the receipt stays auditable as one transaction.
 */
class AssetStockController extends Controller
{
    public function index()
    {
        $receipts = AssetMovement::with(['asset.category', 'toLocation'])
            ->where('movement_type', 'stock_in')
            ->latest()
            ->get();

        $categories = AssetCategory::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        return view('asset-stocks.index', compact('receipts', 'categories', 'locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1|max:200',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'condition' => 'nullable|string|in:good,fair,broken,lost',
            'status' => 'nullable|string|in:active,disposed',
        ]);

        $referenceNo = 'RCPT-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));

        DB::transaction(function () use ($validated, $referenceNo) {
            for ($i = 0; $i < $validated['quantity']; $i++) {
                $asset = Asset::create([
                    'name' => $validated['name'],
                    'category_id' => $validated['category_id'],
                    'location_id' => $validated['location_id'],
                    'brand' => $validated['brand'] ?? null,
                    'model' => $validated['model'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'purchase_price' => $validated['purchase_price'] ?? null,
                    'condition' => $validated['condition'] ?? 'good',
                    'status' => $validated['status'] ?? 'active',
                    'asset_code' => AssetCodeService::nextCode($validated['location_id'], $validated['category_id']),
                ]);
                AssetCodeService::generateQrCode($asset);

                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'to_location_id' => $validated['location_id'],
                    'movement_type' => 'stock_in',
                    'quantity' => 1,
                    'reference_no' => $referenceNo,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Receive',
            'description' => 'Received '.$validated['quantity']." unit(s) of {$validated['name']} ({$referenceNo})",
        ]);

        return redirect()->back()->with('success', "Received {$validated['quantity']} unit(s) — reference {$referenceNo}.");
    }

    /** Route param is $asset_stock (derived from the asset-stocks URI segment) even though it now binds an AssetMovement row. */
    public function destroy(AssetMovement $asset_stock)
    {
        $asset_stock->delete();

        return redirect()->back()->with('success', 'Receipt record removed. The asset itself was not deleted.');
    }
}
