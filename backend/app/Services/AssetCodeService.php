<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Generates asset tags in the PEY-[SITE]-[CATEGORY]-[####] format defined by
 * the Asset Checking & Counting Manual. The numeric sequence is global per
 * category (not per site, not per year), never resets, and is never reused
 * — it always increments from the highest number ever issued for that
 * category, tracked in the `asset_code_sequences` table.
 */
class AssetCodeService
{
    /**
     * The four categories the manual originally shipped with. Categories are
     * no longer locked to this list — any category with a well-formed
     * short_name (see CODE_FORMAT) can generate codes — but these remain the
     * suggested/default options in the category form, and AssetImportService
     * still uses this specific list to parse the historical PEPY register's
     * legacy asset-code segments.
     */
    public const CATEGORY_CODES = ['MOV', 'FAF', 'COM', 'EQU'];

    /** A category short_name must look like this to be usable in an asset tag. */
    public const CODE_FORMAT = '/^[A-Z0-9]{2,6}$/';

    /**
     * @throws InvalidArgumentException if the category has no well-formed
     *                                  short_name, or the location isn't an approved site.
     */
    public static function nextCode(?int $locationId, int $categoryId): string
    {
        $category = AssetCategory::findOrFail($categoryId);
        $categoryCode = strtoupper((string) $category->short_name);

        if (! preg_match(self::CODE_FORMAT, $categoryCode)) {
            throw new InvalidArgumentException(
                "Category \"{$category->name}\" has no valid short code assigned. Set a 2-6 letter/number code for it on the Categories screen before registering assets in it."
            );
        }

        $location = $locationId ? Location::find($locationId) : null;
        $siteCode = $location?->code;

        if (! $siteCode) {
            throw new InvalidArgumentException('Asset location must be an approved site with a site code.');
        }

        return DB::transaction(function () use ($siteCode, $categoryCode) {
            DB::table('asset_code_sequences')->insertOrIgnore([
                'category_code' => $categoryCode,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('asset_code_sequences')
                ->where('category_code', $categoryCode)
                ->lockForUpdate()
                ->first();

            $next = $sequence->last_sequence + 1;

            DB::table('asset_code_sequences')
                ->where('category_code', $categoryCode)
                ->update(['last_sequence' => $next, 'updated_at' => now()]);

            return "PEY-{$siteCode}-{$categoryCode}-".str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Bulk import preserves existing printed-tag codes instead of generating
     * new ones (see AssetImportService), so it never goes through nextCode()'s
     * increment. Without this, the sequence counter stays wherever it was left
     * after the initial migration backfill, and the next Register/Receive
     * Asset call can hand out a number that collides with an already-imported
     * code (unique constraint violation on assets.asset_code). Call this for
     * every preserved code so the counter never falls behind what's on disk.
     */
    public static function bumpSequenceIfHigher(string $categoryCode, int $sequence): void
    {
        DB::transaction(function () use ($categoryCode, $sequence) {
            DB::table('asset_code_sequences')->insertOrIgnore([
                'category_code' => $categoryCode,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('asset_code_sequences')
                ->where('category_code', $categoryCode)
                ->lockForUpdate()
                ->first();

            if ($sequence > $row->last_sequence) {
                DB::table('asset_code_sequences')
                    ->where('category_code', $categoryCode)
                    ->update(['last_sequence' => $sequence, 'updated_at' => now()]);
            }
        });
    }

    public static function generateQrCode(Asset $asset): void
    {
        $url = route('asset.public.show', $asset->asset_code);

        $result = (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        ))->build();

        $path = 'qrcodes/'.$asset->asset_code.'.png';

        Storage::disk('public')->put($path, $result->getString());

        $asset->update(['qr_code_path' => $path]);
    }
}
