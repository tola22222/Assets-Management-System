<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetReturn;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Notification;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function inventory(Request $request)
    {
        $query = Asset::with(['category', 'stocks.location']);

        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('condition')) $query->where('condition', $request->condition);

        return response()->json($query->latest()->get());
    }

    public function assignments(Request $request)
    {
        $query = AssetAssignment::with(['asset', 'location']);

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('assigned_to_type')) $query->where('assigned_to_type', $request->assigned_to_type);

        return response()->json($query->latest()->get());
    }

    public function transfers(Request $request)
    {
        $query = AssetTransfer::with(['asset', 'fromLocation', 'toLocation', 'requester']);

        if ($request->filled('status')) $query->where('status', $request->status);

        return response()->json($query->latest()->get());
    }

    public function verifications(Request $request)
    {
        $query = AssetVerification::with(['asset', 'location', 'verifiedBy']);

        if ($request->filled('condition')) $query->where('condition', $request->condition);

        return response()->json($query->latest()->get());
    }

    public function returns(Request $request)
    {
        $query = AssetReturn::with(['asset', 'assignment', 'returnedBy']);

        if ($request->filled('status')) $query->where('status', $request->status);

        return response()->json($query->latest()->get());
    }

    public function disposed()
    {
        return response()->json(Asset::where('status', 'disposed')->with('category')->latest()->get());
    }

    public function lost()
    {
        return response()->json(Asset::where('condition', 'lost')->with('category')->latest()->get());
    }

    public function locations()
    {
        return response()->json(Location::withCount('assetStocks')->get());
    }

    public function qrScans()
    {
        return response()->json(Notification::where('type', 'qr_scan')->with('user')->latest()->get());
    }

    public function dataCompleteness()
    {
        $assets = Asset::with('category')
            ->where('status', '!=', 'disposed')
            ->where(function ($q) {
                $q->whereNull('purchase_price')
                    ->orWhereNull('purchase_date')
                    ->orWhereNull('serial_number');
            })
            ->latest()
            ->get()
            ->map(function ($asset) {
                $missing = [];
                if (is_null($asset->purchase_price)) $missing[] = 'Purchase Price';
                if (is_null($asset->purchase_date)) $missing[] = 'Purchase Date';
                if (blank($asset->serial_number)) $missing[] = 'Serial Number';
                $asset->missing_fields = implode(', ', $missing);
                return $asset;
            });

        return response()->json($assets);
    }
}
