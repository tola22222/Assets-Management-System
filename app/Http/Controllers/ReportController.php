<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\AssetReturn;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('reports.index', compact('user'));
    }

    public function inventory(Request $request)
    {
        $query = Asset::with(['category', 'stocks.location']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        $assets = $query->latest()->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($assets, 'inventory');
        }

        return view('reports.inventory', compact('assets'));
    }

    public function assignments(Request $request)
    {
        $query = AssetAssignment::with(['asset', 'assignee', 'location']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_to_type')) {
            $query->where('assigned_to_type', $request->assigned_to_type);
        }

        $assignments = $query->latest()->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($assignments, 'assignments');
        }

        return view('reports.assignments', compact('assignments'));
    }

    public function transfers(Request $request)
    {
        $query = AssetTransfer::with(['asset', 'fromLocation', 'toLocation', 'requester']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transfers = $query->latest()->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($transfers, 'transfers');
        }

        return view('reports.transfers', compact('transfers'));
    }

    public function verifications(Request $request)
    {
        $query = AssetVerification::with(['asset', 'location', 'verifiedBy']);

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        $verifications = $query->latest()->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($verifications, 'verifications');
        }

        return view('reports.verifications', compact('verifications'));
    }

    public function returns(Request $request)
    {
        $query = AssetReturn::with(['asset', 'assignment', 'returnedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $returns = $query->latest()->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($returns, 'returns');
        }

        return view('reports.returns', compact('returns'));
    }

    public function disposed(Request $request)
    {
        $assets = Asset::where('status', 'disposed')
            ->with('category')
            ->latest()
            ->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($assets, 'disposed');
        }

        return view('reports.disposed', compact('assets'));
    }

    public function lost(Request $request)
    {
        $assets = Asset::where('condition', 'lost')
            ->with('category')
            ->latest()
            ->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($assets, 'lost');
        }

        return view('reports.lost', compact('assets'));
    }

    public function locations(Request $request)
    {
        $locations = Location::withCount('assetStocks')->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($locations, 'locations');
        }

        return view('reports.locations', compact('locations'));
    }

    public function qrScans(Request $request)
    {
        $scans = Notification::where('type', 'qr_scan')
            ->with('user')
            ->latest()
            ->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($scans, 'qr-scans');
        }

        return view('reports.qr-scans', compact('scans'));
    }

    public function dataCompleteness(Request $request)
    {
        $assets = Asset::with('category')
            ->where('status', '!=', 'disposed')
            ->where(function ($q) {
                $q->whereNull('purchase_price')
                    ->orWhereNull('purchase_date')
                    ->orWhereNull('serial_number');
            })
            ->latest()
            ->get()
            ->map(function ($asset) {
                $missing = [];
                if (is_null($asset->purchase_price)) $missing[] = 'Purchase Price';
                if (is_null($asset->purchase_date)) $missing[] = 'Purchase Date';
                if (blank($asset->serial_number)) $missing[] = 'Serial Number';
                $asset->missing_fields = implode(', ', $missing);
                return $asset;
            });

        if ($request->export === 'csv') {
            return $this->exportCsv($assets, 'data-completeness');
        }

        return view('reports.data-completeness', compact('assets'));
    }

    private function exportCsv($data, $type)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $type . '-report-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($data, $type) {
            $file = fopen('php://output', 'w');

            switch ($type) {
                case 'inventory':
                    fputcsv($file, ['Asset Code', 'Name', 'Category', 'Brand', 'Model', 'Serial No', 'Condition', 'Status', 'Purchase Date', 'Purchase Price']);
                    foreach ($data as $asset) {
                        fputcsv($file, [$asset->asset_code, $asset->name, $asset->category->name ?? '', $asset->brand, $asset->model, $asset->serial_number, $asset->condition, $asset->status, $asset->purchase_date, $asset->purchase_price]);
                    }
                    break;
                case 'assignments':
                    fputcsv($file, ['Asset', 'Assigned To', 'Type', 'Location', 'Date', 'Due Date', 'Status']);
                    foreach ($data as $a) {
                        fputcsv($file, [$a->asset->name ?? '', $a->recipient_name, $a->assigned_to_type, $a->location->name ?? '', $a->assigned_date, $a->due_date, $a->status]);
                    }
                    break;
                case 'transfers':
                    fputcsv($file, ['Asset', 'From', 'To', 'Date', 'Status', 'Requester']);
                    foreach ($data as $t) {
                        fputcsv($file, [$t->asset->name ?? '', $t->fromLocation->name ?? '', $t->toLocation->name ?? '', $t->transfer_date, $t->status, $t->requester->name ?? '']);
                    }
                    break;
                case 'data-completeness':
                    fputcsv($file, ['Asset Code', 'Name', 'Category', 'Missing Fields']);
                    foreach ($data as $asset) {
                        fputcsv($file, [$asset->asset_code, $asset->name, $asset->category->name ?? '', $asset->missing_fields]);
                    }
                    break;
                default:
                    foreach ($data as $row) {
                        fputcsv($file, (array) $row);
                    }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
