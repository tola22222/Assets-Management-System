<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A dedicated counter per category code (MOV/FAF/COM/EQU) so asset-tag
     * sequences are global (not per-site, not per-year), monotonically
     * increasing, and never reused, per the Asset Checking & Counting Manual.
     * Rows are locked with lockForUpdate() in AssetCodeService so concurrent
     * "add asset" requests can never hand out the same number.
     */
    public function up(): void
    {
        Schema::create('asset_code_sequences', function (Blueprint $table) {
            $table->string('category_code', 10)->primary();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        // Seed each counter from the highest sequence already in use, so
        // pre-existing PEY-SITE-CATEGORY-#### codes (e.g. from the bulk
        // import) are never reissued.
        $rows = DB::table('assets')->select('asset_code')->get();
        $highest = [];
        foreach ($rows as $row) {
            if (preg_match('/^PEY-[A-Z]{2,4}-([A-Z]{2,4})-(\d+)$/', $row->asset_code, $m)) {
                $category = $m[1];
                $seq = (int) $m[2];
                $highest[$category] = max($highest[$category] ?? 0, $seq);
            }
        }
        foreach (['MOV', 'FAF', 'COM', 'EQU'] as $category) {
            DB::table('asset_code_sequences')->insert([
                'category_code' => $category,
                'last_sequence' => $highest[$category] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_code_sequences');
    }
};
