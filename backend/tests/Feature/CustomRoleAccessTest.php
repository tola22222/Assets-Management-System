<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a custom role ticks on the Roles & Permissions screen must actually
 * work: the role: route guards and the in-controller role checks let a
 * custom-role grant through on top of the base role — and nothing else.
 */
class CustomRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staffWith(array $grants, bool $active = true): User
    {
        $role = Role::create(['name' => 'Custom', 'slug' => 'custom-'.uniqid(), 'is_active' => $active, 'is_system' => false]);
        $role->syncGrants($grants);

        $user = User::factory()->create(['role' => 'staff']);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_the_route_ability_is_read_off_method_and_path(): void
    {
        $this->assertSame(['assets', 'view'], PermissionRegistry::abilityForRoute('GET', 'api/assets'));
        $this->assertSame(['assets', 'read'], PermissionRegistry::abilityForRoute('GET', 'api/assets/{asset}', ['asset' => 1]));
        $this->assertSame(['assets', 'create'], PermissionRegistry::abilityForRoute('POST', 'api/assets'));
        $this->assertSame(['assets', 'create'], PermissionRegistry::abilityForRoute('POST', 'api/assets/import'));
        $this->assertSame(['assets', 'update'], PermissionRegistry::abilityForRoute('PUT', 'api/assets/{asset}', ['asset' => 1]));
        $this->assertSame(['assets', 'delete'], PermissionRegistry::abilityForRoute('DELETE', 'api/assets/{asset}', ['asset' => 1]));
        $this->assertSame(['asset-transfers', 'update'], PermissionRegistry::abilityForRoute('POST', 'api/asset-transfers/{t}/approve', ['t' => 1]));
        $this->assertSame(['reports', 'view'], PermissionRegistry::abilityForRoute('POST', 'api/reports/email'));
        $this->assertNull(PermissionRegistry::abilityForRoute('POST', 'api/asset-returns/{r}/approve', ['r' => 1]));
    }

    public function test_a_custom_role_grant_opens_a_role_guarded_route(): void
    {
        $plain = User::factory()->create(['role' => 'staff']);
        $this->actingAs($plain)->postJson('/api/categories', ['name' => 'Tablets', 'short_name' => 'TAB'])->assertForbidden();

        $granted = $this->staffWith(['categories' => ['view', 'create']]);
        $this->actingAs($granted)->postJson('/api/categories', ['name' => 'Tablets', 'short_name' => 'TAB'])->assertCreated();
    }

    public function test_the_grant_covers_only_the_ticked_ability(): void
    {
        $user = $this->staffWith(['categories' => ['view', 'create']]);
        $category = AssetCategory::create(['name' => 'Chairs', 'short_name' => 'CHR']);

        // Create ticked, Update and Delete not.
        $this->actingAs($user)->putJson("/api/categories/{$category->id}", ['name' => 'Seats', 'short_name' => 'CHR'])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/categories/{$category->id}")->assertForbidden();
    }

    public function test_an_inactive_custom_role_opens_nothing(): void
    {
        $user = $this->staffWith(['categories' => ['view', 'create']], active: false);

        $this->actingAs($user)->postJson('/api/categories', ['name' => 'Tablets', 'short_name' => 'TAB'])->assertForbidden();
    }

    public function test_a_custom_role_can_open_user_management_and_reports(): void
    {
        $user = $this->staffWith(['users' => ['view'], 'reports' => ['view']]);

        $this->actingAs($user)->getJson('/api/users')->assertOk();
        $this->actingAs($user)->getJson('/api/reports/inventory')->assertOk();
        // Not granted: still closed.
        $this->actingAs($user)->getJson('/api/roles')->assertForbidden();
    }

    public function test_in_controller_staff_checks_honour_a_custom_grant(): void
    {
        $plain = User::factory()->create(['role' => 'staff']);
        $payload = ['full_name' => 'New Teacher', 'phone' => '012345678', 'program_id' => null];

        $this->actingAs($plain)->postJson('/api/staff', $payload)->assertForbidden();

        $granted = $this->staffWith(['staff' => ['view', 'create']]);
        $response = $this->actingAs($granted)->postJson('/api/staff', $payload);
        $this->assertNotSame(403, $response->status(), 'A Staff Directory → Create grant must get past the role check.');
    }

    public function test_a_custom_role_grant_to_edit_assets_works_end_to_end(): void
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'FAF'], ['name' => 'Furniture']);
        $office = Location::where('code', 'SR')->firstOrFail();
        $asset = Asset::create([
            'asset_code' => 'PEY-SR-FAF-0001', 'name' => 'Desk', 'category_id' => $category->id,
            'location_id' => $office->id, 'status' => 'active', 'condition' => 'good',
        ]);
        $user = $this->staffWith(['assets' => ['view', 'update']]);

        $this->actingAs($user)->putJson("/api/assets/{$asset->id}", [
            'name' => 'Standing Desk', 'category_id' => $category->id, 'location_id' => $office->id,
            'status' => 'active', 'condition' => 'good',
        ])->assertOk();
        $this->assertSame('Standing Desk', $asset->fresh()->name);
    }

    public function test_my_permissions_lists_the_custom_grants_separately(): void
    {
        $user = $this->staffWith(['categories' => ['view', 'create']]);

        $this->actingAs($user)->getJson('/api/me/permissions')
            ->assertOk()
            ->assertJsonPath('custom_permissions.categories', ['view', 'create']);
    }
}
