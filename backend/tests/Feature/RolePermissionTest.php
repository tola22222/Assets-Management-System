<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role & Permission Management.
 *
 * The load-bearing property is that custom roles are ADDITIVE. `users.role`
 * still drives every pre-existing route guard, so a custom role may widen what
 * an account can do but must never narrow it — otherwise switching this feature
 * on would silently revoke access people already rely on.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return $this->user('operations_hr_manager', 'opm@example.test');
    }

    private function role(array $grants, bool $active = true): Role
    {
        $role = Role::create([
            'name' => 'Count Supervisor',
            'slug' => 'count-supervisor',
            'is_active' => $active,
            'is_system' => false,
        ]);
        $role->syncGrants($grants);

        return $role;
    }

    // ---- effective permissions ----------------------------------------

    public function test_a_users_role_string_still_grants_its_baseline_with_no_custom_roles(): void
    {
        $staff = $this->user('staff', 'staff@example.test');

        $this->assertTrue($staff->hasPermission('assets', 'view'));
        $this->assertFalse($staff->hasPermission('assets', 'delete'));
        $this->assertFalse($staff->hasPermission('users', 'view'));
    }

    public function test_an_active_custom_role_adds_permissions_on_top_of_the_baseline(): void
    {
        $staff = $this->user('staff', 'staff@example.test');
        $staff->roles()->attach($this->role(['asset-verifications' => ['view', 'create', 'update']]));

        $this->assertTrue($staff->hasPermission('asset-verifications', 'update'));
        // The baseline survives untouched.
        $this->assertTrue($staff->hasPermission('assets', 'view'));
    }

    public function test_an_inactive_role_grants_nothing(): void
    {
        $staff = $this->user('staff', 'staff@example.test');
        $staff->roles()->attach($this->role(['reports' => ['view', 'read']], active: false));

        $this->assertFalse($staff->hasPermission('reports', 'view'));
    }

    public function test_permissions_from_several_roles_combine(): void
    {
        $staff = $this->user('staff', 'staff@example.test');

        $a = Role::create(['name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $a->syncGrants(['reports' => ['view', 'read']]);
        $b = Role::create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
        $b->syncGrants(['assets' => ['view', 'update']]);

        $staff->roles()->attach([$a->id, $b->id]);

        $this->assertTrue($staff->hasPermission('reports', 'read'));
        $this->assertTrue($staff->hasPermission('assets', 'update'));
    }

    public function test_a_custom_role_cannot_take_baseline_access_away(): void
    {
        $staff = $this->user('staff', 'staff@example.test');
        // A role granting nothing at all must not subtract anything.
        $staff->roles()->attach($this->role([]));

        $this->assertTrue($staff->hasPermission('assets', 'view'));
    }

    // ---- validation ---------------------------------------------------

    public function test_create_read_update_and_delete_each_imply_view(): void
    {
        foreach (PermissionRegistry::REQUIRES_VIEW as $ability) {
            $normalised = PermissionRegistry::normalise(['assets' => [$ability]]);
            $this->assertContains('view', $normalised['assets'], "{$ability} should imply view");
        }
    }

    public function test_unknown_modules_and_abilities_are_discarded(): void
    {
        $clean = PermissionRegistry::normalise([
            'assets' => ['view', 'teleport'],
            'not-a-module' => ['view'],
        ]);

        $this->assertSame(['assets' => ['view']], $clean);
    }

    // ---- API authorisation --------------------------------------------

    public function test_only_an_operations_manager_can_reach_role_management(): void
    {
        foreach (['finance_manager', 'executive_director', 'staff'] as $role) {
            $actor = $this->user($role, "{$role}@example.test");

            $this->actingAs($actor)->getJson('/api/roles')->assertForbidden();
            $this->actingAs($actor)->postJson('/api/roles', ['name' => 'X'])->assertForbidden();
        }

        $this->actingAs($this->admin())->getJson('/api/roles')->assertOk();
    }

    public function test_the_permission_middleware_returns_403_not_500(): void
    {
        $staff = $this->user('staff', 'staff@example.test');
        $admin = $this->admin();

        $this->actingAs($staff)
            ->getJson("/api/users/{$admin->id}/permissions")
            ->assertForbidden();
    }

    public function test_every_role_can_read_its_own_permissions(): void
    {
        foreach (['operations_hr_manager', 'finance_manager', 'executive_director', 'staff'] as $role) {
            $this->actingAs($this->user($role, "{$role}@example.test"))
                ->getJson('/api/me/permissions')
                ->assertOk()
                ->assertJsonStructure(['role', 'permissions', 'hidden_modules', 'roles']);
        }
    }

    // ---- built-in role protection --------------------------------------

    public function test_a_built_in_role_cannot_be_deleted_or_deactivated(): void
    {
        $admin = $this->admin();
        $system = Role::create(['name' => 'Staff', 'slug' => 'staff', 'is_active' => true, 'is_system' => true]);

        $this->actingAs($admin)->deleteJson("/api/roles/{$system->id}")->assertStatus(422);
        $this->actingAs($admin)->postJson("/api/roles/{$system->id}/toggle")->assertStatus(422);

        $this->assertDatabaseHas('roles', ['id' => $system->id, 'is_active' => true]);
    }

    public function test_duplicating_a_role_copies_its_permissions_but_leaves_it_inactive(): void
    {
        $admin = $this->admin();
        $role = $this->role(['reports' => ['view', 'read']]);

        $response = $this->actingAs($admin)
            ->postJson("/api/roles/{$role->id}/duplicate")
            ->assertCreated();

        $copy = Role::find($response->json('id'));
        $this->assertFalse($copy->is_active, 'a copy must not grant anything until reviewed');
        $this->assertSame(['reports' => ['view', 'read']], $copy->grants());
    }

    // ---- last administrator --------------------------------------------

    public function test_the_final_administrator_cannot_be_demoted(): void
    {
        $admin = $this->admin();
        $other = $this->user('staff', 'staff@example.test');

        $this->actingAs($other)   // act as someone else so the self-guard is not what fires
            ->putJson("/api/users/{$admin->id}", [
                'name' => $admin->name, 'email' => $admin->email, 'role' => 'staff',
            ])
            ->assertForbidden();  // staff cannot reach /users at all

        // With two admins, demoting one is fine.
        $second = $this->user('operations_hr_manager', 'opm2@example.test');
        $this->actingAs($second)
            ->putJson("/api/users/{$admin->id}", [
                'name' => $admin->name, 'email' => $admin->email, 'role' => 'staff',
            ])
            ->assertOk();

        // Now $second is the last one, and demoting it is refused.
        $third = $this->user('operations_hr_manager', 'opm3@example.test');
        $this->actingAs($third)
            ->putJson("/api/users/{$second->id}", [
                'name' => $second->name, 'email' => $second->email, 'role' => 'staff',
            ])
            ->assertOk();

        $this->actingAs($third)
            ->putJson("/api/users/{$third->id}", [
                'name' => $third->name, 'email' => $third->email, 'role' => 'staff',
            ])
            ->assertStatus(422);
    }

    // ---- audit ----------------------------------------------------------

    public function test_role_changes_are_written_to_the_activity_log(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/roles', [
            'name' => 'Auditor',
            'permissions' => ['reports' => ['view', 'read']],
        ])->assertCreated();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'Create',
        ]);

        $this->assertTrue(
            \App\Models\ActivityLog::where('description', 'like', '%Created role "Auditor"%')->exists()
        );
    }

    public function test_assigning_roles_to_a_user_is_audited(): void
    {
        $admin = $this->admin();
        $staff = $this->user('staff', 'staff@example.test');
        $role = $this->role(['reports' => ['view']]);

        $this->actingAs($admin)
            ->postJson("/api/users/{$staff->id}/roles", ['roles' => [$role->id]])
            ->assertOk();

        $this->assertTrue(
            \App\Models\ActivityLog::where('description', 'like', "%Changed roles for {$staff->name}%")->exists()
        );
    }
}
