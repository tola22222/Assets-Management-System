<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\AssetReturn;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return response()->json($this->staffDashboard($user));
        }

        return response()->json($this->adminDashboard());
    }

    /**
     * Assets registered per day/month/year — grouped in PHP (via Carbon)
     * rather than a raw SQL date-format function, since the dev DB is
     * sqlite and production is MySQL and their date functions differ.
     * Empty buckets in range are pre-filled with 0 so the chart has no gaps.
     */
    public function byPeriod(Request $request)
    {
        abort_if($request->user()->isStaff(), 403);

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
            $buckets[$cursor->format($format)] = 0;
        }

        Asset::where('created_at', '>=', $since)
            ->get(['created_at'])
            ->each(function ($asset) use (&$buckets, $format) {
                $key = $asset->created_at->format($format);
                if (array_key_exists($key, $buckets)) {
                    $buckets[$key]++;
                }
            });

        return response()->json([
            'period' => $period,
            'data' => collect($buckets)->map(fn ($count, $label) => ['label' => $label, 'count' => $count])->values(),
        ]);
    }

    private function adminDashboard(): array
    {
        $totalAssets = Asset::count();

        $byCategory = Asset::select('category_id', DB::raw('count(*) as count'))
            ->with('category:id,name,short_name')
            ->groupBy('category_id')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category->name ?? 'Uncategorized',
                'count' => $row->count,
                'percentage' => $totalAssets > 0 ? round($row->count / $totalAssets * 100) : 0,
            ])
            ->sortByDesc('count')
            ->values();

        $byLocation = Asset::select('location_id', DB::raw('count(*) as total'))
            ->whereNotNull('location_id')
            ->with('location:id,name')
            ->groupBy('location_id')
            ->get()
            ->map(fn ($row) => [
                'location' => $row->location->name ?? 'Unknown',
                'total' => (int) $row->total,
            ])
            ->sortByDesc('total')
            ->values();

        // Record-level "needs attention" — real assets missing required fields or
        // flagged by condition. Prioritised: broken/lost first, then missing fields.
        $needsAttention = collect();
        $add = function ($query, string $reason, string $severity) use ($needsAttention) {
            foreach ($query->latest()->take(3)->get() as $a) {
                $needsAttention->push([
                    'name' => $a->name,
                    'code' => $a->asset_code,
                    'reason' => $reason,
                    'severity' => $severity,
                ]);
            }
        };
        $add(Asset::where('condition', 'lost'), 'lost', 'danger');
        $add(Asset::where('condition', 'broken'), 'damaged', 'danger');
        $add(Asset::whereNull('purchase_price')->where('status', 'active'), 'no price', 'warning');
        $add(Asset::whereNull('purchase_date')->where('status', 'active'), 'no date', 'warning');
        $add(Asset::whereNull('serial_number')->where('status', 'active'), 'no serial', 'info');
        $needsAttention = $needsAttention->unique('code')->take(6)->values();

        $pricedCount = Asset::whereNotNull('purchase_price')->count();

        return [
            'total_locations' => Location::count(),
            'total_assets' => $totalAssets,
            'total_categories' => AssetCategory::count(),
            'recorded_value' => (float) Asset::sum('purchase_price'),
            'priced_percentage' => $totalAssets > 0 ? round($pricedCount / $totalAssets * 100) : 0,
            'missing_price_count' => $totalAssets - $pricedCount,
            'assets_in_use' => AssetAssignment::where('status', 'active')->count(),
            'assets_lost' => Asset::where('condition', 'lost')->count(),
            'assets_by_category' => $byCategory,
            'assets_by_location' => $byLocation,
            'needs_attention' => $needsAttention,
            'recent_assets' => Asset::with('category')->latest()->take(5)->get(),
            'recent_activity' => \App\Models\ActivityLog::with('user')->latest()->take(5)->get(),
            'unread_notifications' => Notification::where('is_read', false)->count(),
        ];
    }

    private function staffDashboard($user): array
    {
        $myAssignments = AssetAssignment::where('assigned_to_type', 'staff')
            ->where('assigned_to_id', $user->staff_id)
            ->with('asset')
            ->latest()
            ->get();

        return [
            'my_assignments' => $myAssignments,
            'pending_returns' => AssetReturn::where('returned_by', $user->id)->where('status', 'pending')->count(),
            'upcoming_verifications' => AssetVerification::where('verified_by', $user->id)->whereNull('verified_at')->count(),
            'recent_scans' => Notification::where('user_id', $user->id)->where('type', 'qr_scan')->latest()->take(5)->get(),
        ];
    }
}
