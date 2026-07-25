<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesAndSorts;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use PaginatesAndSorts;

    private const SORTABLE = ['action', 'created_at'];

    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        $this->applySearch($query, $request, ['action', 'description'], [
            'user' => ['name'],
        ]);
        $this->applyExactFilters($query, $request, ['action']);

        return response()->json($this->paginateSorted($query, $request, self::SORTABLE));
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
