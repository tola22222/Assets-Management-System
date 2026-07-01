<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE asset_verifications MODIFY verified_by VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE asset_verifications MODIFY verified_by VARCHAR(255) NOT NULL');
    }
};
