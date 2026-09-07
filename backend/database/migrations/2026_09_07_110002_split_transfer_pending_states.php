<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separates the two things "pending" used to mean.
 *
 * A transfer now waits on one of two different parties, and conflating them
 * hid the important one: `pending_approval` waits on OPM (only ever reached by
 * a request someone other than OPM raised), while `pending` waits on the
 * DESTINATION site's responsible staff to accept or reject it. The short-lived
 * `in_transit` state was the latter under a name that implied the asset had
 * already left, so it becomes `pending`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table) {
            // Why the receiving site turned a delivery away.
            $table->text('rejection_reason')->nullable()->after('reason');
        });

        // Order matters: free up `pending` before reusing it.
        DB::table('asset_transfers')->where('status', 'pending')->update(['status' => 'pending_approval']);
        DB::table('asset_transfers')->where('status', 'in_transit')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        DB::table('asset_transfers')->where('status', 'pending')->update(['status' => 'in_transit']);
        DB::table('asset_transfers')->where('status', 'pending_approval')->update(['status' => 'pending']);

        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
