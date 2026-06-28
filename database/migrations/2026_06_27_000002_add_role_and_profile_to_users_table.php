<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('password');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete()->after('role');
            $table->string('phone')->nullable()->after('staff_id');
            $table->string('photo_path')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('photo_path');
            $table->boolean('is_locked')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('is_locked');
        });

        // Set existing admin user to admin role
        DB::table('users')->where('email', 'admin@ams.com')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'staff_id', 'phone', 'photo_path', 'is_active', 'is_locked', 'last_login_at']);
        });
    }
};
