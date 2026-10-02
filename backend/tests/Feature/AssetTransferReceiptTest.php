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

    private function makeAssetAt(Location $location, string $code): Asset
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'FAF'], ['name' => 'Furniture & Fixture']);

        return Asset::create([
            'asset_code' => $code, 'name' => 'Office Chair', 'category_id' => $category->id,
            'location_id' => $location->id, 'status' => 'active', 'condition' => 'good',
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

        $this->actingAs($opm)->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])->assertStatus(403);
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
            ->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])
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

        $this->actingAs($bystander)->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])->assertStatus(403);
    }

    public function test_a_lead_from_another_school_cannot_accept(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $elsewhere = Location::whereNotIn('id', [$this->office->id, $this->school->id])->firstOrFail();
        $otherLead = $this->responsibleUserAt($elsewhere, 'Other Lead');

        $this->actingAs($otherLead)->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])->assertStatus(403);
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

    public function test_the_executive_director_can_approve_and_reject_a_transfer_request(): void
    {
        $ed = User::factory()->create(['role' => 'executive_director']);
        $staffUser = User::factory()->create(['role' => 'staff']);
        $lead = $this->responsibleUserAt($this->school);
        // Staff can't raise transfers; another non-OPM role (a second ED) can.
        $requester = User::factory()->create(['role' => 'executive_director']);
        $asset = $this->makeAsset();

        $request = fn () => $this->actingAs($requester)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id,
            'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(201)->json('id');

        $first = $request();
        $this->actingAs($staffUser)->postJson("/api/asset-transfers/{$first}/approve")->assertStatus(403);
        $this->actingAs($ed)->postJson("/api/asset-transfers/{$first}/approve")->assertStatus(200);
        $this->assertDatabaseHas('asset_transfers', ['id' => $first, 'status' => 'pending', 'approved_by' => $ed->id]);

        // One open transfer per asset: the school answers the first before
        // anyone can raise another for the same asset.
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$first}/decline", ['rejection_reason' => 'Not needed here'])->assertStatus(200);

        $second = $request();
        $this->actingAs($ed)->postJson("/api/asset-transfers/{$second}/reject", ['rejection_reason' => 'Not needed'])->assertStatus(200);
        $this->assertDatabaseHas('asset_transfers', ['id' => $second, 'status' => 'rejected']);
    }

    public function test_the_executive_director_cannot_approve_their_own_transfer_request(): void
    {
        $ed = $this->responsibleUserAt($this->office, 'Director', 'executive_director');
        $this->responsibleUserAt($this->school);
        $asset = $this->makeAsset();

        $id = $this->actingAs($ed)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id,
            'from_location_id' => $this->office->id,
            'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(201)->json('id');

        $this->actingAs($ed)->postJson("/api/asset-transfers/{$id}/approve")->assertStatus(403);
    }

    public function test_a_non_opm_request_waits_on_approval_then_on_the_destination(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        // Staff can't raise transfers; the ED is the non-OPM role that can.
        $requester = User::factory()->create(['role' => 'executive_director']);
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
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])->assertStatus(422);

        $this->actingAs($opm)->postJson("/api/asset-transfers/{$id}/approve")->assertStatus(200);
        $this->assertDatabaseHas('asset_transfers', ['id' => $id, 'status' => 'pending']);
        $this->assertSame($this->office->id, $asset->fresh()->location_id);

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])->assertStatus(200);
        $this->assertSame($this->school->id, $asset->fresh()->location_id);
    }

    public function test_an_hr_return_takes_effect_at_once(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);

        $transfer = $this->sendToSchool($opm);
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])->assertStatus(200);

        // The school's own staff may not send it back — only HR or Finance.
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/return")->assertForbidden();
        $this->actingAs($lead)->getJson('/api/asset-transfers')->assertJsonPath('0.can_return', false);

        $response = $this->actingAs($opm)
            ->postJson("/api/asset-transfers/{$transfer->id}/return", ['reason' => 'Program finished'])
            ->assertStatus(201);

        $return = AssetTransfer::findOrFail($response->json('id'));
        $this->assertSame($this->school->id, $return->from_location_id);
        $this->assertSame($this->office->id, $return->to_location_id);
        $this->assertSame($transfer->id, $return->parent_transfer_id);

        // No second step: HR is the office side, so it is back straight away,
        // signed off by HR.
        $this->assertSame('received', $return->status);
        $this->assertSame($opm->id, $return->received_by);
        $this->assertSame($this->office->id, $transfer->asset->fresh()->location_id);
    }

    public function test_a_return_cannot_itself_be_returned_or_edited(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])->assertOk();

        $back = $this->actingAs($opm)->postJson("/api/asset-transfers/{$transfer->id}/return")->assertStatus(201)->json('id');
        $this->assertSame($this->office->id, $transfer->asset->fresh()->location_id);

        // No Edit on either row now, and the server refuses a second trip.
        $rows = collect($this->actingAs($opm)->getJson('/api/asset-transfers')->json())->keyBy('id');
        $this->assertFalse($rows[$transfer->id]['can_return']);
        $this->assertFalse($rows[$back]['can_return']);

        $this->actingAs($opm)->postJson("/api/asset-transfers/{$back}/return")->assertStatus(422);
        $this->actingAs($opm)->putJson("/api/asset-transfers/{$back}/assignment", ['assigned_to_type' => null])->assertStatus(422);
        $this->assertSame($this->office->id, $transfer->asset->fresh()->location_id);
        $this->assertSame(2, AssetTransfer::count());
    }

    public function test_hr_finishes_a_waiting_return_through_edit_return(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $asset = $this->makeAsset();
        $asset->update(['location_id' => $this->school->id]);
        $out = AssetTransfer::create([
            'asset_id' => $asset->id, 'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'requested_by' => $opm->id, 'transfer_date' => now(), 'status' => 'received',
        ]);
        $back = AssetTransfer::create([
            'asset_id' => $asset->id, 'from_location_id' => $this->school->id, 'to_location_id' => $this->office->id,
            'requested_by' => $opm->id, 'transfer_date' => now(), 'status' => 'pending', 'parent_transfer_id' => $out->id,
        ]);

        $row = collect($this->actingAs($opm)->getJson('/api/asset-transfers')->json())->firstWhere('id', $back->id);
        $this->assertTrue($row['can_complete_return']);
        $this->assertFalse($row['can_confirm']);

        $this->actingAs($opm)->postJson("/api/asset-transfers/{$back->id}/return")->assertOk()->assertJsonPath('status', 'received');
        $this->assertSame($this->office->id, $asset->fresh()->location_id);
    }

    public function test_hr_and_finance_never_accept_or_reject_even_as_a_program_lead(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        // A Finance account that is also the office's program lead.
        $financeLead = $this->responsibleUserAt($this->office, 'Office Lead', 'finance_manager');
        $asset = $this->makeAsset();
        $asset->update(['location_id' => $this->school->id]);
        $toOffice = AssetTransfer::create([
            'asset_id' => $asset->id, 'from_location_id' => $this->school->id, 'to_location_id' => $this->office->id,
            'requested_by' => $opm->id, 'transfer_date' => now(), 'status' => 'pending',
        ]);

        foreach ([$opm, $financeLead] as $user) {
            $row = collect($this->actingAs($user)->getJson('/api/asset-transfers')->json())->firstWhere('id', $toOffice->id);
            $this->assertFalse($row['can_confirm']);
            $this->assertFalse($row['can_decline']);
            $this->actingAs($user)->postJson("/api/asset-transfers/{$toOffice->id}/confirm", ['condition' => 'good'])->assertForbidden();
            $this->actingAs($user)->postJson("/api/asset-transfers/{$toOffice->id}/decline")->assertForbidden();
        }

        // Nor do they count as someone who could accept at that site: with only
        // a Finance lead, the office cannot be sent a new transfer.
        $another = $this->makeAssetAt($this->school, 'PEY-SR-FAF-0077');
        $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $another->id, 'from_location_id' => $this->school->id, 'to_location_id' => $this->office->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_an_asset_cannot_be_returned_twice(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $this->responsibleUserAt($this->office, 'Office Lead');
        $transfer = $this->sendToSchool($opm);
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])->assertStatus(200);

        $finance = User::factory()->create(['role' => 'finance_manager']);
        $this->actingAs($finance)->postJson("/api/asset-transfers/{$transfer->id}/return")->assertStatus(201);
        $this->actingAs($opm)->postJson("/api/asset-transfers/{$transfer->id}/return")->assertStatus(422);
    }

    public function test_a_transfer_the_destination_is_answering_cannot_be_deleted(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $transfer = $this->sendToSchool($opm);

        $this->actingAs($opm)->deleteJson("/api/asset-transfers/{$transfer->id}")->assertStatus(422);
        $this->assertDatabaseHas('asset_transfers', ['id' => $transfer->id]);
    }

    // ---- Transferring to a staff member or program --------------------------

    private function sendToRecipient(User $sender, Asset $asset, string $type, int $id, ?Location $to = null)
    {
        return $this->actingAs($sender)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id,
            'from_location_id' => $this->office->id,
            'to_location_id' => ($to ?? $this->school)->id,
            'transfer_date' => now()->toDateString(),
            'assigned_to_type' => $type,
            'assigned_to_id' => $id,
        ]);
    }

    public function test_a_transfer_to_a_staff_member_assigns_it_only_when_the_site_accepts(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $teacher = $this->staffAt($this->school, 'Teacher');
        $teacherLogin = User::factory()->create(['role' => 'staff', 'staff_id' => $teacher->id]);
        $asset = $this->makeAsset();

        $id = $this->sendToRecipient($opm, $asset, 'staff', $teacher->id)
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('recipient_name', 'Teacher')
            ->json('id');
        $this->assertDatabaseCount('asset_assignments', 0);

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])
            ->assertStatus(200)
            ->assertJsonPath('assignment.status', 'assigned');

        $this->assertSame($this->school->id, $asset->fresh()->location_id);
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $teacher->id,
            'location_id' => $this->school->id, 'status' => 'assigned',
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $teacherLogin->id, 'type' => 'asset_assigned']);
    }

    // ---- Stock quantity (the Dell example) ----------------------------------

    /** $count units of one model at the office, like a tagged register. */
    private function dellStock(int $count): \Illuminate\Support\Collection
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'COM'], ['name' => 'Computer']);

        return collect(range(1, $count))->map(fn ($i) => Asset::create([
            'asset_code' => sprintf('PEY-SR-COM-%04d', $i), 'name' => 'Dell', 'category_id' => $category->id,
            'location_id' => $this->office->id, 'status' => 'active', 'condition' => 'good',
        ]));
    }

    private function stockOf(User $user, Asset $asset): array
    {
        return $this->actingAs($user)->getJson('/api/asset-transfers/stock?asset_id='.$asset->id)->assertOk()->json();
    }

    public function test_quantity_is_checked_against_available_stock(): void
    {
        // Dell total 30 at the Office, 20 already out with a holder there, 10 available.
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $holder = $this->staffAt($this->office, 'Holder');
        $dells = $this->dellStock(30);

        $this->actingAs($opm)->postJson('/api/asset-assignments', [
            'asset_id' => $dells[0]->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $holder->id,
            'location_id' => $this->office->id, 'quantity' => 20, 'assigned_date' => now()->toDateString(),
        ])->assertStatus(201);

        $stock = $this->stockOf($opm, $dells[1]);
        $this->assertSame(
            ['name' => 'Dell', 'total' => 30, 'lost_broken' => 0, 'transferred' => 20, 'available' => 10],
            array_intersect_key($stock, array_flip(['name', 'total', 'lost_broken', 'transferred', 'available']))
        );

        $payload = fn (int $qty) => [
            'asset_id' => $dells[1]->id, 'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(), 'quantity' => $qty,
        ];

        // 11 is more than the 10 available.
        $this->actingAs($opm)->postJson('/api/asset-transfers', $payload(11))->assertStatus(422)->assertJsonValidationErrors('quantity');

        // 10 is exactly what is left.
        $pending = $this->actingAs($opm)->postJson('/api/asset-transfers', $payload(10))->assertStatus(201)->json('id');
        $this->assertSame(0, $this->stockOf($opm, $dells[1])['available']);

        // Rejected: the 10 come back by themselves.
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$pending}/decline", ['rejection_reason' => 'Not needed here'])->assertOk();
        $this->assertSame(10, $this->stockOf($opm, $dells[1])['available']);
    }

    public function test_the_assignment_form_uses_the_same_stock_check(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $holder = $this->staffAt($this->office, 'Holder');
        $dells = $this->dellStock(5);
        $assign = fn (Asset $asset, int $qty) => $this->actingAs($opm)->postJson('/api/asset-assignments', [
            'asset_id' => $asset->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $holder->id,
            'location_id' => $this->office->id, 'quantity' => $qty, 'assigned_date' => now()->toDateString(),
        ]);

        $assign($dells[0], 6)->assertStatus(422)->assertJsonValidationErrors('quantity');
        $assign($dells[0], 3)->assertStatus(201);

        $this->assertSame(2, $this->stockOf($opm, $dells[0])['available']);
        $assign($dells[1], 3)->assertStatus(422);
    }

    public function test_lost_or_broken_units_are_not_available(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $dells = $this->dellStock(12);
        $dells[0]->update(['condition' => 'lost']);
        $dells[1]->update(['condition' => 'broken']);

        $stock = $this->stockOf($opm, $dells[2]);
        $this->assertSame(2, $stock['lost_broken']);
        $this->assertSame(10, $stock['available']);
    }

    public function test_returning_a_transfer_gives_its_units_back(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $officeLead = $this->responsibleUserAt($this->office, 'Office Lead');
        $teacher = $this->staffAt($this->school, 'Teacher');
        $dells = $this->dellStock(15);

        $id = $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $dells[0]->id, 'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(), 'quantity' => 4, 'assigned_to_type' => 'staff', 'assigned_to_id' => $teacher->id,
        ])->assertStatus(201)->json('id');
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])->assertOk();
        $this->assertSame(11, $this->stockOf($opm, $dells[14])['available']);

        // Back in stock as soon as HR records the return.
        $this->actingAs($opm)->postJson("/api/asset-transfers/{$id}/return")->assertStatus(201);
        $this->assertSame(15, $this->stockOf($opm, $dells[14])['available']);
        $this->assertDatabaseHas('asset_assignments', ['assigned_to_id' => $teacher->id, 'status' => 'returned']);
    }

    public function test_a_rejected_transfer_assigns_nobody(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $teacher = $this->staffAt($this->school, 'Teacher');

        $id = $this->sendToRecipient($opm, $this->makeAsset(), 'staff', $teacher->id)->json('id');
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/decline", ['rejection_reason' => 'Not needed here'])->assertStatus(200);

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_the_recipient_must_be_at_the_destination(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $officeStaff = $this->staffAt($this->office, 'Office Staff');

        $this->sendToRecipient($opm, $this->makeAsset(), 'staff', $officeStaff->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('assigned_to_id');
    }

    public function test_only_opm_and_finance_may_transfer_to_a_person(): void
    {
        $this->responsibleUserAt($this->school);
        $teacher = $this->staffAt($this->school, 'Teacher');
        $asset = $this->makeAsset();

        foreach (['executive_director', 'staff'] as $role) {
            $this->sendToRecipient(User::factory()->create(['role' => $role]), $asset, 'staff', $teacher->id)->assertStatus(403);
        }

        $this->sendToRecipient(User::factory()->create(['role' => 'finance_manager']), $asset, 'staff', $teacher->id)->assertStatus(201);
    }

    // ---- Ticked asset codes, reviewed by the recipient ----------------------

    public function test_the_recipient_reviews_the_ticked_codes_then_accepts_all_of_them(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        // The recipient is NOT the site lead, but may still answer.
        $sivelong = $this->staffAt($this->school, 'Nhem Sivelong');
        $sivelongLogin = User::factory()->create(['role' => 'staff', 'staff_id' => $sivelong->id]);
        $dells = $this->dellStock(12);
        $ticked = $dells->take(10);

        $id = $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $dells[0]->id, 'asset_ids' => $ticked->pluck('id')->all(),
            'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(), 'assigned_to_type' => 'staff', 'assigned_to_id' => $sivelong->id,
        ])->assertStatus(201)->assertJsonPath('quantity', 10)->json('id');

        $this->assertDatabaseHas('notifications', ['user_id' => $sivelongLogin->id, 'type' => 'transfer_incoming']);

        // Before answering, Nhem Sivelong sees exactly which ten codes are coming.
        $row = collect($this->actingAs($sivelongLogin)->getJson('/api/asset-transfers')->assertOk()->json())->firstWhere('id', $id);
        $this->assertTrue($row['can_confirm']);
        $this->assertTrue($row['can_decline']);
        $this->assertEqualsCanonicalizing($ticked->pluck('asset_code')->all(), array_column($row['units'], 'asset_code'));

        $this->actingAs($sivelongLogin)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])->assertOk();

        // All ten arrive and each is assigned to them; the other two stay put.
        foreach ($ticked as $unit) {
            $this->assertSame($this->school->id, $unit->fresh()->location_id);
            $this->assertDatabaseHas('asset_assignments', ['asset_id' => $unit->id, 'assigned_to_id' => $sivelong->id, 'quantity' => 1, 'status' => 'assigned']);
        }
        $this->assertSame($this->office->id, $dells[10]->fresh()->location_id);
    }

    public function test_the_receiver_accepts_only_the_codes_that_arrived(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $sivelong = $this->staffAt($this->school, 'Nhem Sivelong');
        $login = User::factory()->create(['role' => 'staff', 'staff_id' => $sivelong->id]);
        $dells = $this->dellStock(10);

        $id = $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $dells[0]->id, 'asset_ids' => $dells->pluck('id')->all(),
            'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(), 'assigned_to_type' => 'staff', 'assigned_to_id' => $sivelong->id,
        ])->assertStatus(201)->json('id');

        // A code that was never on the transfer is refused.
        $stranger = Asset::create([
            'asset_code' => 'PEY-SR-COM-9999', 'name' => 'Dell', 'category_id' => $dells[0]->category_id,
            'location_id' => $this->office->id, 'status' => 'active', 'condition' => 'good',
        ]);
        $this->actingAs($login)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good', 'asset_ids' => [$stranger->id]])
            ->assertStatus(422)->assertJsonValidationErrors('asset_ids');

        // Only 8 of the 10 arrived.
        $arrived = $dells->take(8);
        $missing = $dells->slice(8);
        $this->actingAs($login)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good',
            'asset_ids' => $arrived->pluck('id')->all(), 'rejection_reason' => 'Two were not in the box',
        ])->assertOk()->assertJsonPath('status', 'received');

        foreach ($arrived as $unit) {
            $this->assertSame($this->school->id, $unit->fresh()->location_id);
            $this->assertDatabaseHas('asset_assignments', ['asset_id' => $unit->id, 'assigned_to_id' => $sivelong->id, 'status' => 'assigned']);
            $this->assertDatabaseHas('asset_transfer_items', ['asset_transfer_id' => $id, 'asset_id' => $unit->id, 'status' => 'accepted']);
        }
        foreach ($missing as $unit) {
            $this->assertSame($this->office->id, $unit->fresh()->location_id);
            $this->assertDatabaseMissing('asset_assignments', ['asset_id' => $unit->id]);
            $this->assertDatabaseHas('asset_transfer_items', ['asset_transfer_id' => $id, 'asset_id' => $unit->id, 'status' => 'declined']);
        }
        $this->assertDatabaseHas('notifications', ['user_id' => $opm->id, 'type' => 'transfer_received', 'message' => '8 of 10 × Dell accepted at '.$this->school->name.' by Nhem Sivelong.']);

        // The declined two are free to be sent again.
        $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $missing->first()->id, 'asset_ids' => $missing->pluck('id')->values()->all(),
            'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id, 'transfer_date' => now()->toDateString(),
        ])->assertStatus(201);
    }

    public function test_only_usable_units_of_the_same_model_at_the_from_site_can_be_ticked(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->responsibleUserAt($this->school);
        $dells = $this->dellStock(4);
        $dells[1]->update(['condition' => 'broken']);
        $dells[2]->update(['location_id' => $this->school->id]);
        $chair = $this->makeAsset();

        $send = fn (array $ids) => $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $dells[0]->id, 'asset_ids' => $ids,
            'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id, 'transfer_date' => now()->toDateString(),
        ]);

        foreach ([$dells[1]->id, $dells[2]->id, $chair->id] as $bad) {
            $send([$dells[0]->id, $bad])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
        }

        // A unit already travelling can't be put on a second transfer.
        $send([$dells[0]->id, $dells[3]->id])->assertStatus(201);
        $send([$dells[3]->id])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
    }

    public function test_returning_a_ticked_transfer_brings_every_unit_back(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $officeLead = $this->responsibleUserAt($this->office, 'Office Lead');
        $teacher = $this->staffAt($this->school, 'Teacher');
        $dells = $this->dellStock(3);

        $id = $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $dells[0]->id, 'asset_ids' => $dells->pluck('id')->all(),
            'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(), 'assigned_to_type' => 'staff', 'assigned_to_id' => $teacher->id,
        ])->assertStatus(201)->json('id');
        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])->assertOk();

        $this->actingAs($opm)->postJson("/api/asset-transfers/{$id}/return")->assertStatus(201)
            ->assertJsonCount(3, 'units')->assertJsonPath('status', 'received');

        foreach ($dells as $unit) {
            $this->assertSame($this->office->id, $unit->fresh()->location_id);
        }
        $this->assertSame(0, \App\Models\AssetAssignment::where('assigned_to_id', $teacher->id)->whereIn('status', ['assigned', 'active'])->count());
    }

    public function test_hr_can_change_who_holds_an_accepted_transfers_assets(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $lead = $this->responsibleUserAt($this->school);
        $first = $this->staffAt($this->school, 'First');
        $second = $this->staffAt($this->school, 'Second');
        $elsewhere = $this->staffAt($this->office, 'Office person');
        $dells = $this->dellStock(2);

        $id = $this->actingAs($opm)->postJson('/api/asset-transfers', [
            'asset_id' => $dells[0]->id, 'asset_ids' => $dells->pluck('id')->all(),
            'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'transfer_date' => now()->toDateString(), 'assigned_to_type' => 'staff', 'assigned_to_id' => $first->id,
        ])->assertStatus(201)->json('id');

        // Not while it is still pending.
        $this->actingAs($opm)->putJson("/api/asset-transfers/{$id}/assignment", ['assigned_to_type' => 'staff', 'assigned_to_id' => $second->id])->assertStatus(422);

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])->assertOk();

        // Staff can't; the new holder must be at the destination.
        $this->actingAs($lead)->putJson("/api/asset-transfers/{$id}/assignment", ['assigned_to_type' => 'staff', 'assigned_to_id' => $second->id])->assertForbidden();
        $this->actingAs($opm)->putJson("/api/asset-transfers/{$id}/assignment", ['assigned_to_type' => 'staff', 'assigned_to_id' => $elsewhere->id])
            ->assertStatus(422)->assertJsonValidationErrors('assigned_to_id');

        $this->actingAs($opm)->putJson("/api/asset-transfers/{$id}/assignment", ['assigned_to_type' => 'staff', 'assigned_to_id' => $second->id])
            ->assertOk()->assertJsonPath('recipient_name', 'Second');

        foreach ($dells as $unit) {
            $this->assertDatabaseHas('asset_assignments', ['asset_id' => $unit->id, 'assigned_to_id' => $first->id, 'status' => 'returned']);
            $this->assertDatabaseHas('asset_assignments', ['asset_id' => $unit->id, 'assigned_to_id' => $second->id, 'status' => 'assigned']);
            $this->assertSame($this->school->id, $unit->fresh()->location_id);
        }

        // Clearing it leaves nobody holding them.
        $this->actingAs($opm)->putJson("/api/asset-transfers/{$id}/assignment", ['assigned_to_type' => null])->assertOk();
        $this->assertSame(0, \App\Models\AssetAssignment::whereIn('asset_id', $dells->pluck('id'))->where('status', 'assigned')->count());
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
