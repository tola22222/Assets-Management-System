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
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->enum('assigned_to_type', ['staff', 'program']);
            $table->unsignedBigInteger('assigned_to_id');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->date('assigned_date');
            $table->date('due_date')->nullable(); // ADD THIS LINE
            $table->enum('status', ['assigned', 'active', 'returned', 'overdue'])->default('assigned'); // Updated status options
            $table->timestamps();
            
            // Add index for better performance
            $table->index(['assigned_to_type', 'assigned_to_id']);
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};