<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Role & Permission Management, surfaced in the SPA under /app/users.
 *
 * Every mutation writes an ActivityLog row through the same audit trail the
 * rest of the app uses, so a permission change is as traceable as an asset
 * edit.
 */
class RoleController extends Controller
{
    /** The module/ability catalogue the permission matrix is built from. */
    public function catalogue()
    {
        return response()->json([
            'modules' => PermissionRegistry::catalogue(),
            'abilities' => PermissionRegistry::ABILITIES,
            'requires_view' => PermissionRegistry::REQUIRES_VIEW,
        ]);
    }

    public function index()
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->present($role));

        return response()->json($roles);
    }

    public function show(Role $role)
    {
        return response()->json($this->present($role->load('permissions')->loadCount('users')));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Role::makeSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_system' => false,
        ]);

        $role->syncGrants($request->input('permissions', []));

        $this->log('Create', "Created role \"{$role->name}\" with ".$this->countGrants($role).' permission(s).');

        return response()->json($this->present($role->load('permissions')->loadCount('users')), 201);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ]);

        // A system role is the target of a users.role string, so it must never
        // become inactive — its holders would silently lose their baseline.
        $isActive = $role->is_system ? true : $request->boolean('is_active', $role->is_active);

        $before = $this->countGrants($role);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $isActive,
        ]);

        if ($request->has('permissions')) {
            if ($guard = $this->wouldStrandAdministrators($role, $request->input('permissions', []))) {
                return response()->json(['message' => $guard], 422);
            }

            $role->syncGrants($request->input('permissions', []));
        }

        $after = $this->countGrants($role);
        $this->log('Update', "Updated role \"{$role->name}\" (permissions {$before} → {$after}).");

        return response()->json($this->present($role->load('permissions')->loadCount('users')));
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return response()->json([
                'message' => "\"{$role->name}\" is a built-in role and cannot be deleted. Deactivate the accounts that use it instead.",
            ], 422);
        }

        $name = $role->name;
        $holders = $role->users()->count();

        // Detaching first keeps the pivot tidy even where the FK would cascade.
        $role->users()->detach();
        $role->delete();

        $this->log('Delete', "Deleted role \"{$name}\"".($holders ? " (was assigned to {$holders} user(s))" : '').'.');

        return response()->json(['message' => 'Role deleted.']);
    }

    /** Enable / disable without deleting. Inactive roles grant nothing. */
    public function toggle(Role $role)
    {
        if ($role->is_system) {
            return response()->json([
                'message' => "\"{$role->name}\" is a built-in role and cannot be deactivated.",
            ], 422);
        }

        if ($role->is_active && ($guard = $this->wouldStrandAdministrators($role, []))) {
            return response()->json(['message' => $guard], 422);
        }

        $role->update(['is_active' => ! $role->is_active]);

        $this->log('Update', ($role->is_active ? 'Activated' : 'Deactivated')." role \"{$role->name}\".");

        return response()->json($this->present($role->load('permissions')->loadCount('users')));
    }

    /** Copy a role's permission set into a new, unassigned role. */
    public function duplicate(Role $role)
    {
        $name = $role->name.' (copy)';
        $n = 2;
        while (Role::where('name', $name)->exists()) {
            $name = $role->name." (copy {$n})";
            $n++;
        }

        $copy = Role::create([
            'name' => $name,
            'slug' => Role::makeSlug($name),
            'description' => $role->description,
            // A copy starts switched off so it cannot widen anyone's access
            // before someone has reviewed it.
            'is_active' => false,
            'is_system' => false,
        ]);

        $copy->syncGrants($role->grants());

        $this->log('Create', "Duplicated role \"{$role->name}\" as \"{$copy->name}\" (created inactive).");

        return response()->json($this->present($copy->load('permissions')->loadCount('users')), 201);
    }

    // -----------------------------------------------------------------

    private function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_active' => $role->is_active,
            'is_system' => $role->is_system,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'permissions' => $role->grants(),
            'permission_count' => $this->countGrants($role),
        ];
    }

    private function countGrants(Role $role): int
    {
        return collect($role->grants())->flatten()->count();
    }

    /**
     * Last-administrator protection.
     *
     * Editing a role's permissions must not be able to leave the system with
     * nobody who can manage roles and users. Built-in Operations & HR Manager
     * accounts always retain that baseline, so this only bites when the last
     * such account is gone and administration depends on a custom role.
     *
     * Returns an error message when the change would strand the system, or
     * null when it is safe.
     */
    private function wouldStrandAdministrators(Role $role, array $proposedGrants): ?string
    {
        $baselineAdmins = User::where('role', 'operations_hr_manager')
            ->where('is_active', true)
            ->where('is_locked', false)
            ->count();

        if ($baselineAdmins > 0) {
            return null;
        }

        $proposed = PermissionRegistry::normalise($proposedGrants);
        $keepsAdmin = in_array('update', $proposed['roles'] ?? [], true)
            && in_array('update', $proposed['users'] ?? [], true);

        if ($keepsAdmin) {
            return null;
        }

        // Is anyone else still able to administer?
        $othersCanAdminister = User::where('is_active', true)
            ->where('is_locked', false)
            ->whereHas('roles', function ($q) use ($role) {
                $q->where('roles.id', '!=', $role->id)->where('roles.is_active', true);
            })
            ->get()
            ->contains(fn (User $u) => $u->hasPermission('roles', 'update') && $u->hasPermission('users', 'update'));

        if ($othersCanAdminister) {
            return null;
        }

        return 'This change would leave nobody able to manage roles and users. Give another active role permission to update Roles and Users first.';
    }

    private function log(string $action, string $description): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
        ]);
    }
}
