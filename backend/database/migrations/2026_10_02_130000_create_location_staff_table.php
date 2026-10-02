<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A staff member can work at several locations/schools — all of them in the
 * same program, which is where the staff member's program comes from
 * (Program → Location → Staff). staff.location_id stays as their first
 * location for anything that reads a single one.
 *
 * Existing staff get their current location_id as their one location.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'location_id']);
        });

        $now = now();
        DB::table('staff')->whereNotNull('location_id')->orderBy('id')->each(function ($staff) use ($now) {
            DB::table('location_staff')->insert([
                'staff_id' => $staff->id,
                'location_id' => $staff->location_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_staff');
    }
};
