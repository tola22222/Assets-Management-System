<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a program the two things the transfer workflow depends on: the school
 * it runs at, and the staff member accountable for it.
 *
 * Both columns are nullable at the database level purely because programs
 * already exist without them; ProgramController requires both on create and
 * update, so nothing new can be saved half-configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('description')
                ->constrained('locations')->nullOnDelete();
            // The person accountable for this program's assets at that site.
            // AssetTransferController resolves a site's confirmers through it.
            $table->foreignId('responsible_staff_id')->nullable()->after('location_id')
                ->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_staff_id');
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
