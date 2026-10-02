<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings stored role grants in line with the permission catalogue
 * (PermissionRegistry::MODULE_ABILITIES as of this migration): drops grants
 * for modules that no longer have permissions — Assignments (merged into
 * Transfers), Dashboard, QR Scan, Global Search, Notifications — and for
 * abilities a module does not have (e.g. Asset Split create/update/delete,
 * Reports read). Every role is cleaned, built-in and custom; nothing that
 * still controls access is removed.
 *
 * Snapshot, not a reference to the registry, so later catalogue changes
 * never rewrite what this migration did.
 */
return new class extends Migration
{
    private const KEEP = [
        'locations' => ['view', 'create', 'update', 'delete', 'hide'],
        'programs' => ['view', 'create', 'update', 'delete', 'hide'],
        'staff' => ['view', 'create', 'update', 'delete', 'hide'],
        'suppliers' => ['view', 'create', 'update', 'delete', 'hide'],
        'categories' => ['view', 'create', 'update', 'delete', 'hide'],
        'assets' => ['view', 'create', 'update', 'delete', 'hide'],
        'stock-items' => ['view', 'hide'],
        'asset-transfers' => ['view', 'create', 'update', 'delete', 'hide'],
        'asset-verifications' => ['view', 'create', 'update', 'delete', 'hide'],
        'asset-disposals' => ['view', 'delete'],
        'reports' => ['view', 'hide'],
        'users' => ['view', 'read', 'create', 'update', 'delete', 'hide'],
        'roles' => ['view', 'read', 'create', 'update', 'delete'],
        'settings' => ['view', 'read', 'update', 'delete', 'hide'],
        'activity-logs' => ['view', 'delete', 'hide'],
    ];

    public function up(): void
    {
        DB::table('role_permissions')->whereNotIn('module', array_keys(self::KEEP))->delete();

        foreach (self::KEEP as $module => $abilities) {
            DB::table('role_permissions')->where('module', $module)->whereNotIn('ability', $abilities)->delete();
        }
    }

    public function down(): void
    {
        // Removed grants controlled nothing; there is nothing to restore.
    }
};
