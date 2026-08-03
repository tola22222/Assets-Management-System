<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs environments where 2026_06_01_161538_add_due_date_to_asset_assignments_table
 * already ran (and is recorded as such) but its up() body was empty, so
 * `due_date` was never actually added — Laravel won't re-run a migration
 * just because its file content changed, so this must be a new migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asset_assignments', 'due_date')) {
            Schema::table('asset_assignments', function (Blueprint $table) {
                $table->date('due_date')->nullable()->after('assigned_date');
                $table->index('due_date');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
