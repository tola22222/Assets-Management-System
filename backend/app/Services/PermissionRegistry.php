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
     * route guard reads permission:assets,delete against /api/assets. Labels
     * and groups follow the sidebar.
     *
     * Only modules whose permissions actually control something are listed.
     * Dropped as obsolete: Assignments (merged into Transfers — naming a
     * recipient is part of creating a transfer), and Dashboard, QR Scan,
     * Global Search and Notifications (open to every signed-in user; no guard
     * or screen ever read their permissions).
     */
    public const MODULES = [
        'locations' => ['Locations', 'People & Programs'],
        'programs' => ['Programs', 'People & Programs'],
        'staff' => ['Staff Directory', 'People & Programs'],
        'suppliers' => ['Suppliers', 'People & Programs'],
        'categories' => ['Categories', 'Asset Management'],
        'assets' => ['Add Asset', 'Asset Management'],
        'stock-items' => ['Asset Split', 'Asset Management'],
        'asset-transfers' => ['Transfers', 'Asset Management'],
        'asset-verifications' => ['Verification', 'Asset Management'],
        'asset-disposals' => ['Disposals', 'Asset Management'],
        'reports' => ['Reports', 'Insight'],
        'users' => ['User Management', 'Setting'],
        'roles' => ['Roles & Permissions', 'Setting'],
        'settings' => ['System Settings', 'Setting'],
        'activity-logs' => ['Activity Logs', 'Setting'],
    ];

    /**
     * The abilities each module really has — every one maps to a live route
     * guard, an in-controller check, or (view / hide) the sidebar and search.
     * The permission page shows only these; anything else is dropped.
     *
     *   view    the module's sidebar link and list
     *   create / update / delete   its New / Edit (and workflow) / Delete actions
     *   read    a single-record endpoint that is guarded (an account's
     *           permissions, a role's detail, a settings backup download)
     *   hide    the sidebar / search declutter flag (modules with a link only)
     */
    public const MODULE_ABILITIES = [
        'locations' => ['view', 'create', 'update', 'delete', 'hide'],
        'programs' => ['view', 'create', 'update', 'delete', 'hide'],
        'staff' => ['view', 'create', 'update', 'delete', 'hide'],
        'suppliers' => ['view', 'create', 'update', 'delete', 'hide'],
        'categories' => ['view', 'create', 'update', 'delete', 'hide'],
        // create: add, bulk import. update: edit, regenerate QR.
        'assets' => ['view', 'create', 'update', 'delete', 'hide'],
        // Read-only page: no issue / receive / delete actions any more.
        'stock-items' => ['view', 'hide'],
        // create: New Transfer (incl. naming a recipient); update: approve,
        // reject, return, Edit > Assignment; delete: delete a request.
        'asset-transfers' => ['view', 'create', 'update', 'delete', 'hide'],
        // update: mark complete.
        'asset-verifications' => ['view', 'create', 'update', 'delete', 'hide'],
        // Approving stays with the Executive Director alone
        // (canApproveDisposal), and anyone may raise a request, so Delete is
        // the one grantable action.
        'asset-disposals' => ['view', 'delete'],
        'reports' => ['view', 'hide'],
        // read: an account's effective permissions; update: edit, lock,
        // reset password, assign roles.
        'users' => ['view', 'read', 'create', 'update', 'delete', 'hide'],
        // A tab of User Management, not a sidebar link — no hide.
        'roles' => ['view', 'read', 'create', 'update', 'delete'],
        // read: download a backup; update: save, back up, restore, test mail;
        // delete: delete a backup.
        'settings' => ['view', 'read', 'update', 'delete', 'hide'],
        'activity-logs' => ['view', 'delete', 'hide'],
    ];

    /** Shorthand used to keep the baseline table below readable. */
    private const FULL = ['view', 'create', 'read', 'update', 'delete'];

    private const VIEW_ONLY = ['view', 'read'];

    /**
     * What each built-in `users.role` already grants, transcribed from the
     * route guards in routes/api.php and the in-controller abort_unless checks.
     * normalise() trims each list to MODULE_ABILITIES.
     */
    public const BASELINE = [
        'operations_hr_manager' => [
            'assets' => self::FULL,
            'stock-items' => ['view'],
            'asset-transfers' => self::FULL,
            'asset-verifications' => self::FULL,
            'asset-disposals' => ['view', 'delete'],
            'staff' => self::FULL,
            'programs' => self::FULL,
            'categories' => self::FULL,
            'locations' => self::FULL,
            'suppliers' => self::FULL,
            'reports' => ['view'],
            'users' => self::FULL,
            'roles' => self::FULL,
            'settings' => ['view', 'read', 'update', 'delete'],
            'activity-logs' => ['view', 'delete'],
        ],
        // The Accountant has HR's access everywhere except Administration,
        // where they get System Settings only — narrowed to its Appearance tab
        // inside SettingController. No User Management, Roles & Permissions or
        // Activity Log.
        'finance_manager' => [
            'assets' => self::FULL,
            'stock-items' => ['view'],
            'asset-transfers' => self::FULL,
            'asset-verifications' => self::FULL,
            'asset-disposals' => ['view', 'delete'],
            'staff' => self::FULL,
            'programs' => self::FULL,
            'categories' => self::FULL,
            'locations' => self::FULL,
            'suppliers' => self::FULL,
            'reports' => ['view'],
            'settings' => ['view', 'read', 'update'],
        ],
        'executive_director' => [
            'assets' => self::VIEW_ONLY,
            'stock-items' => ['view'],
            // Raises transfer requests, and approves / rejects them.
            'asset-transfers' => ['view', 'create', 'update', 'delete'],
            'asset-verifications' => self::VIEW_ONLY,
            // The manual makes the ED the sole approver of write-offs (a rule,
            // not a grantable ability — see MODULE_ABILITIES).
            'asset-disposals' => ['view', 'delete'],
            'staff' => self::VIEW_ONLY,
            'programs' => self::VIEW_ONLY,
            'categories' => self::VIEW_ONLY,
            'locations' => self::VIEW_ONLY,
            'suppliers' => self::VIEW_ONLY,
            'reports' => ['view'],
        ],
        'staff' => [
            'assets' => self::VIEW_ONLY,
            'stock-items' => ['view'],
            // Staff answer transfers (accept / reject); they never raise one —
            // only a custom role that grants 'create' lets a staff login send.
            'asset-transfers' => ['view', 'read', 'delete'],
            'asset-verifications' => self::VIEW_ONLY,
            'asset-disposals' => ['view', 'delete'],
            'staff' => self::VIEW_ONLY,
            'programs' => self::VIEW_ONLY,
            'categories' => self::VIEW_ONLY,
            'locations' => self::VIEW_ONLY,
            // Suppliers are hidden from staff by default; a custom role can grant them.
            // Administration > Appearance only: the page applies their theme
            // colour and language to their own browser. The /settings API itself
            // stays refused to staff (role: guard), so nothing org-wide changes.
            'settings' => ['view'],
        ],
    ];

    /** Built-in roles get a Role row too, so they show up in the roles list. */
    public const SYSTEM_ROLES = [
        'operations_hr_manager' => ['Operations & HR Manager', 'Primary administrator. Full access to the register, workflows, users and settings.'],
        'finance_manager' => ['Finance Manager', 'The Accountant: same access as the Operations & HR Manager, except Administration — System Settings > Appearance only.'],
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

    /** The abilities a module actually has (MODULE_ABILITIES), in ABILITIES order. */
    public static function abilitiesFor(string $module): array
    {
        return array_values(array_intersect(self::ABILITIES, self::MODULE_ABILITIES[$module] ?? []));
    }

    /** The module catalogue in the shape the permission matrix renders. */
    public static function catalogue(): array
    {
        $out = [];

        foreach (self::MODULES as $key => [$label, $group]) {
            $out[] = ['key' => $key, 'label' => $label, 'group' => $group, 'abilities' => self::abilitiesFor($key)];
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
                fn ($a) => is_string($a) && in_array($a, self::abilitiesFor($module), true)
            )));

            if ($abilities === []) {
                continue;
            }

            // Create/Read/Update/Delete are meaningless without access to the
            // module, so granting one implies View rather than being rejected.
            if (array_intersect($abilities, self::REQUIRES_VIEW) !== [] && ! in_array('view', $abilities, true)) {
                $abilities[] = 'view';
            }

            $clean[$module] = array_values(array_intersect(self::abilitiesFor($module), $abilities));
        }

        return $clean;
    }

    /** The permission set a bare `users.role` string grants on its own. */
    public static function baselineFor(?string $role): array
    {
        return self::BASELINE[$role] ?? [];
    }

    /**
     * The [module, ability] a request needs, read off the route — so a
     * role:-guarded route can also let in a custom role that grants it.
     *
     *   module   the first path segment after api/ (it matches the MODULES
     *            keys by design); null when it is not a permissionable module
     *   ability  GET on a collection = view, GET on a record = read;
     *            POST to the collection (or its import) = create, any other
     *            POST (approve, lock, issue, …) = update; PUT/PATCH = update;
     *            DELETE = delete. Reports are read-only, so always view.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function abilityForRoute(string $method, string $uri, array $parameters = []): ?array
    {
        $segments = array_values(array_filter(explode('/', preg_replace('#^api/#', '', trim($uri, '/')))));
        $module = $segments[0] ?? null;

        if ($module === null || ! self::isModule($module)) {
            return null;
        }

        if ($module === 'reports') {
            return [$module, 'view'];
        }

        $method = strtoupper($method);
        $onRecord = $parameters !== [];

        $ability = match (true) {
            in_array($method, ['GET', 'HEAD'], true) => $onRecord ? 'read' : 'view',
            // Saving Settings posts to the collection, but it is an edit.
            $method === 'POST' && $module === 'settings' => 'update',
            $method === 'POST' && in_array(end($segments), ['import', 'duplicate'], true) => 'create',
            $method === 'POST' => count($segments) === 1 ? 'create' : 'update',
            in_array($method, ['PUT', 'PATCH'], true) => 'update',
            $method === 'DELETE' => 'delete',
            default => null,
        };

        return $ability ? [$module, $ability] : null;
    }
}
