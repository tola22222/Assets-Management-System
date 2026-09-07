<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A staff member may be responsible for at most one program.
 *
 * Accountability for a site's assets is meant to point at one person per
 * program; letting someone lead several blurs who actually answers for a
 * delivery. The unique index makes that structural rather than a rule the
 * controller alone remembers — nullable columns allow repeated NULLs in both
 * MySQL and SQLite, so unassigned programs are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->releaseDuplicateLeads();

        Schema::table('programs', function (Blueprint $table) {
            $table->unique('responsible_staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['responsible_staff_id']);
        });
    }

    /**
     * Existing data can already have one person leading several programs (the
     * seeder used to do exactly that). The oldest program keeps the lead and
     * the rest are freed, rather than failing the migration and leaving the
     * constraint unenforceable.
     */
    private function releaseDuplicateLeads(): void
    {
        $keep = DB::table('programs')
            ->whereNotNull('responsible_staff_id')
            ->groupBy('responsible_staff_id')
            ->selectRaw('MIN(id) as id')
            ->pluck('id');

        DB::table('programs')
            ->whereNotNull('responsible_staff_id')
            ->whereNotIn('id', $keep)
            ->update(['responsible_staff_id' => null]);
    }
};
