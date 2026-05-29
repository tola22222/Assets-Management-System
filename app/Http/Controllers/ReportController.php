<?php
namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'items.asset']);

        // 1. Filter by Date Range / View
        if ($request->filled('view')) {
            $query->when($request->view === 'day', fn($q) => $q->whereDate('created_at', Carbon::today()))
                  ->when($request->view === 'month', fn($q) => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
                  ->when($request->view === 'year', fn($q) => $q->whereYear('created_at', now()->year));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // 2. Calculate Stats (cloning to avoid affecting the main list)
        $stats = [
            'pending_count'   => (clone $query)->where('status', 'pending')->count(),
            'completed_month' => PurchaseOrder::where('status', 'received')
                                    ->whereMonth('updated_at', now()->month)
                                    ->count(),
            'total_procurement' => (clone $query)->sum('total_amount'),
        ];

        $reports = $query->latest()->paginate(15)->withQueryString();

        return view('reports.index', compact('reports', 'stats'));
    }

    public function export(Request $request)
    {
        // Add your Excel export logic here
        return back()->with('info', 'Exporting data...');
    }
}