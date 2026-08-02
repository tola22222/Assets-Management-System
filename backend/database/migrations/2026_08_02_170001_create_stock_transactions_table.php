<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log — a stock item's balance is always re-derivable by
     * replaying this table. Rows are never edited or deleted by normal
     * app flows (see StockService::receive()/issue()).
     */
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->decimal('quantity', 12, 2);
            $table->string('reason')->nullable();
            $table->date('transaction_date');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
