<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

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
        $removed = $activity_log->action.': '.$activity_log->description.' ('.$activity_log->created_at?->toDateTimeString().')';
        $activity_log->delete();

        // The audit trail records its own pruning, so a deleted entry never
        // disappears without a trace.
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted activity log entry — '.$removed,
        ]);

        return response()->json(['message' => 'Activity log entry deleted.']);
    }
}
