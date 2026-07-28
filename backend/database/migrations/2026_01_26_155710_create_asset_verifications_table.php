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
        Schema::create('asset_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('verified_by');
            $table->integer('quantity_verified');
            $table->enum('condition', ['good', 'fair', 'broken', 'lost']);
            $table->text('remark')->nullable();
            $table->date('verified_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_verifications');
    }
};
