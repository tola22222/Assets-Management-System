<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for a verification: the condition the asset had before it, and
 * how many units it took out of use (a unit verified broken or lost counts 1;
 * good / fair counts 0). The asset row and its code are never deleted — the
 * condition alone keeps a broken or lost unit out of available stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_verifications', function (Blueprint $table) {
            $table->string('previous_condition')->nullable()->after('condition');
            $table->unsignedInteger('quantity_affected')->default(0)->after('previous_condition');
        });
    }

    public function down(): void
    {
        Schema::table('asset_verifications', function (Blueprint $table) {
            $table->dropColumn(['previous_condition', 'quantity_affected']);
        });
    }
};
