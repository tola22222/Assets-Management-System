<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ActivityLog;
use App\Services\AssetCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetImportController extends Controller
{
    private const REQUIRED_COLUMNS = ['name', 'category'];
    private const OPTIONAL_COLUMNS = [
        'description', 'model', 'brand', 'serial_number',
        'purchase_date', 'purchase_price', 'condition', 'status',
    ];

    public function index()
    {
        return view('assets.import');
    }

    public function template()
    {
        $columns = array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $columns);
        fputcsv($handle, [
            'Dell Laptop', 'IT Equipment', 'Core i5, 8GB RAM', 'Latitude 5420', 'Dell',
            'SN123456', '2026-01-15', '650.00', 'good', 'active',
        ]);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="asset_import_template.csv"',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            return back()->with('error', 'The uploaded file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if (!empty($missing)) {
            fclose($handle);
            return back()->with('error', 'Missing required column(s): ' . implode(', ', $missing));
        }

        $categories = AssetCategory::all()->keyBy(fn ($c) => strtolower($c->name));

        $created = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), null));

            $name = trim((string) ($data['name'] ?? ''));
            $categoryName = trim((string) ($data['category'] ?? ''));

            if ($name === '' || $categoryName === '') {
                $errors[] = "Row {$rowNumber}: name and category are required.";
                continue;
            }

            $category = $categories->get(strtolower($categoryName));
            if (!$category) {
                $errors[] = "Row {$rowNumber}: category \"{$categoryName}\" not found.";
                continue;
            }

            $condition = strtolower(trim((string) ($data['condition'] ?? 'good')));
            if (!in_array($condition, ['good', 'fair', 'broken', 'lost'])) {
                $errors[] = "Row {$rowNumber}: invalid condition \"{$data['condition']}\", must be good/fair/broken/lost.";
                continue;
            }

            $status = strtolower(trim((string) ($data['status'] ?? 'active')));
            if (!in_array($status, ['active', 'disposed'])) {
                $errors[] = "Row {$rowNumber}: invalid status \"{$data['status']}\", must be active/disposed.";
                continue;
            }

            $purchaseDate = trim((string) ($data['purchase_date'] ?? ''));
            if ($purchaseDate !== '' && !strtotime($purchaseDate)) {
                $errors[] = "Row {$rowNumber}: invalid purchase_date \"{$purchaseDate}\".";
                continue;
            }

            $asset = Asset::create([
                'asset_code' => AssetCodeService::nextCode($category),
                'name' => $name,
                'category_id' => $category->id,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'model' => trim((string) ($data['model'] ?? '')) ?: null,
                'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
                'serial_number' => trim((string) ($data['serial_number'] ?? '')) ?: null,
                'purchase_date' => $purchaseDate !== '' ? date('Y-m-d', strtotime($purchaseDate)) : null,
                'purchase_price' => is_numeric($data['purchase_price'] ?? null) ? $data['purchase_price'] : null,
                'condition' => $condition,
                'status' => $status,
            ]);

            AssetCodeService::generateQrCode($asset);
            $created++;
        }

        fclose($handle);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Import',
            'description' => "Bulk imported {$created} asset(s) from spreadsheet" . (count($errors) ? ', ' . count($errors) . ' row(s) skipped' : ''),
        ]);

        return back()->with('success', "{$created} asset(s) imported successfully.")->with('importErrors', $errors);
    }
}
