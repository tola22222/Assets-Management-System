<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per granted ability, keyed Role -> Module -> Permission.
 *
 * Stored as rows rather than a JSON blob so a permission can be queried
 * directly ("who can delete assets?") and so the unique constraint stops the
 * same grant being recorded twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('module', 64);
            $table->string('ability', 16);
            $table->timestamps();

            $table->unique(['role_id', 'module', 'ability']);
            $table->index(['module', 'ability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
