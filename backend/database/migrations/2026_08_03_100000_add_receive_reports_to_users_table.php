<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personal opt-out for the scheduled asset-count report email. Only ever
     * consulted for role=staff — Finance Manager/Executive Director/OPM are
     * unconditional recipients regardless of this column (see
     * SendScheduledAssetReport). Defaults true so a staff member is included
     * as soon as an admin turns on the org-wide "include staff" setting,
     * without needing to separately opt in themselves.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('receive_reports')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('receive_reports');
        });
    }
};
