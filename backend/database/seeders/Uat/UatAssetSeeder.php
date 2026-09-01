<?php

namespace Database\Seeders\Uat;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Services\AssetCodeService;
use Database\Seeders\Support\PepyInventory;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Imports the fixed-asset register from `.claude/commands/PEPY_Asset_Inventory_Cleaned.md`.
 *
 * Rules this seeder holds to, in order of precedence:
 *
 *  1. Every asset row traces to a documented row in that file. Nothing is
 *     invented, estimated, or substituted.
 *  2. Blanks stay blank. A missing price, purchase date or serial number in the
 *     source becomes NULL here — never 0, never a placeholder. Those gaps are
 *     the exact thing the data-completeness report exists to surface.
 *  3. Range rows are not expanded. A group the source only described as
 *     "PEY-SR-FAF-0034 to 0074" becomes ONE summary asset anchored on the first
 *     documented tag. The span itself is not stored: the assets table has no
 *     column for it, and the register screen shows `description` under every
 *     asset name, so writing it there filled the whole grid with import prose.
 *     Read the span from the source document, or add a column for it.
 *  4. `condition` and `status` are not in the source document at all, so every
 *     asset is created at the schema default (good / active). The handful of
 *     broken, lost and disposed assets in the UAT dataset are produced later by
 *     UatWorkflowSeeder, through the same state changes the real controllers
 *     make — a verification recording damage, a disposal being approved. The
 *     register itself is never edited to manufacture a test state.
 *
 * Codes are preserved exactly as printed, then fed to
 * AssetCodeService::bumpSequenceIfHigher() so the shared sequence counter can
 * never hand a colliding tag to the next Register Asset call.
 */
class UatAssetSeeder extends Seeder
{
    public array $stats = [
        'detail' => 0,
        'range' => 0,
        'updated' => 0,
        'skipped_no_id' => 0,
        'unresolved_location' => 0,
        'qr_generated' => 0,
        'qr_failed' => 0,
    ];

    /** @var array<int,string> */
    public array $notes = [];

    /**
     * Every asset code this seeder imported from the register source. The
     * summary uses it to tell seeded assets apart from ones that arrived some
     * other way — matching on the code is exact, where matching on a marker
     * written into the description was only ever a proxy.
     *
     * @var array<int,string>
     */
    public array $importedCodes = [];

    public function __construct(private bool $withQr = true) {}

    public function run(): void
    {
        $inventory = new PepyInventory;

        $categories = AssetCategory::pluck('id', 'short_name');
        $locations = Location::pluck('id', 'name');

        $highestSequence = [];

        foreach ($inventory->importableRows() as $row) {
            $categoryId = $categories[$row['category_code']] ?? null;

            if ($categoryId === null) {
                $this->notes[] = "No category for short_name {$row['category_code']} — skipped {$row['asset_code']}.";

                continue;
            }

            $locationId = $row['location_name'] ? ($locations[$row['location_name']] ?? null) : null;

            if ($row['location_name'] && $locationId === null) {
                $this->stats['unresolved_location']++;
                $this->notes[] = "Location \"{$row['location_name']}\" not found for {$row['asset_code']} — stored with no location.";
            }

            $asset = Asset::firstOrNew(['asset_code' => $row['asset_code']]);
            $existed = $asset->exists;

            $asset->fill([
                'name' => $row['name'],
                'category_id' => $categoryId,
                'location_id' => $locationId,
                'serial_number' => $row['serial_number'],
                'purchase_date' => $row['purchase_date'],
                'purchase_price' => $row['purchase_price'],
            ]);

            // Only stamp the defaults on creation — never reset a condition or
            // status that a seeded workflow (or a tester) has since moved on.
            if (! $existed) {
                $asset->condition = 'good';
                $asset->status = 'active';
                // Spread creation dates across the last 12 months so the
                // dashboard's day/month/year trend chart has real buckets.
                $asset->created_at = $this->registeredAt($row);
                $asset->updated_at = $asset->created_at;
            }

            $asset->save();
            $this->importedCodes[] = $row['asset_code'];

            if ($existed) {
                $this->stats['updated']++;
            } elseif ($row['kind'] === 'range') {
                $this->stats['range']++;
            } else {
                $this->stats['detail']++;
            }

            if (preg_match('/-([A-Z]{2,6})-(\d{4})$/', $row['asset_code'], $m)) {
                $code = $m[1];
                $seq = (int) $m[2];
                $highestSequence[$code] = max($highestSequence[$code] ?? 0, $seq);
            }

            if ($this->withQr && ! $asset->qr_code_path) {
                $this->generateQr($asset);
            }
        }

        foreach ($highestSequence as $categoryCode => $sequence) {
            AssetCodeService::bumpSequenceIfHigher($categoryCode, $sequence);
        }

        foreach ($inventory->unimportableRanges() as $row) {
            $this->stats['skipped_no_id']++;
            $this->notes[] = "Range group not imported (source gives no asset ID, and assets.asset_code is NOT NULL + UNIQUE): {$row['label']}";
        }
    }

    /**
     * A deterministic registration date derived from the asset code, spread
     * over the last 12 months. The register source has no "date added to the
     * system" column, so this is metadata about the import, not invented asset
     * data — without it every asset shares one created_at and the dashboard
     * trend chart renders a single spike.
     */
    private function registeredAt(array $row): \Illuminate\Support\Carbon
    {
        $seed = crc32($row['asset_code']);

        return now()->subDays($seed % 360)->setTime(8 + ($seed % 9), $seed % 60);
    }

    private function generateQr(Asset $asset): void
    {
        try {
            AssetCodeService::generateQrCode($asset);
            $this->stats['qr_generated']++;
        } catch (Throwable $e) {
            $this->stats['qr_failed']++;
            if ($this->stats['qr_failed'] === 1) {
                $this->notes[] = 'QR generation failed ('.$e->getMessage().'). The PHP GD extension is required; assets are still seeded without QR images.';
            }
        }
    }
}
