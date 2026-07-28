<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The role list now follows the Asset Checking & Counting Manual exactly:
     * Operations & HR Manager, Finance Manager, Executive Director, Staff.
     * The former "admin" role covered the same full-access responsibilities
     * the manual assigns to Operations & HR Manager (lead the process,
     * assign users, tagging and documentation), so existing admins are
     * renamed rather than losing access.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'operations_hr_manager']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'operations_hr_manager')->update(['role' => 'admin']);
    }
};
