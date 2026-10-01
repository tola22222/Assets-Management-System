<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a transfer name who it is for — a staff member or a program at the
 * destination, picked the same way as on the Assignment form — and how many
 * units it takes from stock (quantity, default 1 for existing rows).
 *
 * Nothing is assigned when the transfer is raised. When the destination
 * accepts, confirmReceipt() moves the asset and writes the asset_assignments
 * row; assignment_id points at that row. All three are nullable: a plain
 * site-to-site transfer names nobody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->string('assigned_to_type')->nullable()->after('to_location_id');
            $table->unsignedBigInteger('assigned_to_id')->nullable()->after('assigned_to_type');
            // How many units of the asset's model this transfer takes out of
            // available stock — see App\Services\AssetStockService.
            $table->unsignedInteger('quantity')->default(1)->after('assigned_to_id');
            $table->foreignId('assignment_id')->nullable()->after('parent_transfer_id')
                ->constrained('asset_assignments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropColumn(['assigned_to_type', 'assigned_to_id', 'quantity']);
        });
    }
};
