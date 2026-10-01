<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
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
        // One dashboard for every role. Staff get the same layout as HR, with
        // every figure counted over their own location only (Asset::visibleTo),
        // so nothing from another site shows.
        return response()->json($this->dashboard($request->user()));
    }

    /**
     * Assets registered per day/month/year — grouped in PHP (via Carbon)
     * rather than a raw SQL date-format function, since the dev DB is
     * sqlite and production is MySQL and their date functions differ.
     * Empty buckets in range are pre-filled with 0 so the chart has no gaps.
     * Staff see their own location's registrations only.
     */
    public function byPeriod(Request $request)
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
            $buckets[$cursor->format($format)] = 0;
        }

        Asset::visibleTo($request->user())
            ->where('created_at', '>=', $since)
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
     * (disposed) asset is no longer part of what PEPY holds or its value —
     * and only the assets this user may see: all sites for OPM / Finance /
     * the ED, their own location for staff (none until HR sets it).
     */
    private function dashboard(User $user): array
    {
        $assets = fn () => Asset::onRegister()->visibleTo($user);
        $totalAssets = $assets()->count();

        $byCategory = $assets()->select('category_id', DB::raw('count(*) as count'))
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

        $byLocation = $assets()->select('location_id', DB::raw('count(*) as total'))
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
        $add($assets()->where('condition', 'lost'), 'lost', 'danger');
        $add($assets()->where('condition', 'broken'), 'damaged', 'danger');
        $add($assets()->whereNull('purchase_price'), 'no price', 'warning');
        $add($assets()->whereNull('purchase_date'), 'no date', 'warning');
        $add($assets()->whereNull('serial_number'), 'no serial', 'info');
        $needsAttention = $needsAttention->unique('code')->take(6)->values();

        $pricedCount = $assets()->whereNotNull('purchase_price')->count();

        return [
            'total_locations' => $user->isSiteScoped()
                ? count($user->siteLocationIds())
                : Location::count(),
            'total_assets' => $totalAssets,
            'total_categories' => $user->isSiteScoped()
                ? AssetCategory::whereHas('assets', fn ($q) => $q->onRegister()->visibleTo($user))->count()
                : AssetCategory::count(),
            'recorded_value' => (float) $assets()->sum('purchase_price'),
            'priced_percentage' => $totalAssets > 0 ? round($pricedCount / $totalAssets * 100) : 0,
            'missing_price_count' => $totalAssets - $pricedCount,
            'assets_in_use' => AssetAssignment::whereIn('status', AssetAssignment::CURRENT_STATUSES)
                ->whereHas('asset', fn ($q) => $q->visibleTo($user))
                ->count(),
            'assets_lost' => $assets()->where('condition', 'lost')->count(),
            'assets_by_category' => $byCategory,
            'assets_by_location' => $byLocation,
            'needs_attention' => $needsAttention,
            'recent_assets' => Asset::visibleTo($user)->with('category')->latest()->take(5)->get(),
            // The activity log is OPM-only everywhere else; so it is here.
            'recent_activity' => $user->isOperationsHrManager()
                ? \App\Models\ActivityLog::with('user:id,name')->latest()->take(5)->get()
                : [],
            'unread_notifications' => Notification::where('user_id', $user->id)->where('is_read', false)->count(),
        ];
    }
}
