<?php

namespace App\Services;

/**
 * The catalogue of what can be permissioned, and what the four built-in roles
 * are allowed to do out of the box.
 *
 * MODULES mirrors the modules that actually exist in this system — each entry
 * corresponds to a real route group in routes/api.php and a real page in the
 * SPA. Nothing here is aspirational; adding a module means adding the routes
 * too.
 *
 * BASELINE is the important part for safety. `users.role` remains the primary
 * authorisation input for every route guard that already exists, so the
 * baseline below is a faithful transcription of what those guards permit today.
 * Custom roles can only ADD to a user's baseline, never subtract — so turning
 * this feature on cannot take access away from anyone who has it now.
 */
class PermissionRegistry
{
    /**
     * View gates the module; the rest gate operations inside it.
     *
     * `hide` is the odd one out: it never affects backend authorisation. It is
     * a UI instruction meaning "hide the elements this module marks hideable
     * from holders of this role" — a way to declutter a screen for a role
     * without removing its access.
     */
    public const ABILITIES = ['view', 'create', 'read', 'update', 'delete', 'hide'];

    /** Abilities that are meaningless without `view`, per the spec. */
    public const REQUIRES_VIEW = ['create', 'read', 'update', 'delete'];

    /**
     * module key => [label, group]. The key matches the API path segment so a
     * route guard reads permission:assets,delete against /api/assets.
     */
    public const MODULES = [
        'dashboard' => ['Dashboard', 'Overview'],
        'assets' => ['Asset Register', 'Asset Management'],
        'stock-items' => ['Stock & Consumables', 'Asset Management'],
        'asset-assignments' => ['Assignments', 'Asset Management'],
        'asset-transfers' => ['Transfers', 'Asset Management'],
        'asset-verifications' => ['Verification & Counts', 'Asset Management'],
        'asset-disposals' => ['Disposals', 'Asset Management'],
        'staff' => ['Staff Directory', 'People & Programs'],
        'programs' => ['Programs', 'People & Programs'],
        'categories' => ['Categories', 'System Setup'],
        'locations' => ['Locations', 'System Setup'],
        'suppliers' => ['Suppliers', 'System Setup'],
        'reports' => ['Reports', 'Insight'],
        'qr-scan' => ['QR Scan', 'Insight'],
        'search' => ['Global Search', 'Insight'],
        'notifications' => ['Notifications', 'Insight'],
        'users' => ['User Management', 'Administration'],
        'roles' => ['Roles & Permissions', 'Administration'],
        'settings' => ['System Settings', 'Administration'],
        'activity-logs' => ['Activity Log', 'Administration'],
    ];

    /** Shorthand used to keep the baseline table below readable. */
    private const FULL = ['view', 'create', 'read', 'update', 'delete'];

    private const VIEW_ONLY = ['view', 'read'];

    /**
     * What each built-in `users.role` already grants, transcribed from the
     * route guards in routes/api.php and the in-controller abort_unless checks.
     */
    public const BASELINE = [
        'operations_hr_manager' => [
            'dashboard' => self::VIEW_ONLY,
            'assets' => self::FULL,
            'stock-items' => ['view', 'read', 'update', 'delete'],
            'asset-assignments' => self::FULL,
            'asset-transfers' => self::FULL,
            'asset-verifications' => self::FULL,
            'asset-disposals' => ['view', 'create', 'read', 'delete'],
            'staff' => self::FULL,
            'programs' => self::FULL,
            'categories' => self::FULL,
            'locations' => self::FULL,
            'suppliers' => self::FULL,
            'reports' => self::VIEW_ONLY,
            'qr-scan' => ['view', 'create', 'read'],
            'search' => self::VIEW_ONLY,
            'notifications' => ['view', 'read', 'update'],
            'users' => self::FULL,
            'roles' => self::FULL,
            'settings' => ['view', 'read', 'update'],
            'activity-logs' => ['view', 'read', 'delete'],
        ],
        'finance_manager' => [
            'dashboard' => self::VIEW_ONLY,
            'assets' => ['view', 'read', 'update'],
            'stock-items' => ['view', 'read', 'update', 'delete'],
            'asset-assignments' => self::FULL,
            'asset-transfers' => ['view', 'create', 'read', 'delete'],
            'asset-verifications' => ['view', 'create', 'read'],
            'asset-disposals' => ['view', 'create', 'read', 'delete'],
            'staff' => self::VIEW_ONLY,
            'programs' => self::VIEW_ONLY,
            'categories' => self::VIEW_ONLY,
            'locations' => self::VIEW_ONLY,
            'suppliers' => self::FULL,
            'reports' => self::VIEW_ONLY,
            'qr-scan' => ['view', 'create', 'read'],
            'search' => self::VIEW_ONLY,
            'notifications' => ['view', 'read', 'update'],
        ],
        'executive_director' => [
            'dashboard' => self::VIEW_ONLY,
            'assets' => self::VIEW_ONLY,
            'stock-items' => self::VIEW_ONLY,
            'asset-assignments' => self::VIEW_ONLY,
            'asset-transfers' => ['view', 'create', 'read', 'delete'],
            'asset-verifications' => self::VIEW_ONLY,
            // The manual makes the ED the sole approver of write-offs.
            'asset-disposals' => ['view', 'create', 'read', 'update', 'delete'],
            'staff' => self::VIEW_ONLY,
            'programs' => self::VIEW_ONLY,
            'categories' => self::VIEW_ONLY,
            'locations' => self::VIEW_ONLY,
            'suppliers' => self::VIEW_ONLY,
            'reports' => self::VIEW_ONLY,
            'qr-scan' => ['view', 'create', 'read'],
            'search' => self::VIEW_ONLY,
            'notifications' => ['view', 'read', 'update'],
        ],
        'staff' => [
            'dashboard' => self::VIEW_ONLY,
            'assets' => self::VIEW_ONLY,
            'stock-items' => self::VIEW_ONLY,
            'asset-assignments' => self::VIEW_ONLY,
            'asset-transfers' => ['view', 'create', 'read', 'delete'],
            'asset-verifications' => self::VIEW_ONLY,
            'asset-disposals' => ['view', 'create', 'read', 'delete'],
            'staff' => self::VIEW_ONLY,
            'programs' => self::VIEW_ONLY,
            'categories' => self::VIEW_ONLY,
            'locations' => self::VIEW_ONLY,
            'suppliers' => self::VIEW_ONLY,
            'qr-scan' => ['view', 'create', 'read'],
            'search' => self::VIEW_ONLY,
            'notifications' => ['view', 'read', 'update'],
        ],
    ];

    /** Built-in roles get a Role row too, so they show up in the roles list. */
    public const SYSTEM_ROLES = [
        'operations_hr_manager' => ['Operations & HR Manager', 'Primary administrator. Full access to the register, workflows, users and settings.'],
        'finance_manager' => ['Finance Manager', 'Own-scope edit rights across assets, suppliers and assignments; verifies counts.'],
        'executive_director' => ['Executive Director', 'Reads the register and reports; sole approver of asset disposals.'],
        'staff' => ['Staff', 'Site-scoped. Looks up assets at their own site and flags damage or loss.'],
    ];

    public static function moduleKeys(): array
    {
        return array_keys(self::MODULES);
    }

    public static function isModule(string $module): bool
    {
        return array_key_exists($module, self::MODULES);
    }

    public static function isAbility(string $ability): bool
    {
        return in_array($ability, self::ABILITIES, true);
    }

    /** The module catalogue in the shape the permission matrix renders. */
    public static function catalogue(): array
    {
        $out = [];

        foreach (self::MODULES as $key => [$label, $group]) {
            $out[] = ['key' => $key, 'label' => $label, 'group' => $group];
        }

        return $out;
    }

    /**
     * Drops abilities that need `view` when `view` is absent, and drops
     * anything that is not a real module/ability. Returns
     * ['module' => ['view', 'create', ...]] with every list unique and ordered.
     */
    public static function normalise(array $grants): array
    {
        $clean = [];

        foreach ($grants as $module => $abilities) {
            if (! self::isModule($module) || ! is_array($abilities)) {
                continue;
            }

            $abilities = array_values(array_unique(array_filter(
                $abilities,
                fn ($a) => is_string($a) && self::isAbility($a)
            )));

            if ($abilities === []) {
                continue;
            }

            // Create/Read/Update/Delete are meaningless without access to the
            // module, so granting one implies View rather than being rejected.
            if (array_intersect($abilities, self::REQUIRES_VIEW) !== [] && ! in_array('view', $abilities, true)) {
                $abilities[] = 'view';
            }

            $clean[$module] = array_values(array_intersect(self::ABILITIES, $abilities));
        }

        return $clean;
    }

    /** The permission set a bare `users.role` string grants on its own. */
    public static function baselineFor(?string $role): array
    {
        return self::BASELINE[$role] ?? [];
    }
}
