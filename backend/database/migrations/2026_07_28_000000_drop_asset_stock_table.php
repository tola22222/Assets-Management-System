<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the `asset_stock` table — the AssetStock model and its relations were
 * dead code (no controller, seeder, or query ever touched it; the "Receive
 * Assets" feature actually recorded receipts via AssetMovement, not this
 * table). Removed alongside the Receive Assets feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('asset_stock');
    }

    public function down(): void
    {
        Schema::create('asset_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('location_id')->constrained('locations');
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
    }
};
