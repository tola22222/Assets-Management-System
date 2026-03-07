<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    // Use session-based auth
    // public function __construct()
    // {
    //     $this->middleware('auth'); // <- session-based login
    // }

    public function index()
    {
        $user = Auth::user();

        // Dashboard metrics
        $dashboardData = [
            'total_users' => User::count(),
            'total_logs' => ActivityLog::count(),
            'recent_logs' => ActivityLog::latest()->take(5)->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'created_at' => $log->created_at->toDateTimeString(),
                ];
            }),
        ];

        return response()->json([
            'message' => 'Dashboard data retrieved successfully',
            'user' => $user,
            'dashboard' => $dashboardData,
        ], 200);
    }
}