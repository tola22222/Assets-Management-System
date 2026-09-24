<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\AssetImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AssetImportController extends Controller
{
    public function store(Request $request, AssetImportService $service)
    {
        // Photos are intentionally NOT validated here — not even for size: a
        // single bad/oversized/misnamed photo in a bulk batch would otherwise
        // 422 the entire request before the spreadsheet is even read. Instead
        // each photo is checked individually in the import service, so one bad
        // file is skipped (and reported in images_rejected) without blocking
        // everything else. The spreadsheet itself stays strictly validated.
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'images' => 'nullable|array',
        ]);

        try {
            // The service runs the whole import in one transaction: if it
            // throws, nothing from this file was saved.
            $result = $service->import(
                $request->file('file'),
                $request->boolean('generate_qr', true),
                Arr::wrap($request->file('images'))
            );
        } catch (ValidationException $e) {
            // Problems with the file itself (unreadable, empty, no header
            // row) — written for the user, so they go back as-is.
            throw $e;
        } catch (\Throwable $e) {
            // Anything else is unexpected and its message may carry SQL,
            // bindings or server paths: log it, show a generic message.
            Log::error('Asset register import failed and was rolled back', [
                'user_id' => $request->user()?->id,
                'file' => $request->file('file')?->getClientOriginalName(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'The import could not be completed, so nothing from this file was saved. Check the file and try again; if it keeps failing, contact your system administrator.',
            ], 422);
        }

        ActivityLog::createAndNotify([
            'user_id' => $request->user()->id,
            'action' => 'Import',
            'description' => "Imported asset register: {$result['created']} added, {$result['updated']} updated"
                .($result['unchanged'] ? ", {$result['unchanged']} unchanged" : '')
                .($result['skipped'] ? ", {$result['skipped']} skipped" : '')
                .($result['images_attached'] ? ", {$result['images_attached']} photo(s) attached" : '')
                .(count($result['errors']) ? ', '.count($result['errors']).' error(s)' : '')
                .(count($result['warnings']) ? ', '.count($result['warnings']).' warning(s)' : ''),
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
