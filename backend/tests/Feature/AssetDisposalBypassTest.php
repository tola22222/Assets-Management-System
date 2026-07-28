<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDisposalBypassTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(string $status = 'active'): Asset
    {
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        return Asset::create([
            'asset_code' => 'PEY-SR-COM-0001',
            'name' => 'Dell Laptop',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => $status,
        ]);
    }

    public function test_a_new_asset_cannot_be_registered_as_already_disposed(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        $response = $this->actingAs($user)->postJson('/api/assets', [
            'name' => 'Dell Laptop',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => 'disposed',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
        $this->assertDatabaseMissing('assets', ['name' => 'Dell Laptop']);
    }

    public function test_editing_an_active_asset_cannot_set_status_to_disposed_directly(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset('active');

        $response = $this->actingAs($user)->putJson("/api/assets/{$asset->id}", [
            'name' => $asset->name,
            'category_id' => $asset->category_id,
            'location_id' => $asset->location_id,
            'status' => 'disposed',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'active']);
    }

    public function test_editing_an_already_disposed_asset_without_changing_status_still_works(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset('disposed');

        $response = $this->actingAs($user)->putJson("/api/assets/{$asset->id}", [
            'name' => 'Renamed Laptop',
            'category_id' => $asset->category_id,
            'location_id' => $asset->location_id,
            'status' => 'disposed',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Renamed Laptop', 'status' => 'disposed']);
    }

    public function test_approved_disposal_request_still_sets_status_to_disposed(): void
    {
        $approver = User::factory()->create(['role' => 'operations_hr_manager']);
        $requester = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset('active');

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'requested_by' => $requester->id,
            'recommended_action' => 'disposal',
            'reason' => 'Broken beyond repair',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($approver)->postJson("/api/asset-disposals/{$disposal->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'disposed']);
    }

    public function test_rejecting_a_disposal_request_requires_a_reason(): void
    {
        $approver = User::factory()->create(['role' => 'operations_hr_manager']);
        $requester = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset('active');

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'requested_by' => $requester->id,
            'recommended_action' => 'disposal',
            'reason' => 'Broken beyond repair',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($approver)->postJson("/api/asset-disposals/{$disposal->id}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('review_notes');
        $this->assertDatabaseHas('asset_disposals', ['id' => $disposal->id, 'status' => 'pending']);
    }

    public function test_rejecting_a_disposal_request_with_a_reason_succeeds(): void
    {
        $approver = User::factory()->create(['role' => 'executive_director']);
        $requester = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset('active');

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'requested_by' => $requester->id,
            'recommended_action' => 'disposal',
            'reason' => 'Broken beyond repair',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($approver)->postJson("/api/asset-disposals/{$disposal->id}/reject", [
            'review_notes' => 'Repair is cheaper than replacement.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('asset_disposals', [
            'id' => $disposal->id,
            'status' => 'rejected',
            'review_notes' => 'Repair is cheaper than replacement.',
        ]);
    }
}
