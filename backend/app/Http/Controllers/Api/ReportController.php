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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Sum of purchase_price per category — same shape as DashboardController's count-based
     *  assets_by_category, but value-weighted, for the Analytics "value by category" donut. */
    public function valueByCategory()
    {
        $totalValue = (float) Asset::sum('purchase_price');

        $rows = Asset::select('category_id', DB::raw('sum(purchase_price) as value'), DB::raw('count(*) as count'))
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category->name ?? 'Uncategorized',
                'count' => (float) $row->value,
                'percentage' => $totalValue > 0 ? round((float) $row->value / $totalValue * 100) : 0,
            ])
            ->sortByDesc('count')
            ->values();

        return response()->json(['total_value' => $totalValue, 'segments' => $rows]);
    }

    /**
     * Value + count of assets acquired per day/month/year, gap-filled — same
     * bucketing approach as DashboardController::byPeriod() (grouped in PHP via
     * Carbon since dev is sqlite and production is MySQL and their date
     * functions differ), extended to also sum purchase_price per bucket.
     */
    public function acquisitionsOverTime(Request $request)
    {
        $period = in_array($request->query('period'), ['day', 'month', 'year'], true)
            ? $request->query('period')
            : 'month';

        [$format, $since, $step] = match ($period) {
            'day' => ['Y-m-d', now()->subDays(29)->startOfDay(), fn (Carbon $d) => $d->addDay()],
            'year' => ['Y', now()->subYears(4)->startOfYear(), fn (Carbon $d) => $d->addYear()],
            default => ['Y-m', now()->subMonths(11)->startOfMonth(), fn (Carbon $d) => $d->addMonth()],
        };

        $buckets = [];
        for ($cursor = $since->copy(); $cursor->lte(now()); $cursor = $step($cursor)) {
            $buckets[$cursor->format($format)] = ['count' => 0, 'value' => 0.0];
        }

        Asset::where('created_at', '>=', $since)
            ->get(['created_at', 'purchase_price'])
            ->each(function ($asset) use (&$buckets, $format) {
                $key = $asset->created_at->format($format);
                if (array_key_exists($key, $buckets)) {
                    $buckets[$key]['count']++;
                    $buckets[$key]['value'] += (float) ($asset->purchase_price ?? 0);
                }
            });

        return response()->json([
            'period' => $period,
            'data' => collect($buckets)->map(fn ($b, $label) => ['label' => $label, 'count' => $b['count'], 'value' => $b['value']])->values(),
        ]);
    }

    /** Condition breakdown per location, for the Analytics stacked-bar. */
    public function conditionByLocation()
    {
        $rows = Asset::select('location_id', 'condition', DB::raw('count(*) as count'))
            ->whereNotNull('location_id')
            ->with('location:id,name')
            ->groupBy('location_id', 'condition')
            ->get()
            ->groupBy(fn ($row) => $row->location->name ?? 'Unknown')
            ->map(function ($rows, $location) {
                $byCondition = $rows->pluck('count', 'condition');

                return [
                    'location' => $location,
                    'good' => (int) ($byCondition['good'] ?? 0),
                    'fair' => (int) ($byCondition['fair'] ?? 0),
                    'broken' => (int) ($byCondition['broken'] ?? 0),
                    'lost' => (int) ($byCondition['lost'] ?? 0),
                ];
            })
            ->values();

        return response()->json($rows);
    }

    /** Top assets by recorded value, for the Analytics "top value assets" mini table. */
    public function topValueAssets()
    {
        $rows = Asset::whereNotNull('purchase_price')
            ->with(['category', 'location'])
            ->orderByDesc('purchase_price')
            ->take(10)
            ->get(['id', 'name', 'asset_code', 'category_id', 'location_id', 'purchase_price']);

        return response()->json($rows);
    }

    /**
     * Grouped "count by model" view: each Asset row stays an individually
     * tracked unit with its own tag, but this rolls same-name units up into
     * one line per model/category with a Low/Medium/High stock-level badge —
     * a read-only summary, not a change to how the data is stored.
     */
    public function byModel()
    {
        $rows = Asset::select('name', 'category_id', DB::raw('count(*) as total'))
            ->where('status', '!=', 'disposed')
            ->groupBy('name', 'category_id')
            ->with('category:id,name,short_name')
            ->get()
            ->map(function ($row) {
                $row->stock_level = Asset::stockLevelFor($row->total);

                return $row;
            })
            ->sortByDesc('total')
            ->values();

        return response()->json($rows);
    }

    public function inventory(Request $request)
    {
        $query = Asset::with(['category', 'stocks.location']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        return response()->json($query->latest()->get());
    }

    public function assignments(Request $request)
    {
        $query = AssetAssignment::with(['asset', 'location']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_to_type')) {
            $query->where('assigned_to_type', $request->assigned_to_type);
        }

        return response()->json($query->latest()->get());
    }

    public function transfers(Request $request)
    {
        $query = AssetTransfer::with(['asset', 'fromLocation', 'toLocation', 'requester']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function verifications(Request $request)
    {
        $query = AssetVerification::with(['asset', 'location', 'verifiedBy']);

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        return response()->json($query->latest()->get());
    }

    public function returns(Request $request)
    {
        $query = AssetReturn::with(['asset', 'assignment', 'returnedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

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
        return response()->json(Location::withCount('assets')->get());
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
                if (is_null($asset->purchase_price)) {
                    $missing[] = 'Purchase Price';
                }
                if (is_null($asset->purchase_date)) {
                    $missing[] = 'Purchase Date';
                }
                if (blank($asset->serial_number)) {
                    $missing[] = 'Serial Number';
                }
                $asset->missing_fields = implode(', ', $missing);

                return $asset;
            });

        return response()->json($assets);
    }
}
