<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicConditionUpdateThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_condition_update_endpoint_is_rate_limited(): void
    {
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::create(['name' => 'Furniture & Fixture', 'short_name' => 'FAF']);
        $asset = Asset::create([
            'asset_code' => 'PEY-SR-FAF-0001',
            'name' => 'Office Chair',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => 'active',
            'condition' => 'good',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->post("/asset/{$asset->asset_code}/update-condition", ['condition' => 'good', 'location_id' => $location->id])
                ->assertStatus(302);
        }

        $this->post("/asset/{$asset->asset_code}/update-condition", ['condition' => 'good', 'location_id' => $location->id])
            ->assertStatus(429);
    }
}
