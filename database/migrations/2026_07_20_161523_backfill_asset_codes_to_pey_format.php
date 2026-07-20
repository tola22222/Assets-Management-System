<?php

use App\Services\AssetCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Renames any pre-existing asset code that isn't already in the
     * PEY-[SITE]-[CATEGORY]-[####] scheme (e.g. legacy FAF-2026-000042 codes
     * from the old AssetCodeService) into the new format, and points each
     * renamed asset at the PEPY Office location (SR) since the legacy scheme
     * never recorded a site. Codes already in the PEY-SITE-CATEGORY-####
     * form (from the bulk import, which preserves printed tag numbers) are
     * left untouched.
     */
    public function up(): void
    {
        $office = DB::table('locations')->where('code', 'SR')->first();

        $assets = DB::table('assets')->get();
        foreach ($assets as $asset) {
            if (preg_match('/^PEY-[A-Z]{2,4}-(MOV|FAF|COM|EQU)-\d+$/', $asset->asset_code)) {
                continue; // already conforms
            }

            $category = DB::table('asset_categories')->find($asset->category_id);
            if (! $category) {
                continue;
            }

            $newCode = AssetCodeService::nextCode($office ? $office->id : null, $category->id);

            DB::table('assets')->where('id', $asset->id)->update([
                'asset_code' => $newCode,
                'location_id' => $asset->location_id ?? ($office->id ?? null),
                'updated_at' => now(),
            ]);

            if ($asset->qr_code_path) {
                Storage::disk('public')->delete($asset->qr_code_path);
            }

            $fresh = \App\Models\Asset::find($asset->id);
            if ($fresh) {
                try {
                    AssetCodeService::generateQrCode($fresh);
                } catch (\Throwable $e) {
                    // QR regeneration failing must not block the migration.
                }
            }
        }
    }

    /**
     * Renaming asset codes is not safely reversible (the original code is
     * not recorded), so down() is intentionally a no-op.
     */
    public function down(): void
    {
        //
    }
};
