<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetVerification;
use App\Models\PurchaseOrder;
use App\Models\AssetStock;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Check if export is requested
        if ($request->has('export')) {
            return $this->export($request);
        }

        $activeTab = $request->get('tab', 'procurement');

        // ── 1. PROCUREMENT ──────────────────────────────────────────────
        $procurementQuery = PurchaseOrder::with(['supplier', 'items.asset']);

        if ($request->filled('view')) {
            $procurementQuery
                ->when($request->view === 'day',   fn($q) => $q->whereDate('created_at', Carbon::today()))
                ->when($request->view === 'month', fn($q) => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
                ->when($request->view === 'year',  fn($q) => $q->whereYear('created_at', now()->year));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $procurementQuery->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $procurementStats = [
            'pending_count'      => (clone $procurementQuery)->where('status', 'pending')->count(),
            'completed_month'    => PurchaseOrder::where('status', 'received')->whereMonth('updated_at', now()->month)->count(),
            'total_procurement'  => (clone $procurementQuery)->sum('total_amount'),
        ];
        $procurementOrders = $procurementQuery->latest()->paginate(15, ['*'], 'procurement_page')->withQueryString();

        // ── 2. INVENTORY CONTROL ────────────────────────────────────────
        $assets = Asset::with(['category', 'stocks.location'])->latest()->paginate(15, ['*'], 'inventory_page')->withQueryString();

        $inventoryStats = [
            'total_assets'     => Asset::count(),
            'total_stock'      => AssetStock::sum('quantity'),
            'total_categories' => \App\Models\AssetCategory::count(),
            'low_stock'        => AssetStock::where('quantity', '<=', 2)->count(),
        ];

        // ── 3. OPERATIONS (CUSTODY) ─────────────────────────────────────
        $assignmentQuery = AssetAssignment::with(['asset']);

        if ($request->filled('assignment_status')) {
            if ($request->assignment_status === 'overdue') {
                $assignmentQuery->where('status', 'active')
                    ->where('assigned_date', '<', now()->subDays(30));
            } else {
                $assignmentQuery->where('status', $request->assignment_status);
            }
        }

        $assignments = $assignmentQuery->latest()->paginate(15, ['*'], 'ops_page')->withQueryString();

        $operationsStats = [
            'active_assignments'   => AssetAssignment::where('status', 'active')->count(),
            'returned_this_month'  => AssetAssignment::where('status', 'returned')->whereMonth('updated_at', now()->month)->count(),
            'overdue'              => AssetAssignment::where('status', 'active')
                ->where('assigned_date', '<', now()->subDays(30))
                ->count(),
        ];

        // ── 4. QUALITY ASSURANCE ────────────────────────────────────────
        $verificationQuery = AssetVerification::with(['asset', 'location']);

        if ($request->filled('verification_status')) {
            $verificationQuery->where('condition', $request->verification_status);
        }

        $verifications = $verificationQuery->latest()->paginate(15, ['*'], 'qa_page')->withQueryString();

        $qaStats = [
            'total_verifications' => AssetVerification::count(),
            'this_month'          => AssetVerification::whereMonth('verified_at', now()->month)->count(),
            'issues_found'        => AssetVerification::whereIn('condition', ['damaged', 'missing'])->count(),
            'good_condition'      => AssetVerification::where('condition', 'good')->count(),
        ];

        return view('reports.index', compact(
            'activeTab',
            'procurementOrders', 'procurementStats',
            'assets',            'inventoryStats',
            'assignments',       'operationsStats',
            'verifications',     'qaStats'
        ));
    }

    /**
     * Export data to CSV based on active tab
     */
    public function export(Request $request)
    {
        $activeTab = $request->get('tab', 'procurement');
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        switch ($activeTab) {
            case 'procurement':
                return $this->exportProcurement($request, $timestamp);
            case 'inventory':
                return $this->exportInventory($timestamp);
            case 'operations':
                return $this->exportOperations($request, $timestamp);
            case 'qa':
                return $this->exportQA($request, $timestamp);
            default:
                return redirect()->route('reports.index')->with('error', 'Invalid export type');
        }
    }

    /**
     * Export Procurement data to CSV
     */
    private function exportProcurement(Request $request, $timestamp)
    {
        $query = PurchaseOrder::with(['supplier', 'items.asset']);
        
        if ($request->filled('view')) {
            $query
                ->when($request->view === 'day',   fn($q) => $q->whereDate('created_at', Carbon::today()))
                ->when($request->view === 'month', fn($q) => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
                ->when($request->view === 'year',  fn($q) => $q->whereYear('created_at', now()->year));
        }
        
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }
        
        $orders = $query->latest()->get();
        
        $filename = "procurement_report_{$timestamp}.csv";
        
        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'PO Number',
                'Supplier',
                'Items Count',
                'Total Amount (USD)',
                'Status',
                'Created Date'
            ]);
            
            // Data rows
            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->po_number ?? 'PO-' . $order->id,
                    $order->supplier->name ?? '—',
                    $order->items->count(),
                    number_format($order->total_amount, 2),
                    ucfirst($order->status),
                    $order->created_at->format('Y-m-d H:i:s')
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export Inventory data to CSV
     */
    private function exportInventory($timestamp)
    {
        $assets = Asset::with(['category', 'stocks.location'])->latest()->get();
        
        $filename = "inventory_report_{$timestamp}.csv";
        
        $callback = function() use ($assets) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Asset Code',
                'Name',
                'Brand',
                'Model',
                'Category',
                'Location',
                'Quantity at Location',
                'Total Units',
                'Status'
            ]);
            
            // Data rows
            foreach ($assets as $asset) {
                if ($asset->stocks->count() > 0) {
                    foreach ($asset->stocks as $stock) {
                        fputcsv($file, [
                            $asset->asset_code,
                            $asset->name,
                            $asset->brand ?? '—',
                            $asset->model ?? '—',
                            $asset->category->name ?? 'Uncategorized',
                            $stock->location->name ?? 'Unknown',
                            $stock->quantity,
                            $asset->stocks->sum('quantity'),
                            ucfirst($asset->status)
                        ]);
                    }
                } else {
                    // Assets with no stock
                    fputcsv($file, [
                        $asset->asset_code,
                        $asset->name,
                        $asset->brand ?? '—',
                        $asset->model ?? '—',
                        $asset->category->name ?? 'Uncategorized',
                        'No Location',
                        0,
                        $asset->stocks->sum('quantity'),
                        ucfirst($asset->status)
                    ]);
                }
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export Operations data to CSV
     */
    private function exportOperations(Request $request, $timestamp)
    {
        $query = AssetAssignment::with(['asset']);
        
        if ($request->filled('assignment_status')) {
            if ($request->assignment_status === 'overdue') {
                $query->where('status', 'active')
                    ->where('assigned_date', '<', now()->subDays(30));
            } else {
                $query->where('status', $request->assignment_status);
            }
        }
        
        $assignments = $query->latest()->get();
        
        $filename = "operations_report_{$timestamp}.csv";
        
        $callback = function() use ($assignments) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Asset Name',
                'Asset Code',
                'Assigned To',
                'Assignment Type',
                'Quantity',
                'Assigned Date',
                'Status'
            ]);
            
            // Data rows
            foreach ($assignments as $assignment) {
                fputcsv($file, [
                    $assignment->asset->name ?? '—',
                    $assignment->asset->asset_code ?? '—',
                    $assignment->assignee_name ?? $assignment->recipient_name ?? '—',
                    ucfirst($assignment->assigned_to_type ?? 'N/A'),
                    $assignment->quantity ?? 1,
                    optional($assignment->assigned_date)->format('Y-m-d'),
                    ucfirst($assignment->status)
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export Quality Assurance data to CSV
     */
    private function exportQA(Request $request, $timestamp)
    {
        $query = AssetVerification::with(['asset', 'location']);
        
        if ($request->filled('verification_status')) {
            $query->where('condition', $request->verification_status);
        }
        
        $verifications = $query->latest()->get();
        
        $filename = "qa_report_{$timestamp}.csv";
        
        $callback = function() use ($verifications) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Asset Name',
                'Asset Code',
                'Location',
                'Verified By (User ID)',
                'Quantity Verified',
                'Condition',
                'Remark',
                'Verified At'
            ]);
            
            // Data rows
            foreach ($verifications as $verification) {
                $user = \App\Models\User::find($verification->verified_by);
                
                fputcsv($file, [
                    $verification->asset->name ?? '—',
                    $verification->asset->asset_code ?? '—',
                    $verification->location->name ?? '—',
                    $user ? $user->name : ($verification->verified_by ? 'User #'.$verification->verified_by : '—'),
                    $verification->quantity_verified ?? '—',
                    ucfirst($verification->condition ?? 'N/A'),
                    $verification->remark ?? '—',
                    optional($verification->verified_at)->format('Y-m-d H:i:s')
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}