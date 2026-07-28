<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\AssetImportService;
use Illuminate\Http\Request;

class AssetImportController extends Controller
{
    public function store(Request $request, AssetImportService $service)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        try {
            $result = $service->import($request->file('file'), $request->boolean('generate_qr', true));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Import',
            'description' => "Imported asset register: {$result['created']} added, {$result['updated']} updated"
                . ($result['skipped'] ? ", {$result['skipped']} skipped" : '')
                . (count($result['errors']) ? ', ' . count($result['errors']) . ' error(s)' : ''),
        ]);

        return response()->json($result);
    }

    public function template()
    {
        $columns = ['name', 'category', 'location', 'description', 'model', 'brand', 'serial_number', 'purchase_date', 'purchase_price', 'condition', 'status'];

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $columns);
        fputcsv($handle, ['Dell Laptop', 'Computer Equipment', 'PEPY Office', 'Core i5, 8GB RAM', 'Latitude 5420', 'Dell', 'SN123456', '2026-01-15', '650.00', 'good', 'active']);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="asset_import_template.csv"',
        ]);
    }
}
