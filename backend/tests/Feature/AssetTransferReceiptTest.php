<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A transfer is a request the RECEIVING site answers.
 *
 * The rule these tests exist to hold: the office cannot mark its own delivery
 * as received on a school's behalf. Who may answer for a site is resolved
 * through School -> Program -> Responsible Staff, never by role.
 */
class AssetTransferReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Location $office;

    private Location $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Location::where('code', 'SR')->firstOrFail();
        $this->school = Location::where('code', '!=', 'SR')->firstOrFail();
    }

    private function makeAsset(): Asset
    {
        $category = AssetCategory::create(['name' => 'Furniture & Fixture', 'short_name' => 'FAF']);

        return Asset::create([
            'asset_code' => 'PEY-SR-FAF-0001',
            'name' => 'Office Chair',
            'category_id' => $category->id,
            'location_id' => $this->office->id,
            'status' => 'active',
            'condition' => 'good',
        ]);
    }

    private function staffAt(Location $location, string $name): Staff
    {
        return Staff::create([
            'full_name' => $name,
            'phone' => '012345678',
            'location_id' => $location->id,
        ]);
    }

    /**
     * The whole chain a site needs before it can accept anything: a program at
     * that site, a responsible staff member, and a login account for them.
     */
    private function responsibleUserAt(Location $location, string $name = 'Program Lead', string $role = 'staff'): User
    {
        $staff = $this->staffAt($location, $name);

        Program::create([
            'name' => 'Dream Management '.$location->id.' '.$name,
            'location_id' => $location->id,
            'responsible_staff_id' => $staff->id,
        ]);

        return User::factory()->create(['role' => $role, 'staff_id' => $staff->id]);
    }

    private function sendToSchool(User $sender, ?Asset $asset = null): AssetTransfer
    {
        $asset ??= $this->makeAsset();

        $response = $this->actingAs($sender)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id,
            'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(201);

        return AssetTransfer::findOrFail($response->json('id'));
    }

    public function test_hr_transfer_to_a_school_creates_a_pending_request_and_moves_nothing(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);

        $transfer = $this->sendToSchool($opm);

        $this->assertSame('pending', $transfer->status);
        $this->assertSame($this->office->id, $transfer->asset->fresh()->location_id);
    }

    public function test_the_office_cannot_accept_a_school_transfer_on_its_behalf(): void
    {
        // The rule the whole redesign exists for.
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $this->actingAs($opm)->postJson("/api/asset-transfers/{$transfer->id}/confirm")->assertStatus(403);
        $this->actingAs($opm)->postJson("/api/asset-transfers/{$transfer->id}/decline")->assertStatus(403);

        $this->assertDatabaseHas('asset_transfers', ['id' => $transfer->id, 'status' => 'pending']);
        $this->assertSame($this->office->id, $transfer->asset->fresh()->location_id);
    }

    public function test_the_schools_responsible_staff_accepts_and_the_asset_moves(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $this->actingAs($lead)
            ->postJson("/api/asset-transfers/{$transfer->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('status', 'received');

        $this->assertSame($this->school->id, $transfer->asset->fresh()->location_id);
        $this->assertDatabaseHas('asset_transfers', [
            'id' => $transfer->id,
            'status' => 'received',
            'received_by' => $lead->id,
        ]);
    }

    public function test_the_schools_responsible_staff_can_reject_and_the_asset_stays_put(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $this->actingAs($lead)
            ->postJson("/api/asset-transfers/{$transfer->id}/decline", ['rejection_reason' => 'No space for it'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'rejected');

        $this->assertSame($this->office->id, $transfer->asset->fresh()->location_id);
        $this->assertDatabaseHas('asset_transfers', [
            'id' => $transfer->id,
            'rejection_reason' => 'No space for it',
        ]);
    }

    public function test_other_staff_at_the_school_are_not_confirmers(): void
    {
        // Being based at the site is not enough — accountability runs through
        // the program, so only its responsible staff answer for it.
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $bystander = User::factory()->create([
            'role' => 'staff',
            'staff_id' => $this->staffAt($this->school, 'Someone Else')->id,
        ]);

        $this->actingAs($bystander)->postJson("/api/asset-transfers/{$transfer->id}/confirm")->assertStatus(403);
    }

    public function test_a_lead_from_another_school_cannot_accept(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $elsewhere = Location::whereNotIn('id', [$this->office->id, $this->school->id])->firstOrFail();
        $otherLead = $this->responsibleUserAt($elsewhere, 'Other Lead');

        $this->actingAs($otherLead)->postJson("/api/asset-transfers/{$transfer->id}/confirm")->assertStatus(403);
    }

    public function test_a_transfer_to_a_site_with_no_responsible_staff_is_refused_up_front(): void
    {
        // Better a loud 422 than a row that sits pending forever with nobody
        // able to act on it and no indication why.
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset();

        $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id,
            'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('asset_transfers', 0);
    }

    public function test_a_program_without_a_responsible_staff_does_not_make_a_site_receivable(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        Program::create(['name' => 'Unstaffed', 'location_id' => $this->school->id]);
        $asset = $this->makeAsset();

        $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id,
            'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_a_non_opm_request_waits_on_approval_then_on_the_destination(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $requester = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $response = $this->actingAs($requester)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id,
            'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(201);

        $id = $response->json('id');
        $this->assertDatabaseHas('asset_transfers', ['id' => $id, 'status' => 'pending_approval']);

        // The destination cannot act until OPM has released it.
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm")->assertStatus(422);

        $this->actingAs($opm)->postJson("/api/asset-transfers/{$id}/approve")->assertStatus(200);
        $this->assertDatabaseHas('asset_transfers', ['id' => $id, 'status' => 'pending']);
        $this->assertSame($this->office->id, $asset->fresh()->location_id);

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm")->assertStatus(200);
        $this->assertSame($this->school->id, $asset->fresh()->location_id);
    }

    public function test_the_school_returns_the_asset_and_the_office_lead_accepts_it_back(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        // The office answers for itself the same way any site does.
        $officeLead = $this->responsibleUserAt($this->office, 'Office Lead', 'operations_hr_manager');

        $transfer = $this->sendToSchool($opm);
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/confirm")->assertStatus(200);

        $response = $this->actingAs($lead)
            ->postJson("/api/asset-transfers/{$transfer->id}/return", ['reason' => 'Program finished'])
            ->assertStatus(201);

        $return = AssetTransfer::findOrFail($response->json('id'));
        $this->assertSame($this->school->id, $return->from_location_id);
        $this->assertSame($this->office->id, $return->to_location_id);
        $this->assertSame('pending', $return->status);
        $this->assertSame($transfer->id, $return->parent_transfer_id);

        // Still at the school until the office accepts it.
        $this->assertSame($this->school->id, $transfer->asset->fresh()->location_id);

        $this->actingAs($officeLead)->postJson("/api/asset-transfers/{$return->id}/confirm")->assertStatus(200);
        $this->assertSame($this->office->id, $transfer->asset->fresh()->location_id);
    }

    public function test_an_asset_cannot_be_returned_twice(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $this->responsibleUserAt($this->office, 'Office Lead');
        $transfer = $this->sendToSchool($opm);
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/confirm")->assertStatus(200);

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/return")->assertStatus(201);
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/return")->assertStatus(422);
    }

    public function test_a_transfer_the_destination_is_answering_cannot_be_deleted(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $this->actingAs($opm)->deleteJson("/api/asset-transfers/{$transfer->id}")->assertStatus(422);
        $this->assertDatabaseHas('asset_transfers', ['id' => $transfer->id]);
    }

    public function test_index_flags_tell_each_viewer_what_they_may_do(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $this->actingAs($lead)->getJson('/api/asset-transfers')
            ->assertStatus(200)
            ->assertJsonPath('0.can_confirm', true)
            ->assertJsonPath('0.can_decline', true)
            ->assertJsonPath('0.can_return', false);

        $this->actingAs($opm)->getJson('/api/asset-transfers')
            ->assertStatus(200)
            ->assertJsonPath('0.can_confirm', false)
            ->assertJsonPath('0.can_decline', false);
    }
}
