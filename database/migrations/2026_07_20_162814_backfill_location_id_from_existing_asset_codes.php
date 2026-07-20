<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Assets whose codes already conformed to PEY-SITE-CATEGORY-#### (e.g.
     * preserved by the bulk import) were skipped by the code-renaming
     * backfill migration, so they never got a location_id set. Derive it
     * from the site segment already encoded in their asset_code.
     */
    public function up(): void
    {
        $assets = DB::table('assets')->whereNull('location_id')->get();
        $locationsByCode = DB::table('locations')->whereNotNull('code')->get()->keyBy(fn ($l) => strtoupper($l->code));

        foreach ($assets as $asset) {
            if (! preg_match('/^PEY-([A-Z]{2,4})-(MOV|FAF|COM|EQU)-\d+$/', $asset->asset_code, $m)) {
                continue;
            }
            $site = $locationsByCode->get($m[1]);
            if ($site) {
                DB::table('assets')->where('id', $asset->id)->update(['location_id' => $site->id]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
