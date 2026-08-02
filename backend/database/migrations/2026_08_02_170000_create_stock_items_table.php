<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bulk consumables (toner, cables, paper, batteries, ...) — one row per
     * SKU, never one row per physical unit. `balance` is a cached value;
     * stock_transactions is the source of truth it's derived from (see
     * StockService).
     */
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('stock_code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit');
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('min_threshold', 12, 2)->nullable();
            $table->decimal('max_threshold', 12, 2)->nullable();
            $table->foreignId('location_id')->constrained('locations');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
