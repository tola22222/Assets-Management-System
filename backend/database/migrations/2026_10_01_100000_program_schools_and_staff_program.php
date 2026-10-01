<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Program → Schools → Staff.
 *
 *  - A Program links to MANY schools/locations (location_program). The old
 *    single programs.location_id stays as the program's first school so
 *    anything still reading it keeps working; the pivot is the truth.
 *  - A Staff member belongs to ONE program (staff.program_id). Which schools
 *    they can see and manage is that program's schools — no school is picked
 *    for the staff member directly any more.
 *
 * Backfill: each program's existing school becomes its first linked school; a
 * program's Responsible Staff joins that program; any other staff member
 * whose site runs exactly one program joins it. Anyone left over keeps their
 * old site (User::siteLocationIds falls back to it) until HR picks a program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'location_id']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('location_id')
                ->constrained('programs')->nullOnDelete();
        });

        $now = now();
        $programs = DB::table('programs')->get(['id', 'location_id', 'responsible_staff_id']);

        foreach ($programs as $program) {
            if ($program->location_id) {
                DB::table('location_program')->insertOrIgnore([
                    'program_id' => $program->id, 'location_id' => $program->location_id,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            if ($program->responsible_staff_id) {
                DB::table('staff')->where('id', $program->responsible_staff_id)->update(['program_id' => $program->id]);
            }
        }

        // Other staff: join the program running at their site, when exactly one does.
        $byLocation = $programs->whereNotNull('location_id')->groupBy('location_id');
        foreach ($byLocation as $locationId => $atSite) {
            if ($atSite->count() === 1) {
                DB::table('staff')->whereNull('program_id')->where('location_id', $locationId)
                    ->update(['program_id' => $atSite->first()->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
        Schema::dropIfExists('location_program');
    }
};
