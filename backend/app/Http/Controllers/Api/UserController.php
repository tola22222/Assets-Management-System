<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::with(['staff', 'roles:id,name,slug,is_active'])->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:operations_hr_manager,staff,executive_director,finance_manager',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created user: '.$user->name,
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:operations_hr_manager,staff,executive_director,finance_manager',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        if ($guard = $this->lastAdministratorGuard($user, $validated['role'])) {
            return response()->json(['message' => $guard], 422);
        }

        $user->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated user: '.$user->name,
        ]);

        return response()->json($user->fresh()->load('roles:id,name,slug,is_active'));
    }

    public function lock(User $user)
    {
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'You cannot lock your own account.'], 422);
        }

        if (! $user->is_locked && ($guard = $this->lastAdministratorGuard($user, null))) {
            return response()->json(['message' => $guard], 422);
        }

        $user->update(['is_locked' => ! $user->is_locked]);

        if ($user->is_locked) {
            // Otherwise an already-issued bearer token keeps working until it expires
            // naturally — locking the account should end the session immediately.
            $user->tokens()->delete();
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => ($user->is_locked ? 'Locked' : 'Unlocked').' user: '.$user->name,
        ]);

        return response()->json($user->fresh());
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Password Reset',
            'description' => 'Reset password for user: '.$user->name,
        ]);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Replace the set of custom roles held by one user.
     *
     * `users.role` is untouched — that string is the account's baseline and is
     * changed through the normal update() path. These are additive roles.
     */
    public function syncRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => 'present|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        $before = $user->roles()->pluck('name')->sort()->implode(', ') ?: 'none';
        $user->roles()->sync($validated['roles']);
        $after = $user->roles()->pluck('name')->sort()->implode(', ') ?: 'none';

        if ($before !== $after) {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Update',
                'description' => "Changed roles for {$user->name}: {$before} → {$after}.",
            ]);
        }

        return response()->json($this->withRoles($user->fresh()));
    }

    /**
     * Everything this account may do, and where each grant came from — so an
     * admin can see why a user has an ability without reading four role
     * definitions side by side.
     */
    public function permissions(User $user)
    {
        $baseline = PermissionRegistry::baselineFor($user->role);

        $fromRoles = [];
        foreach ($user->activeRoles()->with('permissions')->get() as $role) {
            foreach ($role->grants() as $module => $abilities) {
                foreach ($abilities as $ability) {
                    $fromRoles[$module][$ability][] = $role->name;
                }
            }
        }

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'effective' => $user->effectivePermissions(),
            'baseline' => $baseline,
            'from_roles' => $fromRoles,
            'hidden_modules' => $user->hiddenModules(),
            'roles' => $user->roles()->get(['roles.id', 'name', 'is_active'])->all(),
        ]);
    }

    private function withRoles(User $user): array
    {
        return array_merge($user->toArray(), [
            'roles' => $user->roles()->get(['roles.id', 'name', 'slug', 'is_active'])->all(),
        ]);
    }

    /**
     * Last-administrator protection.
     *
     * Refuses any change that would leave the system with nobody able to
     * administer it — demoting, locking or deleting the final account that can
     * manage users and roles. Without this an admin can lock themselves out of
     * their own system with a single dropdown change and no way back in short
     * of editing the database by hand.
     *
     * $newRole is the role the account is being moved to, or null when the
     * account is being locked or removed outright.
     */
    private function lastAdministratorGuard(User $user, ?string $newRole): ?string
    {
        $isAdminNow = $user->hasPermission('users', 'update') && $user->hasPermission('roles', 'update');

        if (! $isAdminNow) {
            return null;   // Not an administrator, so nothing to protect.
        }

        // Would they still be one afterwards?
        if ($newRole !== null) {
            $after = PermissionRegistry::baselineFor($newRole);
            if (in_array('update', $after['users'] ?? [], true) && in_array('update', $after['roles'] ?? [], true)) {
                return null;
            }
        }

        $othersRemain = User::where('id', '!=', $user->id)
            ->where('is_active', true)
            ->where('is_locked', false)
            ->get()
            ->contains(fn (User $u) => $u->hasPermission('users', 'update') && $u->hasPermission('roles', 'update'));

        if ($othersRemain) {
            return null;
        }

        return "{$user->name} is the only account that can still manage users and roles. Give another active account administrator access first.";
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($guard = $this->lastAdministratorGuard($user, null)) {
            return response()->json(['message' => $guard], 422);
        }

        $name = $user->name;

        try {
            $user->delete();
        } catch (QueryException $e) {
            // A row somewhere still points at this account with a restrictive
            // foreign key. Without this the request died as a 500 carrying the
            // raw SQLSTATE text — database name, table names and the full DELETE
            // statement — straight to the browser.
            report($e);

            return response()->json([
                'message' => "{$name} still has records attached and cannot be deleted. Lock the account instead to block sign-in while keeping its history.",
            ], 422);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted user: '.$name,
        ]);

        return response()->json(['message' => 'User deleted.']);
    }
}
