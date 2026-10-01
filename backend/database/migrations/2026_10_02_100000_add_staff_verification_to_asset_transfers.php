<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff verification of an incoming transfer: who answered it, when, whether
 * they accepted or rejected it, and — on acceptance — the condition they
 * found the assets in (new / good / fair). The condition is also kept on the
 * assignment the acceptance creates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->foreignId('verified_by')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            // accepted | rejected
            $table->string('verification_status')->nullable()->after('verified_at');
            // new | good | fair
            $table->string('received_condition')->nullable()->after('verification_status');
        });

        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->string('condition')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropColumn('condition');
        });

        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verified_at', 'verification_status', 'received_condition']);
        });
    }
};
