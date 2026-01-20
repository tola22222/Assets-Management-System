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
        Schema::create('asset_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_return_id')->constrained();
            $table->foreignId('asset_id')->constrained();
            $table->enum('condition',['Good','Broken']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_return_items');
    }
};
