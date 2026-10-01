<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The exact tagged units a transfer moves.
 *
 * "Dell laptop × 10" used to be one asset_id plus a quantity, so nobody could
 * see WHICH ten laptops were coming. Now HR ticks the asset codes on the
 * Transfer form, one row here per unit, and the receiving staff member reviews
 * those codes before accepting. asset_transfers.asset_id stays as the first
 * unit (the model it is "for") and quantity equals the number of rows here.
 *
 * Every existing transfer is backfilled with its single asset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_transfer_id')->constrained('asset_transfers')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asset_transfer_id', 'asset_id']);
        });

        $now = now();
        DB::table('asset_transfers')->whereNotNull('asset_id')->orderBy('id')->select(['id', 'asset_id'])
            ->chunk(500, function ($transfers) use ($now) {
                DB::table('asset_transfer_items')->insert($transfers->map(fn ($t) => [
                    'asset_transfer_id' => $t->id,
                    'asset_id' => $t->asset_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfer_items');
    }
};
