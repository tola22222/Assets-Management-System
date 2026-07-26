<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMaintenance;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetMaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(): Asset
    {
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        return Asset::create([
            'asset_code' => 'PEY-SR-COM-0001',
            'name' => 'Projector',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => 'active',
            'condition' => 'fair',
        ]);
    }

    public function test_staff_can_report_a_maintenance_issue(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($staff)->postJson('/api/asset-maintenance', [
            'asset_id' => $asset->id,
            'issue' => 'Projector bulb is flickering',
            'reported_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('asset_maintenance_records', [
            'asset_id' => $asset->id,
            'status' => 'pending',
        ]);
    }

    public function test_manager_can_start_and_complete_a_maintenance_record(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset();

        $record = AssetMaintenance::create([
            'asset_id' => $asset->id,
            'issue' => 'Broken hinge',
            'reported_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($opm)->postJson("/api/asset-maintenance/{$record->id}/start")
            ->assertStatus(200);
        $this->assertSame('in_progress', $record->fresh()->status);

        $this->actingAs($opm)->postJson("/api/asset-maintenance/{$record->id}/complete")
            ->assertStatus(200);
        $this->assertSame('completed', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->completed_at);
    }

    public function test_staff_cannot_start_or_complete_a_maintenance_record(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $record = AssetMaintenance::create([
            'asset_id' => $asset->id,
            'issue' => 'Broken hinge',
            'reported_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($staff)->postJson("/api/asset-maintenance/{$record->id}/start")
            ->assertStatus(403);
    }
}
