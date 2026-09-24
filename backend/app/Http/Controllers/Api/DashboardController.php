<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetScan;
use App\Models\Location;
use App\Models\Notification;
use App\Models\User;
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

        return response()->json($this->adminDashboard($user));
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

    /**
     * Every figure here counts assets still on the register — a written-off
     * (disposed) asset is no longer part of what PEPY holds or its value.
     */
    private function adminDashboard(User $user): array
    {
        $totalAssets = Asset::onRegister()->count();

        $byCategory = Asset::onRegister()->select('category_id', DB::raw('count(*) as count'))
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

        $byLocation = Asset::onRegister()->select('location_id', DB::raw('count(*) as total'))
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
        $add(Asset::onRegister()->where('condition', 'lost'), 'lost', 'danger');
        $add(Asset::onRegister()->where('condition', 'broken'), 'damaged', 'danger');
        $add(Asset::whereNull('purchase_price')->where('status', 'active'), 'no price', 'warning');
        $add(Asset::whereNull('purchase_date')->where('status', 'active'), 'no date', 'warning');
        $add(Asset::whereNull('serial_number')->where('status', 'active'), 'no serial', 'info');
        $needsAttention = $needsAttention->unique('code')->take(6)->values();

        $pricedCount = Asset::onRegister()->whereNotNull('purchase_price')->count();

        return [
            'total_locations' => Location::count(),
            'total_assets' => $totalAssets,
            'total_categories' => AssetCategory::count(),
            'recorded_value' => (float) Asset::onRegister()->sum('purchase_price'),
            'priced_percentage' => $totalAssets > 0 ? round($pricedCount / $totalAssets * 100) : 0,
            'missing_price_count' => $totalAssets - $pricedCount,
            'assets_in_use' => AssetAssignment::whereIn('status', AssetAssignment::CURRENT_STATUSES)->count(),
            'assets_lost' => Asset::onRegister()->where('condition', 'lost')->count(),
            'assets_by_category' => $byCategory,
            'assets_by_location' => $byLocation,
            'needs_attention' => $needsAttention,
            'recent_assets' => Asset::with('category')->latest()->take(5)->get(),
            // The activity log is OPM-only everywhere else; so it is here.
            'recent_activity' => $user->isOperationsHrManager()
                ? \App\Models\ActivityLog::with('user:id,name')->latest()->take(5)->get()
                : [],
            'unread_notifications' => Notification::where('user_id', $user->id)->where('is_read', false)->count(),
        ];
    }

    private function staffDashboard($user): array
    {
        $myAssignments = AssetAssignment::where('assigned_to_type', 'staff')
            ->where('assigned_to_id', $user->staff_id)
            ->whereIn('status', AssetAssignment::CURRENT_STATUSES)
            ->with('asset')
            ->latest()
            ->get();

        return [
            'my_assignments' => $myAssignments,
            // Assets they hold that are due back within a week, or overdue.
            'pending_returns' => $myAssignments
                ->filter(fn (AssetAssignment $a) => $a->due_date !== null && Carbon::parse($a->due_date)->lte(now()->addDays(7)))
                ->count(),
            // Assets at their site not yet verified in the current count
            // period (the manual's counts run from 1 Feb and 1 Aug).
            'upcoming_verifications' => $this->unverifiedAtSite($user),
            // Same {id, message, created_at} shape Dashboard.vue rendered back when
            // scans were notification rows, now read from the scan log itself.
            'recent_scans' => AssetScan::where('user_id', $user->id)->latest()->take(5)->get()
                ->map(fn (AssetScan $scan) => [
                    'id' => $scan->id,
                    'message' => match ($scan->action) {
                        AssetScan::ACTION_VERIFIED => 'Verified: ',
                        AssetScan::ACTION_LOCATION_UPDATED => 'Location updated: ',
                        default => 'QR scanned: ',
                    }.$scan->asset_name.' ('.$scan->asset_code.')',
                    'created_at' => $scan->created_at,
                ]),
        ];
    }

    private function unverifiedAtSite(User $user): int
    {
        $site = $user->siteLocationId();
        if ($site === null) {
            return 0;
        }

        $today = now();
        $periodStart = $today->month >= 8
            ? $today->copy()->setDate($today->year, 8, 1)->startOfDay()
            : ($today->month >= 2
                ? $today->copy()->setDate($today->year, 2, 1)->startOfDay()
                : $today->copy()->setDate($today->year - 1, 8, 1)->startOfDay());

        return Asset::onRegister()
            ->where('location_id', $site)
            ->whereDoesntHave('verifications', fn ($q) => $q->where('verified_at', '>=', $periodStart))
            ->count();
    }
}
