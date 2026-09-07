<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the receiving end of a transfer.
 *
 * Until now a transfer ended the moment OPM approved it: the asset's
 * location_id was rewritten on the spot and nobody at the destination ever
 * acknowledged the delivery, so the register could claim an asset was at a
 * school that had never seen it. The lifecycle is now
 * pending -> in_transit -> received, and the location only moves on `received`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->foreignId('received_by')->nullable()->after('approved_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by');
            // A return is a real transfer in its own right (school -> office),
            // pointed back at the outbound one it reverses so the round trip
            // stays legible and an asset can't be returned twice.
            $table->foreignId('parent_transfer_id')->nullable()->after('received_at')
                ->constrained('asset_transfers')->nullOnDelete();
        });

        // Every historic 'approved' row already had its asset relocated, which
        // is exactly what 'received' now means. Rewriting them keeps 'approved'
        // out of the lifecycle entirely rather than leaving a third terminal
        // state the UI would have to special-case forever.
        DB::table('asset_transfers')
            ->where('status', 'approved')
            ->update([
                'status' => 'received',
                'received_at' => DB::raw('updated_at'),
                'received_by' => DB::raw('approved_by'),
            ]);
    }

    public function down(): void
    {
        DB::table('asset_transfers')->where('status', 'received')->update(['status' => 'approved']);
        // in_transit has no pre-migration equivalent; the asset hasn't moved
        // yet, so 'pending' is the honest rollback target.
        DB::table('asset_transfers')->where('status', 'in_transit')->update(['status' => 'pending']);

        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_transfer_id');
            $table->dropColumn('received_at');
            $table->dropConstrainedForeignId('received_by');
        });
    }
};
