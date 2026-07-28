<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDeletionDeniedTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(): Asset
    {
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        return Asset::create([
            'asset_code' => 'PEY-SR-COM-0001',
            'name' => 'Dell Laptop',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => 'active',
        ]);
    }

    public function test_operations_hr_manager_cannot_delete_an_asset_via_api(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($user)->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_staff_cannot_delete_an_asset_via_api(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($user)->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_no_role_can_delete_an_asset_via_the_web_route(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($user)->delete("/assets-registeration/{$asset->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }
}
