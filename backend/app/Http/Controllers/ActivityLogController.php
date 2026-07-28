<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the activity logs.
     */
    public function index()
    {
        // 1. We use with('user') to load user names in one query (Eager Loading)
        // 2. We use latest() to show the most recent actions first
        // 3. We use paginate() so the page doesn't crash if you have 10,000 logs
        $activityLogs = ActivityLog::with('user')->latest()->paginate(20);
        return view('activity_logs.index', compact('activityLogs'));
    }

    /**
     * Show the form for creating a new resource.
     * (Not usually used for system logs)
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     * (Logs are usually created via Model events, not this method)
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ActivityLog $activityLog)
    {
        return view('activity_logs.show', compact('activityLog'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ActivityLog $activityLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ActivityLog $activityLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityLog $activityLog)
    {
        // Allow deletion of a specific log entry
        $activityLog->delete();

        return redirect()->route('activity-logs.index')
            ->with('success', 'Activity log entry deleted successfully.');
    }
}