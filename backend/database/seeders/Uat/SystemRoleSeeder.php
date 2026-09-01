<?php

namespace Database\Seeders\Uat;

use App\Models\Role;
use App\Services\PermissionRegistry;
use Illuminate\Database\Seeder;

/**
 * Materialises the four built-in `users.role` values as Role rows so they
 * appear in the roles list with their real permission sets, and so an admin can
 * see what the defaults actually grant instead of having to read the code.
 *
 * They are flagged is_system: editable, never deletable or deactivatable,
 * because `users.role` still points at their slugs.
 *
 * Idempotent — keyed on slug, and existing permission grants are only written
 * when the role has none, so an admin's edits to a system role survive
 * re-seeding.
 */
class SystemRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionRegistry::SYSTEM_ROLES as $slug => [$name, $description]) {
            $role = Role::firstOrNew(['slug' => $slug]);
            $isNew = ! $role->exists;

            $role->fill([
                'name' => $role->name ?: $name,
                'description' => $role->description ?: $description,
                'is_active' => true,
                'is_system' => true,
            ])->save();

            if ($isNew || $role->permissions()->doesntExist()) {
                $role->syncGrants(PermissionRegistry::baselineFor($slug));
            }
        }
    }
}
