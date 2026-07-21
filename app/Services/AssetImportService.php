<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Imports the PEPY fixed-asset register from an Excel (.xlsx/.xls) or CSV file.
 *
 * It understands two layouts:
 *   1. The real PEPY workbook   — columns: Description, Asset ID, Purchase Date,
 *      Location, Price, Serial No., Currently Using, Used By, Remark. Existing
 *      Asset IDs (e.g. PEY-SR-FAF-0928) are PRESERVED because they are already
 *      printed on physical tags, and the category is derived from the ID.
 *   2. The simple template      — columns: name, category, description, model,
 *      brand, serial_number, purchase_date, purchase_price, condition, status.
 *      Here codes are auto-generated and the category is matched by name.
 *
 * Re-importing is safe: rows are upserted by asset code, so the same file can be
 * loaded twice without creating duplicates.
 */
class AssetImportService
{
    /** Category codes found in PEPY asset IDs → friendly names (auto-created if missing). */
    private const CATEGORY_NAMES = [
        'MOV' => 'Motor & Vehicle',
        'FAF' => 'Fixture & Furniture',
        'FVF' => 'Fixture & Furniture', // typo variant in the register
        'COM' => 'Computer Equipment',
        'EQU' => 'Equipment Unit',
    ];

    private array $categoryCache = [];

    private array $locationCache = [];

    public function import(UploadedFile $file, bool $generateQr = true): array
    {
        @set_time_limit(0);

        $rows = $this->readRows($file);
        if (empty($rows)) {
            throw new \RuntimeException('The file appears to be empty.');
        }

        [$map, $headerIndex] = $this->detectHeader($rows);
        if ($map === null) {
            throw new \RuntimeException('Could not find a header row. Expected a "Description"/"Asset ID" or "name"/"category" column.');
        }

        $preserveCodes = isset($map['code']); // PEPY layout preserves IDs; template does not

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($i <= $headerIndex) {
                continue;
            }
            $lineNo = $i + 1;

            $get = fn (string $key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';

            $name = $get('name');
            $code = $this->normalizeCode($get('code'));

            // In the PEPY layout, only rows with a real asset code are assets.
            // Section headers ("Motor & Vehicle ( MOV )" → code "PEY-SR-MOV", no
            // sequence) and subtotal rows ("Total MOV" → code "Till 0086") are skipped.
            if ($preserveCodes) {
                if (! $this->isAssetCode($code) || $name === '') {
                    $skipped++;

                    continue;
                }
            } elseif ($name === '') {
                $skipped++;

                continue;
            }

            // Resolve category
            try {
                $category = $preserveCodes
                    ? $this->categoryFromCode($code)
                    : $this->categoryByName($get('category'));
            } catch (\RuntimeException $e) {
                $errors[] = "Row {$lineNo}: ".$e->getMessage();

                continue;
            }

            $location = $this->resolveLocation($get('location'));

            $payload = [
                'name' => $name,
                'category_id' => $category->id,
                'location_id' => $location->id,
                'serial_number' => $get('serial') ?: null,
                'model' => $get('model') ?: null,
                'brand' => $get('brand') ?: null,
                'purchase_date' => $this->parseDate($get('date')),
                'purchase_price' => $this->parsePrice($get('price')),
                'condition' => $this->parseCondition($get('condition'), $get('remark')),
                'status' => $this->parseStatus($get('status')),
                'description' => $preserveCodes
                    ? $this->buildNote($get('location'), $get('using'), $get('used_by'), $get('remark'))
                    : ($get('description') ?: null),
            ];

            if ($preserveCodes) {
                $this->bumpSequenceFromCode($code);

                $existing = Asset::where('asset_code', $code)->first();
                if ($existing) {
                    $existing->update($payload);
                    $updated++;

                    continue;
                }
                $asset = Asset::create($payload + ['asset_code' => $code]);
            } else {
                $asset = Asset::create($payload + ['asset_code' => AssetCodeService::nextCode($location->id, $category->id)]);
            }

            if ($generateQr) {
                try {
                    AssetCodeService::generateQrCode($asset);
                } catch (\Throwable $e) {
                    // A QR failure must not abort a 900-row import.
                }
            }
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_rows' => count($rows) - $headerIndex - 1,
        ];
    }

    /** Read the first worksheet into a 0-indexed array of rows, values formatted as displayed. */
    private function readRows(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $type = match ($ext) {
            'csv', 'txt' => 'Csv',
            'xls' => 'Xls',
            default => 'Xlsx',
        };

        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(false); // keep formatting so dates/prices come as displayed text
        $spreadsheet = $reader->load($file->getRealPath());

        // Always the first sheet by index, not getActiveSheet() — a workbook's
        // "active" tab is whichever one was selected when it was last saved in
        // Excel, which may be an unrelated summary/subset sheet, not the register.
        // toArray(nullValue, calculateFormulas, formatData, returnCellRef)
        return $spreadsheet->getSheet(0)->toArray(null, true, true, false);
    }

    /** Locate the header row and build [logicalField => columnIndex]. */
    private function detectHeader(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $map = [];
            foreach ($row as $col => $cell) {
                $h = strtolower(trim((string) $cell));
                if ($h === '') {
                    continue;
                }
                if (str_contains($h, 'asset id')) {
                    $map['code'] = $col;
                } elseif ($h === 'name') {
                    $map['name'] = $col;
                } elseif ($h === 'description') {
                    $map['description_or_name'] = $col;
                } elseif ($h === 'category') {
                    $map['category'] = $col;
                } elseif (str_contains($h, 'purchase date') || $h === 'date') {
                    $map['date'] = $col;
                } elseif ($h === 'location') {
                    $map['location'] = $col;
                } elseif ($h === 'price' || str_contains($h, 'purchase price')) {
                    $map['price'] = $col;
                } elseif (str_contains($h, 'serial')) {
                    $map['serial'] = $col;
                } elseif ($h === 'model') {
                    $map['model'] = $col;
                } elseif ($h === 'brand') {
                    $map['brand'] = $col;
                } elseif ($h === 'condition') {
                    $map['condition'] = $col;
                } elseif ($h === 'status') {
                    $map['status'] = $col;
                } elseif (str_contains($h, 'using')) {
                    $map['using'] = $col;
                } elseif (str_contains($h, 'used by')) {
                    $map['used_by'] = $col;
                } elseif (str_contains($h, 'remark')) {
                    $map['remark'] = $col;
                }
            }

            $hasPepy = isset($map['code']) && isset($map['description_or_name']);
            $hasTemplate = isset($map['name']) && isset($map['category']);

            if ($hasPepy || $hasTemplate) {
                if ($hasPepy) {
                    $map['name'] = $map['description_or_name']; // Description is the item name
                }
                unset($map['description_or_name']);

                return [$map, $index];
            }
        }

        return [null, -1];
    }

    /**
     * Clean a raw asset id: uppercase, turn whitespace into dashes and collapse
     * runs. Fixes the register's typos like "PEY-SR- COM-0022" and "PEY-VR COM-0119"
     * into "PEY-SR-COM-0022" / "PEY-VR-COM-0119".
     */
    private function normalizeCode(string $code): string
    {
        $c = strtoupper(trim($code));
        $c = preg_replace('/\s+/', '-', $c);
        $c = preg_replace('/-+/', '-', $c);

        return trim($c, '-');
    }

    /** A real asset code carries a category segment AND a numeric sequence. */
    private function isAssetCode(string $code): bool
    {
        if ($code === '') {
            return false;
        }
        $segments = explode('-', $code);
        $hasCategory = (bool) array_intersect($segments, array_keys(self::CATEGORY_NAMES));
        $hasSequence = (bool) array_filter($segments, fn ($s) => preg_match('/^\d+$/', $s));

        return $hasCategory && $hasSequence;
    }

    /** Keep asset_code_sequences ahead of every preserved code so the next Register/Receive Asset call can't collide with it. */
    private function bumpSequenceFromCode(string $code): void
    {
        if (! preg_match('/^PEY-[A-Z]{2,4}-([A-Z]{2,4})-(\d+)$/', $code, $m)) {
            return;
        }
        $categoryCode = $m[1] === 'FVF' ? 'FAF' : $m[1];
        if (! in_array($categoryCode, AssetCodeService::CATEGORY_CODES, true)) {
            return;
        }
        AssetCodeService::bumpSequenceIfHigher($categoryCode, (int) $m[2]);
    }

    private function categoryFromCode(string $code): AssetCategory
    {
        $segments = explode('-', $code);
        $found = null;
        foreach ($segments as $seg) {
            if (isset(self::CATEGORY_NAMES[$seg])) {
                $found = $seg;
                break;
            }
        }
        if ($found === null) {
            throw new \RuntimeException("could not determine category from code \"{$code}\".");
        }
        $shortName = $found === 'FVF' ? 'FAF' : $found;

        return $this->resolveCategory($shortName, self::CATEGORY_NAMES[$found]);
    }

    private function categoryByName(string $name): AssetCategory
    {
        if ($name === '') {
            throw new \RuntimeException('category is required.');
        }
        $existing = AssetCategory::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if (! $existing) {
            throw new \RuntimeException("category \"{$name}\" not found.");
        }

        return $existing;
    }

    /** Find a category by short_name, creating it if missing. Cached per run. */
    private function resolveCategory(string $shortName, string $friendlyName): AssetCategory
    {
        if (isset($this->categoryCache[$shortName])) {
            return $this->categoryCache[$shortName];
        }

        $category = AssetCategory::whereRaw('UPPER(short_name) = ?', [$shortName])->first()
            ?? AssetCategory::create(['name' => $friendlyName, 'short_name' => $shortName]);

        return $this->categoryCache[$shortName] = $category;
    }

    /**
     * Match the register's free-text "Location" cell (e.g. "PEPY Office",
     * "Kralanh HS") against a known site by name. Falls back to the PEPY
     * Office site so a row with a blank/unrecognized location doesn't abort
     * the whole import — the row is still flagged in its description.
     */
    private function resolveLocation(string $name): Location
    {
        $key = strtolower(trim($name));
        if (isset($this->locationCache[$key])) {
            return $this->locationCache[$key];
        }

        $location = $key !== ''
            ? Location::whereRaw('LOWER(name) = ?', [$key])->first()
            : null;

        $location ??= Location::whereRaw('UPPER(code) = ?', ['SR'])->first();
        $location ??= Location::first();

        if (! $location) {
            throw new \RuntimeException('No locations exist to assign this asset to. Seed at least one site first.');
        }

        return $this->locationCache[$key] = $location;
    }

    private function parsePrice(string $raw): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $raw);

        return ($clean !== '' && is_numeric($clean) && (float) $clean > 0) ? (float) $clean : null;
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $formats = ['d-M-y', 'j-M-y', 'd-M-Y', 'j-M-Y', 'n/j/Y', 'm/d/Y', 'd-m-y', 'd-m-Y', 'Y-m-d', 'd/m/Y'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt && $dt->format($fmt) === $raw) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($raw);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function parseCondition(string $condition, string $remark): string
    {
        $c = strtolower(trim($condition));
        if (in_array($c, ['good', 'fair', 'broken', 'lost'], true)) {
            return $c;
        }
        $hay = strtolower($remark);
        if (str_contains($hay, 'lost')) {
            return 'lost';
        }
        if (str_contains($hay, 'broken') || str_contains($hay, 'damage')) {
            return 'broken';
        }

        return 'good';
    }

    private function parseStatus(string $status): string
    {
        $s = strtolower(trim($status));

        return in_array($s, ['active', 'disposed'], true) ? $s : 'active';
    }

    /** Fold the PEPY location/assignment context into the asset description. */
    private function buildNote(string $location, string $using, string $usedBy, string $remark): ?string
    {
        $parts = [];
        if ($location !== '') {
            $parts[] = "Location: {$location}";
        }
        if ($using !== '') {
            $parts[] = "Using: {$using}";
        }
        if ($usedBy !== '') {
            $parts[] = "Used by: {$usedBy}";
        }
        if ($remark !== '') {
            $parts[] = "Remark: {$remark}";
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
