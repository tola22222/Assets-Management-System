<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_receives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('received_by')->constrained('users');
            $table->date('received_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_receives');
    }
};
