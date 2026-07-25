<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_quantity_creates_one_asset_per_unit_with_its_own_code_and_qr(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        $response = $this->actingAs($user)->postJson('/api/asset-stocks', [
            'name' => 'HP ProBook 450',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseCount('assets', 3);
        $this->assertDatabaseCount('asset_movements', 3);

        $codes = \App\Models\Asset::pluck('asset_code')->sort()->values();
        $this->assertSame(['PEY-SR-COM-0001', 'PEY-SR-COM-0002', 'PEY-SR-COM-0003'], $codes->all());

        $referenceNos = AssetMovement::where('movement_type', 'stock_in')->pluck('reference_no')->unique();
        $this->assertCount(1, $referenceNos, 'All three received units must share one receipt reference_no.');

        \App\Models\Asset::each(function ($asset) {
            $this->assertNotNull($asset->qr_code_path);
        });
    }
}
