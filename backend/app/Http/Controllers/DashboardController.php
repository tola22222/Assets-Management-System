<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Staff;
use App\Models\AssetMovement;
use App\Models\Notification;
use App\Models\AssetReturn;
use App\Models\AssetTransfer;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isStaff()) {
            return $this->staffDashboard($user);
        }

        return $this->adminDashboard();
    }

    private function adminDashboard()
    {
        $stats = [
            'total_locations' => Location::count(),
            'total_assets' => Asset::count(),
            'total_categories' => AssetCategory::count(),
            'total_staff' => Staff::count(),
            'assets_in_use' => AssetAssignment::where('status', 'active')->count(),
            'assets_available' => Asset::where('status', 'active')
                ->whereDoesntHave('assignments', function ($q) {
                    $q->whereIn('status', ['assigned', 'active']);
                })->count(),
            'assets_lost' => Asset::where('condition', 'lost')->count(),
            'recent_assets' => Asset::with('category')->latest()->take(5)->get(),
            'recent_movements' => AssetMovement::with('asset')->latest()->take(5)->get(),
            'recent_scans' => Notification::where('type', 'qr_scan')->latest()->take(5)->get(),
            'pending_verifications' => AssetVerification::whereNull('verified_at')->count(),
            'pending_returns' => AssetReturn::where('status', 'pending')->count(),
            'pending_transfers' => AssetTransfer::where('status', 'pending')->count(),
            'unread_notifications' => Notification::where('is_read', false)->count(),
            'recent_activity' => ActivityLog::with('user')->latest()->take(5)->get(),
        ];

        return view('dashboard.admin', compact('stats'));
    }

    private function staffDashboard($user)
    {
        $myAssignments = AssetAssignment::where('assigned_to_type', 'staff')
            ->where('assigned_to_id', $user->staff_id)
            ->with('asset')
            ->latest()
            ->get();

        $pendingReturns = AssetReturn::where('returned_by', $user->id)
            ->where('status', 'pending')
            ->count();

        $upcomingVerifications = AssetVerification::where('verified_by', $user->id)
            ->whereNull('verified_at')
            ->count();

        $recentScans = Notification::where('user_id', $user->id)
            ->where('type', 'qr_scan')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.staff', compact(
            'myAssignments',
            'pendingReturns',
            'upcomingVerifications',
            'recentScans'
        ));
    }
}
