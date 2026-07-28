<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('asset_assignments')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('returned_by')->constrained('users')->cascadeOnDelete();
            $table->string('condition');
            $table->text('damage_notes')->nullable();
            $table->string('image_path')->nullable();
            $table->string('staff_signature')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->date('return_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_returns');
    }
};
