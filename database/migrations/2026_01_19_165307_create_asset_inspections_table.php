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
        Schema::create('asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained();
            $table->enum('result',['Repairable','Dispose']);
            $table->text('note')->nullable();
            $table->foreignId('inspected_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_inspections');
    }
};
