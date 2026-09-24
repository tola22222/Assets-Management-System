<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the fixes from the 2026-09-24 full-application audit: authorization
 * holes on the workflow endpoints, staff site scope, and the state checks
 * that let decisions be reversed or data be half-deleted.
 */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private Location $office;

    private Location $school;

    private AssetCategory $category;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Location::where('code', 'SR')->firstOrFail();
        $this->school = Location::where('code', '!=', 'SR')->whereNotNull('code')->firstOrFail();
        $this->category = AssetCategory::create(['name' => 'Furniture & Fixture', 'short_name' => 'FAF']);
    }

    private function asset(?Location $at = null, array $extra = []): Asset
    {
        $this->seq++;

        return Asset::create(array_merge([
            'asset_code' => sprintf('PEY-SR-FAF-%04d', $this->seq),
            'name' => 'Chair '.$this->seq,
            'category_id' => $this->category->id,
            'location_id' => ($at ?? $this->office)->id,
            'status' => 'active',
            'condition' => 'good',
        ], $extra));
    }

    private function staffUserAt(Location $location, string $name = 'Site Staff'): User
    {
        $staff = Staff::create(['full_name' => $name, 'phone' => '012345678', 'location_id' => $location->id]);

        return User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
    }

    /** A program lead with a login, so $location can accept transfers. */
    private function leadAt(Location $location): User
    {
        $staff = Staff::create(['full_name' => 'Lead '.$location->id, 'phone' => '012345678', 'location_id' => $location->id]);
        Program::create(['name' => 'Program '.$location->id, 'location_id' => $location->id, 'responsible_staff_id' => $staff->id]);

        return User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    // ---- Disposals -----------------------------------------------------

    public function test_the_executive_director_cannot_approve_their_own_disposal_request(): void
    {
        $ed = $this->user('executive_director');
        $asset = $this->asset();

        $id = $this->actingAs($ed)->postJson('/api/asset-disposals', [
            'asset_id' => $asset->id, 'recommended_action' => 'disposal', 'reason' => 'Broken beyond repair',
        ])->assertCreated()->json('id');

        $this->actingAs($ed)->postJson("/api/asset-disposals/{$id}/approve")->assertForbidden();
        $this->assertSame('active', $asset->fresh()->status);
    }

    public function test_a_reviewed_disposal_decision_cannot_be_reversed(): void
    {
        $opm = $this->user('operations_hr_manager');
        $ed = $this->user('executive_director');
        $asset = $this->asset();

        $id = $this->actingAs($opm)->postJson('/api/asset-disposals', [
            'asset_id' => $asset->id, 'recommended_action' => 'disposal', 'reason' => 'Old',
        ])->json('id');

        $this->actingAs($ed)->postJson("/api/asset-disposals/{$id}/reject", ['review_notes' => 'Keep it'])->assertOk();
        $this->actingAs($ed)->postJson("/api/asset-disposals/{$id}/approve")->assertStatus(422);

        $this->assertSame('active', $asset->fresh()->status);
        $this->assertDatabaseHas('asset_disposals', ['id' => $id, 'status' => 'rejected']);
    }

    public function test_only_the_requester_or_opm_can_delete_a_pending_disposal_request(): void
    {
        $opm = $this->user('operations_hr_manager');
        $asset = $this->asset();
        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id, 'requested_by' => $opm->id,
            'recommended_action' => 'repair', 'reason' => 'Formal report', 'status' => 'pending',
        ]);

        $this->actingAs($this->staffUserAt($this->office))->deleteJson("/api/asset-disposals/{$disposal->id}")->assertForbidden();
        $this->actingAs($this->user('finance_manager'))->deleteJson("/api/asset-disposals/{$disposal->id}")->assertForbidden();
        $this->assertDatabaseHas('asset_disposals', ['id' => $disposal->id]);

        $this->actingAs($opm)->deleteJson("/api/asset-disposals/{$disposal->id}")->assertOk();
    }

    public function test_approving_a_disposal_closes_the_assets_current_assignment(): void
    {
        $opm = $this->user('operations_hr_manager');
        $ed = $this->user('executive_director');
        $asset = $this->asset();
        $holder = Staff::create(['full_name' => 'Holder', 'phone' => '0', 'location_id' => $this->office->id]);
        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $holder->id,
            'location_id' => $this->office->id, 'quantity' => 1, 'assigned_date' => now(), 'status' => 'assigned',
        ]);

        $id = $this->actingAs($opm)->postJson('/api/asset-disposals', [
            'asset_id' => $asset->id, 'recommended_action' => 'disposal', 'reason' => 'Written off',
        ])->json('id');
        $this->actingAs($ed)->postJson("/api/asset-disposals/{$id}/approve")->assertOk();

        $this->assertSame('disposed', $asset->fresh()->status);
        $this->assertSame('returned', $assignment->fresh()->status);
    }

    public function test_staff_cannot_raise_a_disposal_request_for_another_sites_asset(): void
    {
        $staff = $this->staffUserAt($this->office);
        $elsewhere = $this->asset($this->school);

        $this->actingAs($staff)->postJson('/api/asset-disposals', [
            'asset_id' => $elsewhere->id, 'recommended_action' => 'repair', 'reason' => 'x',
        ])->assertForbidden();
    }

    // ---- Transfers -----------------------------------------------------

    public function test_only_the_requester_or_opm_can_delete_a_transfer_request(): void
    {
        $finance = $this->user('finance_manager');
        $this->leadAt($this->school);
        $asset = $this->asset();

        $id = $this->actingAs($finance)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id, 'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id, 'transfer_date' => now()->toDateString(),
        ])->assertCreated()->json('id');

        $this->actingAs($this->staffUserAt($this->office))->deleteJson("/api/asset-transfers/{$id}")->assertForbidden();
        $this->assertDatabaseHas('asset_transfers', ['id' => $id]);

        $this->actingAs($finance)->deleteJson("/api/asset-transfers/{$id}")->assertOk();
    }

    public function test_an_asset_can_only_have_one_open_transfer(): void
    {
        $opm = $this->user('operations_hr_manager');
        $this->leadAt($this->school);
        $asset = $this->asset();
        $payload = ['asset_id' => $asset->id, 'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id, 'transfer_date' => now()->toDateString()];

        $this->actingAs($opm)->postJson('/api/asset-transfers', $payload)->assertCreated();
        $this->actingAs($opm)->postJson('/api/asset-transfers', $payload)->assertStatus(422);
    }

    public function test_a_transfer_starts_where_the_asset_actually_is(): void
    {
        $opm = $this->user('operations_hr_manager');
        $third = Location::whereNotIn('id', [$this->office->id, $this->school->id])->firstOrFail();
        $this->leadAt($third);
        $asset = $this->asset($this->school);

        // The form claims the office; the asset is at the school.
        $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id, 'from_location_id' => $this->office->id,
            'to_location_id' => $third->id, 'transfer_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_site_staff_can_only_request_transfers_for_their_own_sites_assets(): void
    {
        $staff = $this->staffUserAt($this->office);
        $this->leadAt($this->office);
        $elsewhere = $this->asset($this->school);

        $this->actingAs($staff)->postJson('/api/asset-transfers', [
            'asset_id' => $elsewhere->id, 'from_location_id' => $this->school->id,
            'to_location_id' => $this->office->id, 'transfer_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_a_disposed_asset_cannot_be_transferred(): void
    {
        $opm = $this->user('operations_hr_manager');
        $this->leadAt($this->school);
        $asset = $this->asset(null, ['status' => 'disposed']);

        $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id, 'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id, 'transfer_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_an_old_transfer_leg_cannot_be_returned_after_the_asset_moved_on(): void
    {
        $lead = $this->leadAt($this->school);
        $asset = $this->asset($this->office);
        $leg = AssetTransfer::create([
            'asset_id' => $asset->id, 'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'requested_by' => $lead->id, 'transfer_date' => now(), 'status' => 'received',
        ]);
        // The asset is no longer at the school this leg delivered it to.
        $asset->update(['location_id' => Location::whereNotIn('id', [$this->office->id, $this->school->id])->value('id')]);
        $this->leadAt($this->office);

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$leg->id}/return")->assertStatus(422);
    }

    // ---- Assets ----------------------------------------------------------

    public function test_finance_cannot_write_an_asset_off_by_editing_its_status(): void
    {
        $asset = $this->asset();
        $payload = ['name' => $asset->name, 'category_id' => $asset->category_id, 'location_id' => $asset->location_id, 'status' => 'disposed'];

        $this->actingAs($this->user('finance_manager'))->putJson("/api/assets/{$asset->id}", $payload)->assertStatus(422);
        $this->actingAs($this->user('operations_hr_manager'))->putJson("/api/assets/{$asset->id}", ['status' => 'banana'] + $payload)->assertStatus(422);
        $this->assertSame('active', $asset->fresh()->status);
    }

    public function test_a_new_asset_cannot_be_registered_as_disposed(): void
    {
        $this->actingAs($this->user('operations_hr_manager'))->postJson('/api/assets', [
            'name' => 'Desk', 'category_id' => $this->category->id, 'location_id' => $this->office->id, 'status' => 'disposed',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_an_asset_with_history_cannot_be_deleted(): void
    {
        $opm = $this->user('operations_hr_manager');
        $asset = $this->asset();
        AssetVerification::create([
            'asset_id' => $asset->id, 'location_id' => $this->office->id, 'verified_by' => $opm->id,
            'quantity_verified' => 1, 'condition' => 'good', 'verified_at' => now(),
        ]);

        $this->actingAs($opm)->deleteJson("/api/assets/{$asset->id}")->assertStatus(422);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_a_category_with_assets_cannot_be_deleted(): void
    {
        $this->asset();

        $this->actingAs($this->user('operations_hr_manager'))
            ->deleteJson("/api/categories/{$this->category->id}")
            ->assertStatus(422);
    }

    public function test_site_staff_cannot_flag_or_regenerate_qr_for_another_sites_asset(): void
    {
        $staff = $this->staffUserAt($this->office);
        $elsewhere = $this->asset($this->school);

        $this->actingAs($staff)->postJson("/api/assets/{$elsewhere->id}/flag", ['note' => 'x', 'condition' => 'lost'])->assertNotFound();
        $this->actingAs($staff)->postJson("/api/assets/{$elsewhere->id}/regenerate-qr")->assertForbidden();
        $this->actingAs($staff)->getJson("/api/assets/{$elsewhere->id}/qr-code/download")->assertNotFound();
        $this->assertSame('good', $elsewhere->fresh()->condition);
    }

    public function test_site_staff_search_and_site_detail_are_limited_to_their_own_site(): void
    {
        $staff = $this->staffUserAt($this->office);
        $this->asset($this->office, ['name' => 'Projector Office']);
        $this->asset($this->school, ['name' => 'Projector School']);

        $names = collect($this->actingAs($staff)->getJson('/api/search?q=Projector')->assertOk()->json('assets'))->pluck('name');
        $this->assertEquals(['Projector Office'], $names->all());

        $this->actingAs($staff)->getJson("/api/locations/{$this->school->id}")->assertNotFound();
    }

    // ---- QR scan -----------------------------------------------------------

    public function test_every_reported_condition_is_saved_and_damage_alerts_opm(): void
    {
        $opm = $this->user('operations_hr_manager');
        $staff = $this->staffUserAt($this->office);
        $asset = $this->asset();

        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", ['location_id' => $this->office->id, 'condition' => 'fair'])->assertOk();
        $this->assertSame('fair', $asset->fresh()->condition);

        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", ['location_id' => $this->office->id, 'condition' => 'broken', 'remark' => 'Leg snapped'])->assertOk();
        $this->assertSame('broken', $asset->fresh()->condition);
        $this->assertDatabaseHas('notifications', ['user_id' => $opm->id, 'type' => 'asset_flagged']);

        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", ['location_id' => $this->office->id, 'condition' => 'good'])->assertOk();
        $this->assertSame('good', $asset->fresh()->condition);
    }

    // ---- Users, auth, notifications ------------------------------------

    public function test_an_administrator_cannot_change_their_own_role(): void
    {
        $opm = $this->user('operations_hr_manager');
        $this->user('operations_hr_manager');

        $this->actingAs($opm)->putJson("/api/users/{$opm->id}", ['name' => $opm->name, 'email' => $opm->email, 'role' => 'staff'])->assertStatus(422);
        $this->assertSame('operations_hr_manager', $opm->fresh()->role);
    }

    public function test_the_last_active_opm_cannot_be_demoted_even_with_custom_admin_roles_around(): void
    {
        $opm = $this->user('operations_hr_manager');
        $other = $this->user('operations_hr_manager');
        $other->update(['is_locked' => true]);

        $target = $this->user('operations_hr_manager');
        // $opm demotes $target: fine, $opm remains.
        $this->actingAs($opm)->putJson("/api/users/{$target->id}", ['name' => $target->name, 'email' => $target->email, 'role' => 'staff'])->assertOk();

        // Now $opm is the only active, unlocked OPM; another OPM (locked) cannot count.
        $this->actingAs($other)->putJson("/api/users/{$opm->id}", ['name' => $opm->name, 'email' => $opm->email, 'role' => 'staff'])->assertStatus(422);
    }

    public function test_changing_a_users_role_signs_them_out(): void
    {
        $opm = $this->user('operations_hr_manager');
        $staff = $this->staffUserAt($this->office);
        $staff->createToken('spa');

        $this->actingAs($opm)->putJson("/api/users/{$staff->id}", ['name' => $staff->name, 'email' => $staff->email, 'role' => 'finance_manager', 'staff_id' => $staff->staff_id])->assertOk();

        $this->assertSame(0, $staff->tokens()->count());
    }

    public function test_an_admin_password_reset_signs_the_user_out(): void
    {
        $staff = $this->staffUserAt($this->office);
        $staff->createToken('spa');

        $this->actingAs($this->user('operations_hr_manager'))
            ->postJson("/api/users/{$staff->id}/reset-password", ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertOk();

        $this->assertSame(0, $staff->tokens()->count());
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        $user = $this->user('staff');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_a_user_cannot_mark_someone_elses_notification_read(): void
    {
        $opm = $this->user('operations_hr_manager');
        $note = Notification::create(['user_id' => $opm->id, 'type' => 'x', 'message' => 'Private', 'is_read' => false]);

        $this->actingAs($this->staffUserAt($this->office))->postJson("/api/notifications/{$note->id}/mark-read")->assertNotFound();
        $this->assertFalse((bool) $note->fresh()->is_read);
    }

    // ---- Lists and dashboard ---------------------------------------------

    public function test_finance_and_the_ed_see_every_assignment(): void
    {
        $holder = Staff::create(['full_name' => 'Holder', 'phone' => '0', 'location_id' => $this->office->id]);
        AssetAssignment::create([
            'asset_id' => $this->asset()->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $holder->id,
            'location_id' => $this->office->id, 'quantity' => 1, 'assigned_date' => now(), 'status' => 'assigned',
        ]);

        $this->actingAs($this->user('finance_manager'))->getJson('/api/asset-assignments')->assertOk()->assertJsonCount(1);
        $this->actingAs($this->user('executive_director'))->getJson('/api/asset-assignments')->assertOk()->assertJsonCount(1);
    }

    public function test_a_new_assignment_shows_as_current_on_the_register(): void
    {
        $opm = $this->user('operations_hr_manager');
        $holder = Staff::create(['full_name' => 'Holder', 'phone' => '0', 'location_id' => $this->office->id]);
        $asset = $this->asset();

        $this->actingAs($opm)->postJson('/api/asset-assignments', [
            'asset_id' => $asset->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $holder->id,
            'location_id' => $this->office->id, 'quantity' => 1, 'assigned_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('status', 'assigned');

        $row = collect($this->actingAs($opm)->getJson('/api/assets')->json())->firstWhere('id', $asset->id);
        $this->assertCount(1, $row['assignments']);
    }

    public function test_only_opm_gets_the_activity_feed_on_the_dashboard(): void
    {
        $this->actingAs($this->user('finance_manager'))->getJson('/api/dashboard')->assertOk()->assertJsonPath('recent_activity', []);
    }

    public function test_disposed_assets_are_not_counted_on_the_dashboard(): void
    {
        $this->asset();
        $this->asset(null, ['status' => 'disposed', 'purchase_price' => 500]);

        $this->actingAs($this->user('operations_hr_manager'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('total_assets', 1)
            ->assertJsonPath('recorded_value', 0);
    }

    public function test_site_staff_only_see_their_own_sites_staff_and_disposals(): void
    {
        $staff = $this->staffUserAt($this->office, 'Office Person');
        Staff::create(['full_name' => 'School Person', 'phone' => '0', 'location_id' => $this->school->id]);
        $opm = $this->user('operations_hr_manager');
        AssetDisposal::create(['asset_id' => $this->asset($this->school)->id, 'requested_by' => $opm->id, 'recommended_action' => 'repair', 'reason' => 'x', 'status' => 'pending']);

        $names = collect($this->actingAs($staff)->getJson('/api/staff')->json())->pluck('full_name');
        $this->assertContains('Office Person', $names);
        $this->assertNotContains('School Person', $names);

        $this->actingAs($staff)->getJson('/api/asset-disposals')->assertOk()->assertJsonCount(0);
    }

    public function test_staff_who_lead_a_program_cannot_be_deleted(): void
    {
        $this->leadAt($this->school);
        $lead = Program::first()->responsible_staff_id;

        $this->actingAs($this->user('operations_hr_manager'))->deleteJson("/api/staff/{$lead}")->assertStatus(422);
        $this->assertDatabaseHas('staff', ['id' => $lead]);
    }

    // ---- SPA file route --------------------------------------------------

    public function test_the_spa_route_never_serves_files_outside_the_build(): void
    {
        $response = $this->get('/app/..%2f..%2fbackend%2f.env');

        $this->assertStringNotContainsString('APP_KEY', (string) $response->getContent());
    }
}
