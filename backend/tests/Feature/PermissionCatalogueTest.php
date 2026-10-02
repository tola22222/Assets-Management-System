<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The permission page lists only modules and actions that control something
 * in the system today.
 */
class PermissionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_obsolete_modules_are_gone(): void
    {
        foreach (['asset-assignments', 'dashboard', 'qr-scan', 'search', 'notifications'] as $module) {
            $this->assertFalse(PermissionRegistry::isModule($module), "{$module} should not be a permission module");
        }
    }

    public function test_every_module_has_abilities_and_every_ability_is_known(): void
    {
        $this->assertSame(array_keys(PermissionRegistry::MODULES), array_keys(PermissionRegistry::MODULE_ABILITIES));

        foreach (PermissionRegistry::MODULE_ABILITIES as $module => $abilities) {
            $this->assertNotEmpty($abilities, $module);
            $this->assertContains('view', $abilities, $module);
            $this->assertSame($abilities, array_values(array_unique($abilities)), "{$module} has duplicates");
            foreach ($abilities as $ability) {
                $this->assertTrue(PermissionRegistry::isAbility($ability), "{$module}.{$ability}");
            }
        }
    }

    public function test_baselines_only_name_real_permissions(): void
    {
        foreach (PermissionRegistry::BASELINE as $role => $grants) {
            foreach (PermissionRegistry::normalise($grants) as $module => $abilities) {
                $this->assertTrue(PermissionRegistry::isModule($module), "{$role}: {$module}");
            }
        }
    }

    public function test_the_catalogue_sends_each_modules_abilities(): void
    {
        $hr = User::factory()->create(['role' => 'operations_hr_manager']);

        $modules = collect($this->actingAs($hr)->getJson('/api/roles/catalogue')->assertOk()->json('modules'))->keyBy('key');

        $this->assertSame(['view', 'hide'], $modules['stock-items']['abilities']);
        $this->assertSame('Add Asset', $modules['assets']['label']);
        $this->assertFalse($modules->has('asset-assignments'));
    }

    public function test_saving_a_role_drops_actions_a_module_does_not_have(): void
    {
        $role = Role::create(['name' => 'X', 'slug' => 'x', 'is_active' => true, 'is_system' => false]);

        $role->syncGrants([
            'stock-items' => ['view', 'create', 'delete'],
            'asset-assignments' => ['view', 'create'],
            'reports' => ['view', 'read'],
        ]);

        $grants = $role->fresh()->grants();
        ksort($grants);
        $this->assertSame(['reports' => ['view'], 'stock-items' => ['view']], $grants);
    }

    public function test_saving_settings_and_duplicating_a_role_map_to_the_right_ability(): void
    {
        $this->assertSame(['settings', 'update'], PermissionRegistry::abilityForRoute('POST', 'api/settings'));
        $this->assertSame(['roles', 'create'], PermissionRegistry::abilityForRoute('POST', 'api/roles/{role}/duplicate', ['role' => 1]));
    }
}
