<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-code outcome of a transfer: when the receiver accepts, they tick which
 * asset codes actually arrived. Ticked lines become `accepted` (those units
 * move); unticked ones become `declined` (they stay where they were). Null
 * means not decided yet — the transfer is still open, or was rejected whole.
 *
 * Lines of transfers already accepted before this existed are all accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_transfer_items', function (Blueprint $table) {
            $table->string('status')->nullable()->after('asset_id');
        });

        DB::table('asset_transfer_items')
            ->whereIn('asset_transfer_id', DB::table('asset_transfers')->where('status', 'received')->select('id'))
            ->update(['status' => 'accepted']);
    }

    public function down(): void
    {
        Schema::table('asset_transfer_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
