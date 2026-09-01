<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the long-dormant `roles` table into the backing store for Role &
 * Permission Management.
 *
 * The table shipped with the original build as `id, name, timestamps` and was
 * never used — `users.role` has always been a plain string. It is still empty
 * in production, so extending it in place is safe and avoids leaving a dead
 * table sitting next to a near-identical live one.
 *
 * `users.role` is NOT replaced. Every existing route guard, User::isStaff()
 * helper and frontend check still reads it, and the four built-in roles keep
 * working exactly as before. Custom roles layer on top: a user's effective
 * permissions are the union of the baseline their `users.role` grants and every
 * active custom role assigned to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->boolean('is_active')->default(true)->after('description');
            // System roles mirror the four built-in users.role values. They can
            // be edited but never deleted, or a user could end up with a
            // users.role string that no longer resolves to anything.
            $table->boolean('is_system')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'is_active', 'is_system']);
        });
    }
};
