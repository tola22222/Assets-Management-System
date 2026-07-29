<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
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

    private function makeAsset(?string $code = null): Asset
    {
        return Asset::create([
            'asset_code' => $code ?? 'PEY-SR-FAF-0001',
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

    public function test_finance_manager_can_update_an_asset_but_staff_and_ed_cannot(): void
    {
        $payload = fn ($asset) => [
            'name' => 'Renamed',
            'category_id' => $asset->category_id,
            'location_id' => $asset->location_id,
            'status' => 'active',
        ];

        $asset = $this->makeAsset('PEY-SR-FAF-0001');
        $finance = User::factory()->create(['role' => 'finance_manager']);
        $this->actingAs($finance)->putJson("/api/assets/{$asset->id}", $payload($asset))->assertStatus(200);

        foreach (['staff', 'executive_director'] as $i => $role) {
            $asset = $this->makeAsset('PEY-SR-FAF-000'.($i + 2));
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->putJson("/api/assets/{$asset->id}", $payload($asset))->assertStatus(403);
        }
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

    public function test_only_opm_can_approve_or_reject_a_transfer(): void
    {
        $asset = $this->makeAsset();
        $otherLocation = Location::where('code', '!=', 'SR')->firstOrFail();
        $requester = User::factory()->create(['role' => 'staff']);

        $transfer = \App\Models\AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_location_id' => $asset->location_id,
            'to_location_id' => $otherLocation->id,
            'requested_by' => $requester->id,
            'transfer_date' => now(),
            'status' => 'pending',
        ]);

        foreach (['staff', 'finance_manager', 'executive_director'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->postJson("/api/asset-transfers/{$transfer->id}/approve")->assertStatus(403);
            $this->actingAs($user)->postJson("/api/asset-transfers/{$transfer->id}/reject")->assertStatus(403);
        }
        $this->assertDatabaseHas('asset_transfers', ['id' => $transfer->id, 'status' => 'pending']);

        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->actingAs($opm)->postJson("/api/asset-transfers/{$transfer->id}/approve")->assertStatus(200);
        $this->assertDatabaseHas('asset_transfers', ['id' => $transfer->id, 'status' => 'approved']);
    }

    public function test_only_opm_can_approve_or_reject_a_return(): void
    {
        $asset = $this->makeAsset();
        $staffMember = \App\Models\Staff::create(['full_name' => 'Test Staff', 'phone' => '012345678']);
        $requester = User::factory()->create(['role' => 'staff']);

        $assignment = \App\Models\AssetAssignment::create([
            'asset_id' => $asset->id,
            'assigned_to_type' => 'staff',
            'assigned_to_id' => $staffMember->id,
            'location_id' => $asset->location_id,
            'quantity' => 1,
            'assigned_date' => now(),
            'status' => 'assigned',
        ]);

        $return = \App\Models\AssetReturn::create([
            'assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
            'returned_by' => $requester->id,
            'condition' => 'good',
            'return_date' => now(),
            'status' => 'pending',
        ]);

        foreach (['staff', 'finance_manager', 'executive_director'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->postJson("/api/asset-returns/{$return->id}/approve")->assertStatus(403);
            $this->actingAs($user)->postJson("/api/asset-returns/{$return->id}/reject")->assertStatus(403);
        }
        $this->assertDatabaseHas('asset_returns', ['id' => $return->id, 'status' => 'pending']);

        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->actingAs($opm)->postJson("/api/asset-returns/{$return->id}/approve")->assertStatus(200);
        $this->assertDatabaseHas('asset_returns', ['id' => $return->id, 'status' => 'approved']);
    }

    public function test_only_opm_can_manage_categories(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson('/api/categories', ['name' => 'Electronics', 'short_name' => 'ELE'])->assertStatus(403);
        $this->assertDatabaseMissing('asset_categories', ['name' => 'Electronics']);

        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->actingAs($opm)->postJson('/api/categories', ['name' => 'Electronics', 'short_name' => 'ELE'])->assertStatus(201);
    }

    public function test_opm_and_finance_can_manage_suppliers_but_staff_cannot(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson('/api/suppliers', ['name' => 'Acme Supplies'])->assertStatus(403);
        $this->assertDatabaseMissing('suppliers', ['name' => 'Acme Supplies']);

        $finance = User::factory()->create(['role' => 'finance_manager']);
        $this->actingAs($finance)->postJson('/api/suppliers', ['name' => 'Acme Supplies'])->assertStatus(201);
    }

    public function test_only_opm_can_manage_programs(): void
    {
        $ed = User::factory()->create(['role' => 'executive_director']);

        $this->actingAs($ed)->postJson('/api/programs', ['name' => 'Dream Program'])->assertStatus(403);
        $this->assertDatabaseMissing('programs', ['name' => 'Dream Program']);
    }

    public function test_opm_and_finance_can_create_or_cancel_an_assignment_but_staff_cannot(): void
    {
        $asset = $this->makeAsset();
        $staffMember = \App\Models\Staff::create(['full_name' => 'Test Staff', 'phone' => '012345678']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson('/api/asset-assignments', [
            'asset_id' => $asset->id,
            'assigned_to_type' => 'staff',
            'assigned_to_id' => $staffMember->id,
            'location_id' => $asset->location_id,
            'quantity' => 1,
            'assigned_date' => now()->toDateString(),
        ])->assertStatus(403);
        $this->assertDatabaseCount('asset_assignments', 0);

        $finance = User::factory()->create(['role' => 'finance_manager']);
        $this->actingAs($finance)->postJson('/api/asset-assignments', [
            'asset_id' => $asset->id,
            'assigned_to_type' => 'staff',
            'assigned_to_id' => $staffMember->id,
            'location_id' => $asset->location_id,
            'quantity' => 1,
            'assigned_date' => now()->toDateString(),
        ])->assertStatus(201);

        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $assignment = \App\Models\AssetAssignment::create([
            'asset_id' => $asset->id,
            'assigned_to_type' => 'staff',
            'assigned_to_id' => $staffMember->id,
            'location_id' => $asset->location_id,
            'quantity' => 1,
            'assigned_date' => now(),
            'status' => 'assigned',
        ]);
        $this->actingAs($staff)->postJson("/api/asset-assignments/{$assignment->id}/cancel")->assertStatus(403);
        $this->actingAs($opm)->postJson("/api/asset-assignments/{$assignment->id}/cancel")->assertStatus(200);
    }

    public function test_only_opm_can_delete_a_verification_record(): void
    {
        $asset = $this->makeAsset();
        $staff = User::factory()->create(['role' => 'staff']);

        $verification = \App\Models\AssetVerification::create([
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'quantity_verified' => 1,
            'condition' => 'good',
            'verified_by' => $staff->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($staff)->deleteJson("/api/asset-verifications/{$verification->id}")->assertStatus(403);
        $this->assertDatabaseHas('asset_verifications', ['id' => $verification->id]);
    }

    public function test_only_opm_can_finalize_a_verification(): void
    {
        $asset = $this->makeAsset();

        $verification = \App\Models\AssetVerification::create([
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'quantity_verified' => 1,
            'condition' => 'good',
            'verified_by' => 1,
            'verified_at' => now(),
        ]);

        $finance = User::factory()->create(['role' => 'finance_manager']);
        $this->actingAs($finance)->postJson("/api/asset-verifications/{$verification->id}/complete")->assertStatus(403);

        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->actingAs($opm)->postJson("/api/asset-verifications/{$verification->id}/complete")->assertStatus(200);
    }

    public function test_staff_cannot_pull_reports(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->getJson('/api/reports/inventory')->assertStatus(403);

        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->actingAs($opm)->getJson('/api/reports/inventory')->assertStatus(200);
    }

    public function test_staff_can_only_scan_and_verify_assets_at_their_own_site(): void
    {
        $ownSite = $this->location();
        $otherSite = Location::where('code', '!=', 'SR')->firstOrFail();

        $staffMember = \App\Models\Staff::create(['full_name' => 'Site Staff', 'location_id' => $ownSite->id]);
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $staffMember->id]);

        $ownAsset = $this->makeAsset('PEY-SR-FAF-0010');
        $otherAsset = Asset::create([
            'asset_code' => 'PEY-OT-FAF-0011',
            'name' => 'Other Site Chair',
            'category_id' => $this->category()->id,
            'location_id' => $otherSite->id,
            'status' => 'active',
            'condition' => 'good',
        ]);

        $this->actingAs($staffUser)->getJson('/api/qr-scan/'.$otherAsset->asset_code)->assertStatus(404);
        $this->actingAs($staffUser)->getJson('/api/qr-scan/'.$ownAsset->asset_code)->assertStatus(200);

        $this->actingAs($staffUser)->postJson("/api/qr-scan/{$otherAsset->asset_code}/verify", [
            'location_id' => $otherSite->id,
            'condition' => 'good',
        ])->assertStatus(404);
        $this->assertDatabaseMissing('asset_verifications', ['asset_id' => $otherAsset->id]);
    }
}
