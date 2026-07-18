<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\AssetReturn;
use App\Models\AssetStock;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Notification;
use Illuminate\Http\Request;
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

        $byLocation = AssetStock::select('location_id', DB::raw('sum(quantity) as total'))
            ->with('location:id,name')
            ->groupBy('location_id')
            ->get()
            ->map(fn ($row) => [
                'location' => $row->location->name ?? 'Unknown',
                'total' => (int) $row->total,
            ])
            ->sortByDesc('total')
            ->values();

        $pendingVerifications = AssetVerification::whereNull('verified_at')->count();
        $pendingReturns = AssetReturn::where('status', 'pending')->count();
        $pendingTransfers = AssetTransfer::where('status', 'pending')->count();
        $pendingDisposals = AssetDisposal::pending()->count();

        $needsAttention = [];
        if ($pendingVerifications > 0) {
            $needsAttention[] = ['label' => 'Verifications awaiting completion', 'count' => $pendingVerifications, 'type' => 'verification'];
        }
        if ($pendingReturns > 0) {
            $needsAttention[] = ['label' => 'Returns awaiting approval', 'count' => $pendingReturns, 'type' => 'return'];
        }
        if ($pendingTransfers > 0) {
            $needsAttention[] = ['label' => 'Transfers awaiting approval', 'count' => $pendingTransfers, 'type' => 'transfer'];
        }
        if ($pendingDisposals > 0) {
            $needsAttention[] = ['label' => 'Disposal requests awaiting review', 'count' => $pendingDisposals, 'type' => 'disposal'];
        }

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
