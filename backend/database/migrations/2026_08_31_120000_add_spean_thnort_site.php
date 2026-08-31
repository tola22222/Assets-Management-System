<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the 13th PEPY site: Spean Thnort HS.
 *
 * The original site list (2026_07_20_161520_add_code_to_locations_table) seeded
 * only 12 rows while its own docblock claimed "13 real PEPY sites (office + 12
 * partner high schools)". The register confirms the docblock was right and the
 * data was short: it contains 12 distinct school names plus PEPY Office.
 *
 * The school was missed because the fixed-asset register reuses one site code
 * for two schools — assets at BOTH "Sna Techo 317 HS" and "Spean Thnort HS" are
 * tagged PEY-ST-…. Sna Techo took the ST slot, and Spean Thnort was dropped.
 *
 * Spean Thnort therefore gets its own code, SP. Existing printed tags are NOT
 * affected: bulk import preserves the codes already on the assets, so the ~7
 * PEY-ST- assets physically at Spean Thnort keep their labels. Only newly
 * registered assets at this site generate PEY-SP-… codes.
 *
 * A separate migration rather than an edit to the original because that one has
 * already run in production — editing it would never execute again.
 */
return new class extends Migration
{
    private const CODE = 'SP';

    private const NAME = 'Spean Thnort HS';

    public function up(): void
    {
        // Idempotent on both keys: the site may already exist by name (added by
        // hand through Locations CRUD) or the code may have been taken.
        $existing = DB::table('locations')
            ->where('name', self::NAME)
            ->orWhere('code', self::CODE)
            ->first();

        if ($existing) {
            if ($existing->name === self::NAME && blank($existing->code)) {
                DB::table('locations')->where('id', $existing->id)->update(['code' => self::CODE]);
            }

            return;
        }

        DB::table('locations')->insert([
            'name' => self::NAME,
            'code' => self::CODE,
            'type' => 'program',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Only remove the row if nothing has been registered against it, so a
        // rollback can never orphan assets.
        $site = DB::table('locations')->where('code', self::CODE)->where('name', self::NAME)->first();

        if ($site && ! DB::table('assets')->where('location_id', $site->id)->exists()) {
            DB::table('locations')->where('id', $site->id)->delete();
        }
    }
};
