<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        return response()->json(ActivityLog::with('user')->latest()->paginate(20));
    }

    public function show(ActivityLog $activity_log)
    {
        return response()->json($activity_log->load('user'));
    }

    public function destroy(ActivityLog $activity_log)
    {
        $activity_log->delete();
        return response()->json(['message' => 'Activity log entry deleted.']);
    }
}
