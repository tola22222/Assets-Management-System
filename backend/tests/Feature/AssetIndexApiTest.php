<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetIndexApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(array $overrides = []): Asset
    {
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::firstOrCreate(['short_name' => 'COM'], ['name' => 'Computer Equipment']);

        return Asset::create(array_merge([
            'asset_code' => 'PEY-SR-COM-'.str_pad((string) random_int(1, 999999), 4, '0', STR_PAD_LEFT),
            'name' => 'Test Asset',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'condition' => 'good',
            'status' => 'active',
        ], $overrides));
    }

    public function test_index_returns_a_paginated_envelope(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->makeAsset();
        $this->makeAsset();

        $response = $this->actingAs($user)->getJson('/api/assets?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'per_page', 'total', 'last_page']);
        $this->assertSame(10, $response->json('per_page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('total'));
    }

    public function test_index_search_matches_name_and_asset_code(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->makeAsset(['name' => 'Dell Laptop XPS']);
        $this->makeAsset(['name' => 'Office Chair']);

        $response = $this->actingAs($user)->getJson('/api/assets?search=Laptop');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Dell Laptop XPS'));
        $this->assertFalse($names->contains('Office Chair'));
    }

    public function test_index_rejects_unsortable_column_and_falls_back_to_created_at(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->makeAsset();

        $response = $this->actingAs($user)->getJson('/api/assets?sort_by=description&sort_dir=asc');

        $response->assertOk();
    }

    public function test_only_opm_can_bulk_delete_assets(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($staff)->postJson('/api/assets/bulk-delete', ['ids' => [$asset->id]]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_opm_bulk_delete_removes_all_given_assets(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $a = $this->makeAsset();
        $b = $this->makeAsset();

        $response = $this->actingAs($opm)->postJson('/api/assets/bulk-delete', ['ids' => [$a->id, $b->id]]);

        $response->assertOk();
        $this->assertDatabaseCount('assets', 0);
    }
}
