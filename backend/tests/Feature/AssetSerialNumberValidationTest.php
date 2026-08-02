<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetSerialNumberValidationTest extends TestCase
{
    use RefreshDatabase;

    private function location(): Location
    {
        return Location::where('code', 'SR')->firstOrFail();
    }

    private function category(): AssetCategory
    {
        return AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);
    }

    public function test_creating_an_asset_with_a_duplicate_serial_number_is_rejected(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        Asset::create([
            'asset_code' => 'PEY-SR-COM-0001',
            'name' => 'Existing Laptop',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'serial_number' => 'DUP-123',
            'status' => 'active',
            'condition' => 'good',
        ]);

        $response = $this->actingAs($opm)->postJson('/api/assets', [
            'name' => 'New Laptop',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'serial_number' => 'DUP-123',
            'status' => 'active',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('serial_number');
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_updating_an_asset_to_another_assets_serial_number_is_rejected(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        Asset::create([
            'asset_code' => 'PEY-SR-COM-0001',
            'name' => 'Laptop A',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'serial_number' => 'SN-AAA',
            'status' => 'active',
            'condition' => 'good',
        ]);
        $assetB = Asset::create([
            'asset_code' => 'PEY-SR-COM-0002',
            'name' => 'Laptop B',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'serial_number' => 'SN-BBB',
            'status' => 'active',
            'condition' => 'good',
        ]);

        $response = $this->actingAs($opm)->putJson("/api/assets/{$assetB->id}", [
            'name' => 'Laptop B',
            'category_id' => $assetB->category_id,
            'location_id' => $assetB->location_id,
            'serial_number' => 'SN-AAA',
            'status' => 'active',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('serial_number');
    }

    public function test_updating_an_asset_without_changing_its_own_serial_number_is_allowed(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = Asset::create([
            'asset_code' => 'PEY-SR-COM-0001',
            'name' => 'Laptop A',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'serial_number' => 'SN-AAA',
            'status' => 'active',
            'condition' => 'good',
        ]);

        $response = $this->actingAs($opm)->putJson("/api/assets/{$asset->id}", [
            'name' => 'Laptop A Renamed',
            'category_id' => $asset->category_id,
            'location_id' => $asset->location_id,
            'serial_number' => 'SN-AAA',
            'status' => 'active',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Laptop A Renamed', 'serial_number' => 'SN-AAA']);
    }

    public function test_two_assets_can_both_have_no_serial_number(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        Asset::create([
            'asset_code' => 'PEY-SR-COM-0099',
            'name' => 'Laptop A',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'status' => 'active',
            'condition' => 'good',
        ]);

        $response = $this->actingAs($opm)->postJson('/api/assets', [
            'name' => 'Laptop B',
            'category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'status' => 'active',
        ]);

        $response->assertStatus(201);
    }
}
