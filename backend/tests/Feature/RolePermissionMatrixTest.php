<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function location(): Location
    {
        return Location::where('code', 'SR')->firstOrFail();
    }

    private function category(): AssetCategory
    {
        return AssetCategory::create(['name' => 'Furniture & Fixture', 'short_name' => 'FAF']);
    }

    private function makeAsset(): Asset
    {
        return Asset::create([
            'asset_code' => 'PEY-SR-FAF-0001',
            'name' => 'Office Chair',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'status' => 'active',
            'condition' => 'good',
        ]);
    }

    public function test_staff_cannot_create_an_asset(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->postJson('/api/assets', [
            'name' => 'New Laptop',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'status' => 'active',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_finance_manager_cannot_update_an_asset(): void
    {
        $finance = User::factory()->create(['role' => 'finance_manager']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($finance)->putJson("/api/assets/{$asset->id}", [
            'name' => 'Renamed',
            'category_id' => $asset->category_id,
            'location_id' => $asset->location_id,
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    public function test_no_role_other_than_opm_can_delete_an_asset(): void
    {
        $asset = $this->makeAsset();

        foreach (['staff', 'finance_manager', 'executive_director'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->deleteJson("/api/assets/{$asset->id}");
            $response->assertStatus(403);
        }

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_opm_can_delete_an_asset(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($opm)->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_all_roles_can_view_the_asset_register(): void
    {
        $this->makeAsset();

        foreach (['staff', 'finance_manager', 'executive_director', 'operations_hr_manager'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/api/assets')->assertStatus(200);
        }
    }

    public function test_finance_manager_cannot_manage_locations(): void
    {
        $finance = User::factory()->create(['role' => 'finance_manager']);

        $response = $this->actingAs($finance)->postJson('/api/locations', [
            'name' => 'New Site',
            'code' => 'NS',
            'type' => 'office',
        ]);

        $response->assertStatus(403);
    }

    public function test_executive_director_cannot_manage_users(): void
    {
        $ed = User::factory()->create(['role' => 'executive_director']);
        $target = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($ed)->putJson("/api/users/{$target->id}", ['name' => 'New Name']);

        $response->assertStatus(403);
    }

    public function test_only_opm_can_delete_a_stock_movement_record(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset();
        $movement = AssetMovement::create([
            'asset_id' => $asset->id,
            'to_location_id' => $this->location()->id,
            'movement_type' => 'stock_in',
            'quantity' => 1,
            'reference_no' => 'REC-0001',
        ]);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->deleteJson("/api/asset-stocks/{$movement->id}")->assertStatus(403);
        $this->assertDatabaseHas('asset_movements', ['id' => $movement->id]);

        $this->actingAs($opm)->deleteJson("/api/asset-stocks/{$movement->id}")->assertStatus(200);
        $this->assertDatabaseMissing('asset_movements', ['id' => $movement->id]);
    }

    public function test_non_opm_non_ed_role_cannot_approve_disposal(): void
    {
        $finance = User::factory()->create(['role' => 'finance_manager']);
        $requester = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $disposal = \App\Models\AssetDisposal::create([
            'asset_id' => $asset->id,
            'requested_by' => $requester->id,
            'recommended_action' => 'disposal',
            'reason' => 'Beyond repair',
            'status' => 'pending',
        ]);

        $this->actingAs($finance)->postJson("/api/asset-disposals/{$disposal->id}/approve")->assertStatus(403);
    }
}
