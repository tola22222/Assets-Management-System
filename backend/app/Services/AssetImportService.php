<?php

namespace App\Services;

use App\Exceptions\AssetCodeException;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
 * Re-importing the PEPY layout is safe: rows are matched by asset code, and a
 * row that matches an asset already on the register only FILLS fields that
 * are still blank on it. It never touches the asset's status, condition or
 * location — those are owned by the disposal, damage-flag and transfer
 * workflows, and a re-import of an old spreadsheet must not undo them. (The
 * template layout has no asset code to match on, so re-importing it creates
 * new assets each time.)
 *
 * The whole import runs in one database transaction: an unexpected failure on
 * row 500 rolls back rows 1-499 too, and any photos stored for them are
 * deleted, rather than leaving half a register behind. Per-row data problems
 * (unknown category, unknown location in the template layout) are not
 * failures — those rows are skipped and reported in `errors`, as before.
 *
 * Optionally accepts a batch of photo files alongside the sheet. With exactly
 * one photo, it's applied to every row in the import — no renaming needed,
 * useful for a quick placeholder photo across a whole batch. With more than
 * one photo, each is instead matched to a row by its filename (without
 * extension) against that row's asset code first, then its serial number —
 * e.g. "PEY-SR-COM-0212.jpg" or "SN123456.png" — so a bulk per-asset photo
 * upload doesn't require any extra column in the spreadsheet itself.
 */
class AssetImportService
{
    /**
     * Legacy category codes found in the historical PEPY register → friendly
     * names. Only consulted when a code's category segment matches no
     * asset_categories.short_name in the database; the category is then
     * created with this name.
     */
    private const CATEGORY_NAMES = [
        'MOV' => 'Motor & Vehicle',
        'FAF' => 'Fixture & Furniture',
        'FVF' => 'Fixture & Furniture', // typo variant in the register
        'COM' => 'Computer Equipment',
        'EQU' => 'Equipment Unit',
    ];

    /** Register typos that stand for another category code. */
    private const CATEGORY_ALIASES = ['FVF' => 'FAF'];

    /**
     * A well-formed tag: PEY-[SITE]-[CATEGORY]-[####]. The category segment
     * follows AssetCodeService::CODE_FORMAT (2-6 letters/digits). The site
     * segment is accepted at the same width; real site codes are 2-4 (see
     * LocationController), so a longer one simply matches no location.
     */
    private const CODE_PATTERN = '/^PEY-([A-Z0-9]{2,6})-([A-Z0-9]{2,6})-(\d+)$/';

    /** Largest photo accepted, in bytes. */
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    /** Existing-asset fields a re-import may fill in when they are blank. */
    private const FILLABLE_ON_REIMPORT = [
        'name', 'serial_number', 'model', 'brand', 'purchase_date', 'purchase_price', 'description',
    ];

    /** Category-code segment (upper-cased) → category, or null when it matches none. */
    private array $categoryCache = [];

    private array $locationCache = [];

    private ?Collection $allLocations = null;

    /** Photos written to the public disk by the current run — deleted again if it rolls back. */
    private array $storedFiles = [];

    /**
     * @param  UploadedFile[]  $images  Optional photo files, matched to rows by filename.
     *
     * @throws ValidationException for problems with the file itself (unreadable,
     *                             empty, no header row, no locations to assign
     *                             to). Anything else thrown is unexpected and
     *                             has already been rolled back.
     */
    public function import(UploadedFile $file, bool $generateQr = true, array $images = []): array
    {
        @set_time_limit(0);

        $this->categoryCache = [];
        $this->locationCache = [];
        $this->allLocations = null;
        $this->storedFiles = [];

        // Checked per-file (not via the request validator) so one bad photo in
        // a bulk batch — wrong type, corrupted, oversized — is skipped and
        // reported instead of aborting the whole import.
        $uploadedCount = 0;
        $validImages = [];
        $rejectedImages = [];
        foreach ($images as $imageFile) {
            if (! $imageFile instanceof UploadedFile) {
                continue;
            }
            $uploadedCount++;
            $reason = $this->imageRejectionReason($imageFile);
            if ($reason !== null) {
                $rejectedImages[] = ['name' => $imageFile->getClientOriginalName(), 'reason' => $reason];

                continue;
            }
            $validImages[] = $imageFile;
        }

        // A single photo is applied to every row in this import — no renaming
        // needed. Filename-based per-row matching only kicks in once there's
        // more than one photo to tell apart. Decided by how many photos were
        // UPLOADED, not how many survived the checks above: two photos named
        // after two assets, one of them oversized, must not turn the other into
        // a placeholder stamped on every row.
        $sharedImage = $uploadedCount === 1 && count($validImages) === 1 ? $validImages[0] : null;

        // Filename (without extension) → uploaded photo, for the multi-photo
        // case. Whitespace-stripped/uppercased so "PEY-SR-COM-0212.jpg"
        // matches asset code "PEY-SR-COM-0212" and "sn 123456.png" matches
        // serial "SN123456".
        $imagesByKey = [];
        if ($sharedImage === null) {
            foreach ($validImages as $imageFile) {
                $key = $this->normalizeKey(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
                if ($key !== '') {
                    $imagesByKey[$key] = $imageFile;
                }
            }
        }

        try {
            $rows = $this->readRows($file);
        } catch (\Throwable $e) {
            // PhpSpreadsheet's own exceptions are low-level (zip/XML parser
            // errors) and leak the server's temp file path — never show them
            // to the user directly. This is what a renamed/corrupted file, or
            // a non-Excel file given an .xlsx/.xls extension, looks like.
            throw $this->fileError(
                'Could not read this file as a spreadsheet. Make sure it\'s a valid, unmodified .xlsx, .xls, or .csv export — not a renamed or corrupted file — then try again.'
            );
        }

        if (empty($rows)) {
            throw $this->fileError('The file appears to be empty.');
        }

        [$map, $headerIndex] = $this->detectHeader($rows);
        if ($map === null) {
            throw $this->fileError('Could not find a header row. Expected a "Description"/"Asset ID" or "name"/"category" column.');
        }

        try {
            $run = DB::transaction(fn () => $this->importRows($rows, $map, $headerIndex, $sharedImage, $imagesByKey));
        } catch (\Throwable $e) {
            // The rows are gone with the rollback; the photos stored for them
            // are not, so remove them too.
            $this->deleteFiles($this->storedFiles);
            throw $e;
        }

        // Only once the rows are committed: a photo replaced by a re-import is
        // deleted now, not before — a rollback would otherwise have left the
        // asset pointing at a file that no longer exists.
        $this->deleteFiles($run['superseded_files']);

        $warnings = $run['warnings'];

        // Outside the transaction and best-effort: a QR failure must not abort
        // (or roll back) a 900-row import. Each failure is still counted, so
        // the user learns which tags will need regenerating.
        if ($generateQr) {
            $failed = 0;
            $firstError = null;
            foreach ($run['created_assets'] as $asset) {
                try {
                    AssetCodeService::generateQrCode($asset);
                } catch (\Throwable $e) {
                    $failed++;
                    $firstError ??= $e;
                }
            }
            if ($failed > 0) {
                Log::warning('Asset import: QR code generation failed', ['count' => $failed, 'exception' => $firstError]);
                $warnings[] = "QR codes could not be generated for {$failed} new asset(s). Use \"Regenerate QR\" on each asset once the problem is fixed.";
            }
        }

        return [
            'created' => $run['created'],
            'updated' => $run['updated'],
            'skipped' => $run['skipped'],
            'errors' => $run['errors'],
            'total_rows' => count($rows) - $headerIndex - 1,
            'images_attached' => $run['images_attached'],
            'images_unmatched' => array_values(array_merge(
                array_column($rejectedImages, 'name'),
                array_diff_key(
                    array_map(fn (UploadedFile $f) => $f->getClientOriginalName(), $imagesByKey),
                    $run['used_image_keys']
                )
            )),
            // Added alongside the keys above, which are unchanged:
            // rows that matched an existing asset with nothing blank to fill,
            'unchanged' => $run['unchanged'],
            // rows imported with a fallback the user should check (location
            // guessed from the site code or defaulted, QR failures),
            'warnings' => $warnings,
            // and photos refused before matching, with the reason (their names
            // are also still listed in images_unmatched).
            'images_rejected' => $rejectedImages,
        ];
    }

    /**
     * The row loop. Runs inside the import's transaction, so anything it
     * throws undoes every row written before it.
     */
    private function importRows(array $rows, array $map, int $headerIndex, ?UploadedFile $sharedImage, array $imagesByKey): array
    {
        $preserveCodes = isset($map['code']); // PEPY layout preserves IDs; template does not

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];
        $createdAssets = [];
        $supersededFiles = [];
        $usedImageKeys = [];
        $imagesAttached = 0;

        foreach ($rows as $i => $row) {
            if ($i <= $headerIndex) {
                continue;
            }
            $lineNo = $i + 1;

            $get = fn (string $key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';

            $name = $get('name');
            $code = $this->normalizeCode($get('code'));
            $parsedCode = $preserveCodes ? $this->parseCode($code) : null;

            // In the PEPY layout, only rows with a real asset code are assets.
            // Section headers ("Motor & Vehicle ( MOV )" → code "PEY-SR-MOV", no
            // sequence) and subtotal rows ("Total MOV" → code "Till 0086", no
            // category) are skipped.
            if ($preserveCodes) {
                if ($name === '' || ! $this->hasSequence($code)) {
                    $skipped++;

                    continue;
                }
            } elseif ($name === '') {
                $skipped++;

                continue;
            }

            // Resolve category
            if ($preserveCodes) {
                $category = $this->categoryFromCode($code, $parsedCode);
                if ($category === null) {
                    if ($parsedCode === null) {
                        // Not tag-shaped and no category segment: a subtotal
                        // or note row, not an asset.
                        $skipped++;
                    } else {
                        // A properly formed tag whose category nobody has set
                        // up — say so instead of silently dropping the asset.
                        $errors[] = "Row {$lineNo}: category code \"{$parsedCode['category']}\" in asset ID \"{$code}\" does not match any category. Add a category with that short code on the Categories screen, then import again.";
                    }

                    continue;
                }
            } else {
                try {
                    $category = $this->categoryByName($get('category'));
                } catch (\UnexpectedValueException $e) {
                    $errors[] = "Row {$lineNo}: ".$e->getMessage();

                    continue;
                }
            }

            // Location is resolved per branch below: the PEPY layout only
            // needs one for a NEW asset (an existing asset's location is never
            // changed by an import), while the template layout requires one on
            // every row.
            $payload = [
                'name' => $name,
                'category_id' => $category->id,
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

            // A single uploaded photo applies to every row. Otherwise match a
            // bulk-uploaded photo to this row: asset code first (only
            // meaningful for the PEPY layout, where it's known before the row
            // is even created), then serial number (available in both layouts).
            $imageFile = null;
            $imageKey = null;
            if ($sharedImage !== null) {
                $imageFile = $sharedImage;
            } elseif ($code !== '' && isset($imagesByKey[$this->normalizeKey($code)])) {
                $imageKey = $this->normalizeKey($code);
                $imageFile = $imagesByKey[$imageKey];
            } elseif ($payload['serial_number'] && isset($imagesByKey[$this->normalizeKey($payload['serial_number'])])) {
                $imageKey = $this->normalizeKey($payload['serial_number']);
                $imageFile = $imagesByKey[$imageKey];
            }

            if ($preserveCodes) {
                $this->bumpSequenceFromCode($parsedCode, $category);

                $existing = Asset::where('asset_code', $code)->first();
                if ($existing) {
                    // Fill blanks only. status, condition and location_id are
                    // deliberately never written: a spreadsheet re-import must
                    // not re-activate a disposed asset, clear a damage flag or
                    // undo a transfer.
                    $fill = [];
                    foreach (self::FILLABLE_ON_REIMPORT as $field) {
                        if ($this->isBlank($existing->getAttribute($field)) && ! $this->isBlank($payload[$field])) {
                            $fill[$field] = $payload[$field];
                        }
                    }

                    // A photo named after this asset is an explicit request to
                    // (re)place its photo; the one-photo-for-everything
                    // placeholder only fills an asset that has none.
                    if ($imageFile && ($imageKey !== null || $this->isBlank($existing->image_path))) {
                        $stored = $this->storeImage($imageFile);
                        if ($stored !== null) {
                            if (! $this->isBlank($existing->image_path)) {
                                $supersededFiles[] = $existing->image_path;
                            }
                            $fill['image_path'] = $stored;
                            if ($imageKey !== null) {
                                $usedImageKeys[$imageKey] = true;
                            }
                            $imagesAttached++;
                        }
                    }

                    if ($fill) {
                        $existing->update($fill);
                        $updated++;
                    } else {
                        $unchanged++;
                    }

                    continue;
                }

                $location = $this->resolvePepyLocation(
                    $get('location'),
                    $this->siteSegment($code, $parsedCode),
                    "Row {$lineNo} ({$code})",
                    $warnings
                );

                if ($imageFile && ($stored = $this->storeImage($imageFile)) !== null) {
                    $payload['image_path'] = $stored;
                    if ($imageKey !== null) {
                        $usedImageKeys[$imageKey] = true;
                    }
                    $imagesAttached++;
                }
                $asset = Asset::create($payload + ['location_id' => $location->id, 'asset_code' => $code]);
            } else {
                // The template layout has no excuse for a missing location:
                // it is a required field on the Register Asset form and in the
                // database, so a missing/unrecognized one is a row error here,
                // same as category.
                try {
                    $location = $this->requireLocation($get('location'));
                } catch (\UnexpectedValueException $e) {
                    $errors[] = "Row {$lineNo}: ".$e->getMessage();

                    continue;
                }

                // Generated before the photo is stored so a rejected code
                // leaves no orphaned file — and reported as a row error rather
                // than allowed to escape, because an uncaught throw here would
                // abort (and roll back) the whole upload over one site that is
                // simply missing its code.
                try {
                    $assetCode = AssetCodeService::nextCode($location->id, $category->id);
                } catch (AssetCodeException $e) {
                    $errors[] = "Row {$lineNo}: ".$e->getMessage();

                    continue;
                }

                if ($imageFile && ($stored = $this->storeImage($imageFile)) !== null) {
                    $payload['image_path'] = $stored;
                    if ($imageKey !== null) {
                        $usedImageKeys[$imageKey] = true;
                    }
                    $imagesAttached++;
                }
                $asset = Asset::create($payload + ['location_id' => $location->id, 'asset_code' => $assetCode]);
            }

            $createdAssets[] = $asset;
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'errors' => $errors,
            'warnings' => $warnings,
            'created_assets' => $createdAssets,
            'superseded_files' => $supersededFiles,
            'used_image_keys' => $usedImageKeys,
            'images_attached' => $imagesAttached,
        ];
    }

    /** Why a photo can't be used, or null when it can. */
    private function imageRejectionReason(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'the upload did not complete (it may be larger than the server allows)';
        }
        if ($file->getSize() > self::MAX_IMAGE_BYTES) {
            return 'larger than 8 MB';
        }
        if (! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true)
            || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png'], true)) {
            return 'not a JPG or PNG image';
        }

        return null;
    }

    /** Store a photo on the public disk, remembering it so a rollback can remove it again. */
    private function storeImage(UploadedFile $file): ?string
    {
        $path = $file->store('assets', 'public');
        if (! is_string($path) || $path === '') {
            return null;
        }
        $this->storedFiles[] = $path;

        return $path;
    }

    /** Best-effort delete: cleanup must never mask the outcome it follows. */
    private function deleteFiles(array $paths): void
    {
        if (! $paths) {
            return;
        }
        try {
            Storage::disk('public')->delete($paths);
        } catch (\Throwable $e) {
            Log::warning('Asset import: could not delete photo files', ['paths' => $paths, 'exception' => $e]);
        }
    }

    /** A problem with the uploaded file itself, surfaced as a normal 422 on the `file` field. */
    private function fileError(string $message): ValidationException
    {
        return ValidationException::withMessages(['file' => $message]);
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /** Whitespace-stripped, uppercased key used to match a photo filename against an asset code or serial number. */
    private function normalizeKey(string $s): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($s)));
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
                // Normalize underscores to spaces so the template's own
                // snake_case headers (purchase_date, purchase_price) match
                // the same rules as the free-text "Purchase Price" headers
                // used in the real PEPY register — without this, a file
                // built from the downloadable template silently imported
                // with no price or date at all.
                $h = str_replace('_', ' ', strtolower(trim((string) $cell)));
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

    /**
     * Split a well-formed PEY-[SITE]-[CATEGORY]-[####] tag into its parts, or
     * null when the code isn't in that exact shape.
     *
     * @return array{site: string, category: string, sequence: int}|null
     */
    private function parseCode(string $code): ?array
    {
        if (! preg_match(self::CODE_PATTERN, $code, $m)) {
            return null;
        }

        return ['site' => $m[1], 'category' => $m[2], 'sequence' => (int) $m[3]];
    }

    /** A real asset code carries a numeric sequence (its category is checked separately). */
    private function hasSequence(string $code): bool
    {
        if ($code === '') {
            return false;
        }

        return (bool) array_filter(explode('-', $code), fn ($s) => preg_match('/^\d+$/', $s));
    }

    /**
     * The code's SITE segment, used to place a row whose Location cell is
     * blank or unrecognised. For a code not in the exact tag shape, the
     * segment after a leading "PEY" is used when it looks like a site code.
     */
    private function siteSegment(string $code, ?array $parsedCode): ?string
    {
        if ($parsedCode !== null) {
            return $parsedCode['site'];
        }
        $segments = explode('-', $code);
        if (($segments[0] ?? '') === 'PEY' && isset($segments[1]) && preg_match('/^[A-Z0-9]{2,6}$/', $segments[1])) {
            return $segments[1];
        }

        return null;
    }

    /**
     * Keep asset_code_sequences ahead of every preserved code so the next
     * Register Asset call can't hand out a number already on a printed tag.
     * The sequence row is the category's own short code — the same key
     * AssetCodeService::nextCode() increments — so a custom category is kept
     * ahead exactly like the original four.
     */
    private function bumpSequenceFromCode(?array $parsedCode, AssetCategory $category): void
    {
        if ($parsedCode === null) {
            return;
        }
        $categoryCode = strtoupper(trim((string) $category->short_name));
        if (! preg_match(AssetCodeService::CODE_FORMAT, $categoryCode)) {
            return;
        }
        AssetCodeService::bumpSequenceIfHigher($categoryCode, $parsedCode['sequence']);
    }

    /**
     * The category an asset code belongs to, or null when it has none.
     *
     * For a well-formed tag the category is its third segment. Anything
     * else falls back to the first segment that names a known category —
     * how the historical register's irregular codes were always read.
     */
    private function categoryFromCode(string $code, ?array $parsedCode): ?AssetCategory
    {
        if ($parsedCode !== null) {
            return $this->categoryForSegment($parsedCode['category']);
        }

        foreach (explode('-', $code) as $segment) {
            if ($segment === 'PEY' || preg_match('/^\d+$/', $segment)) {
                continue;
            }
            if ($category = $this->categoryForSegment($segment)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Resolve a code's category segment: first against every category's
     * short_name in the database (case-insensitive), so a category an admin
     * added is recognised; then against the register's legacy codes, which
     * create their category when it doesn't exist yet. Cached per run.
     */
    private function categoryForSegment(string $segment): ?AssetCategory
    {
        $segment = strtoupper($segment);
        if (array_key_exists($segment, $this->categoryCache)) {
            return $this->categoryCache[$segment];
        }

        $category = $this->categoryByShortName($segment);

        if (! $category && isset(self::CATEGORY_NAMES[$segment])) {
            $shortName = self::CATEGORY_ALIASES[$segment] ?? $segment;
            $category = $this->categoryByShortName($shortName)
                ?? AssetCategory::create(['name' => self::CATEGORY_NAMES[$segment], 'short_name' => $shortName]);
        }

        return $this->categoryCache[$segment] = $category;
    }

    private function categoryByShortName(string $shortName): ?AssetCategory
    {
        return AssetCategory::whereRaw('UPPER(TRIM(short_name)) = ?', [strtoupper($shortName)])->orderBy('id')->first();
    }

    private function categoryByName(string $name): AssetCategory
    {
        if ($name === '') {
            throw new \UnexpectedValueException('category is required.');
        }
        $existing = AssetCategory::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if (! $existing) {
            throw new \UnexpectedValueException("category \"{$name}\" not found.");
        }

        return $existing;
    }

    /**
     * Strict counterpart to resolvePepyLocation() for the template layout — see
     * the call site for why.
     *
     * Location names are not unique, and production has held a hand-made
     * duplicate of a seeded site sitting alongside the real one. Where several
     * rows share a name, prefer the one that actually has a site code: the
     * code-less twin can't mint an asset tag, so picking it just fails the row.
     */
    private function requireLocation(string $name): Location
    {
        if ($name === '') {
            throw new \UnexpectedValueException('location is required.');
        }
        $key = strtolower($name);
        if (isset($this->locationCache[$key])) {
            return $this->locationCache[$key];
        }
        $existing = Location::whereRaw('LOWER(name) = ?', [$key])->orderByRaw('code is null')->first();
        if (! $existing) {
            throw new \UnexpectedValueException("location \"{$name}\" not found.");
        }

        return $this->locationCache[$key] = $existing;
    }

    /**
     * Place a new asset from the PEPY register, whose free-text "Location"
     * column is known to be messy (typos, blanks) across 900+ rows:
     *
     *   1. the Location cell, matched by name — case-insensitive, trimmed,
     *      inner whitespace collapsed;
     *   2. else the site whose code is the asset ID's SITE segment
     *      (PEY-[SITE]-…), when exactly one site has that code;
     *   3. else the PEPY Office (code SR), or failing that the first site.
     *
     * Steps 2 and 3 are guesses, so each one is recorded in $warnings for the
     * user to check, rather than applied silently.
     */
    private function resolvePepyLocation(string $rawName, ?string $siteCode, string $rowLabel, array &$warnings): Location
    {
        $locations = $this->allLocations ??= Location::orderBy('id')->get();
        $name = trim($rawName);

        if ($name !== '') {
            $key = $this->normalizeLocationName($name);
            $matches = $locations->filter(fn (Location $l) => $this->normalizeLocationName((string) $l->name) === $key);
            if ($matches->isNotEmpty()) {
                // Duplicate names: the one this tag's site code points at,
                // then any that has a site code, then the oldest.
                return $matches->first(fn (Location $l) => $siteCode !== null && $this->locationCode($l) === $siteCode)
                    ?? $matches->first(fn (Location $l) => $this->locationCode($l) !== '')
                    ?? $matches->first();
            }
        }

        $given = $name === '' ? 'no location given' : "location \"{$name}\" not recognised";

        if ($siteCode !== null) {
            $bySite = $locations->filter(fn (Location $l) => $this->locationCode($l) === $siteCode);
            if ($bySite->count() === 1) {
                $location = $bySite->first();
                $warnings[] = "{$rowLabel}: {$given}; assigned to \"{$location->name}\" from site code {$siteCode} in the asset ID.";

                return $location;
            }
        }

        $default = $locations->first(fn (Location $l) => $this->locationCode($l) === 'SR') ?? $locations->first();
        if (! $default) {
            throw $this->fileError('No locations exist to assign this asset to. Seed at least one site first.');
        }

        $warnings[] = "{$rowLabel}: {$given}"
            .($siteCode !== null ? " and site code {$siteCode} matches no location" : '')
            ."; assigned to the default site \"{$default->name}\".";

        return $default;
    }

    private function normalizeLocationName(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    private function locationCode(Location $location): string
    {
        return strtoupper(trim((string) $location->code));
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
