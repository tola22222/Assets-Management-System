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
 * Every location — Office or school — holds its own stock. A transfer takes
 * units out of the source and, once accepted, adds them to the destination;
 * a location can only send or assign what IT has available.
 */
class LocationStockTest extends TestCase
{
    use RefreshDatabase;

    private Location $office;

    private Location $kralanh;

    private Location $other;

    private User $hr;

    /** @var array<int, User> location id => the user who accepts there */
    private array $leads = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Location::where('code', 'SR')->firstOrFail();
        [$this->kralanh, $this->other] = Location::where('code', '!=', 'SR')->orderBy('id')->take(2)->get()->all();
        $this->hr = User::factory()->create(['role' => 'operations_hr_manager']);

        foreach ([$this->office, $this->kralanh, $this->other] as $location) {
            $this->leads[$location->id] = $this->leadAt($location);
        }
    }

    private function leadAt(Location $location): User
    {
        $staff = Staff::create(['full_name' => 'Lead '.$location->id, 'phone' => '012345678', 'location_id' => $location->id]);
        Program::create(['name' => 'Program '.$location->id, 'location_id' => $location->id, 'responsible_staff_id' => $staff->id]);

        return User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
    }

    /** @return Asset[] */
    private function iphonesAtOffice(int $n): array
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'COM'], ['name' => 'Computer']);

        return array_map(fn ($i) => Asset::create([
            'asset_code' => sprintf('PEY-SR-COM-%04d', $i), 'name' => 'iPhone 18 Pro Max', 'category_id' => $category->id,
            'location_id' => $this->office->id, 'status' => 'active', 'condition' => 'good',
        ]), range(1, $n));
    }

    private function stockAt(Asset $model, Location $location): array
    {
        return $this->actingAs($this->hr)
            ->getJson("/api/asset-transfers/stock?asset_id={$model->id}&location_id={$location->id}")
            ->assertOk()->json();
    }

    /** @param Asset[] $units */
    private function send(array $units, Location $from, Location $to): AssetTransfer
    {
        $id = $this->actingAs($this->hr)->postJson('/api/asset-transfers', [
            'asset_id' => $units[0]->id,
            'asset_ids' => array_map(fn ($u) => $u->id, $units),
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'transfer_date' => now()->toDateString(),
        ])->assertCreated()->json('id');

        return AssetTransfer::findOrFail($id);
    }

    private function accept(AssetTransfer $transfer): void
    {
        $this->actingAs($this->leads[$transfer->to_location_id])
            ->postJson("/api/asset-transfers/{$transfer->id}/confirm", ['condition' => 'good'])->assertOk();
    }

    public function test_office_sends_two_iphones_to_kralanh_and_each_location_keeps_its_own_count(): void
    {
        $phones = $this->iphonesAtOffice(10);
        $model = $phones[0];

        $this->assertSame([10, 0, 10], $this->counts($this->stockAt($model, $this->office)));
        $this->assertSame([0, 0, 0], $this->counts($this->stockAt($model, $this->kralanh)));

        $transfer = $this->send([$phones[0], $phones[1]], $this->office, $this->kralanh);

        // Waiting on Kralanh: still at the Office, but no longer available there.
        $this->assertSame([10, 2, 8], $this->counts($this->stockAt($model, $this->office)));

        $this->accept($transfer);

        $this->assertSame([8, 0, 8], $this->counts($this->stockAt($model, $this->office)));
        $this->assertSame([2, 0, 2], $this->counts($this->stockAt($model, $this->kralanh)));
        $this->assertEqualsCanonicalizing([$phones[0]->id, $phones[1]->id], $this->stockAt($model, $this->kralanh)['available_ids']);
    }

    public function test_kralanh_assigns_both_and_then_has_none_left_to_send(): void
    {
        $phones = $this->iphonesAtOffice(10);
        $transfer = $this->send([$phones[0], $phones[1]], $this->office, $this->kralanh);
        $this->accept($transfer);

        // Kralanh hands both to a staff member there (Edit → Assignment).
        $teacher = Staff::create(['full_name' => 'Kralanh Teacher', 'phone' => '012', 'location_id' => $this->kralanh->id]);
        $this->actingAs($this->hr)->putJson("/api/asset-transfers/{$transfer->id}/assignment", [
            'assigned_to_type' => 'staff', 'assigned_to_id' => $teacher->id,
        ])->assertOk();

        $this->assertSame([2, 2, 0], $this->counts($this->stockAt($phones[0], $this->kralanh)));
        // The Office's 8 spare phones never cover Kralanh.
        $this->assertSame([8, 0, 8], $this->counts($this->stockAt($phones[0], $this->office)));

        // Neither the assigned codes nor a bare quantity can leave Kralanh.
        $this->actingAs($this->hr)->postJson('/api/asset-transfers', [
            'asset_id' => $phones[0]->id, 'asset_ids' => [$phones[0]->id],
            'from_location_id' => $this->kralanh->id, 'to_location_id' => $this->other->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('asset_ids');

        $this->actingAs($this->hr)->postJson('/api/asset-transfers', [
            'asset_id' => $phones[0]->id, 'quantity' => 1,
            'from_location_id' => $this->kralanh->id, 'to_location_id' => $this->other->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_school_to_office_and_school_to_school_move_stock_the_same_way(): void
    {
        $phones = $this->iphonesAtOffice(10);
        $model = $phones[0];
        $this->accept($this->send([$phones[0], $phones[1]], $this->office, $this->kralanh));

        // School → Office.
        $this->accept($this->send([$phones[0]], $this->kralanh, $this->office));
        $this->assertSame(9, $this->stockAt($model, $this->office)['total']);
        $this->assertSame(1, $this->stockAt($model, $this->kralanh)['total']);

        // School → School.
        $this->accept($this->send([$phones[1]], $this->kralanh, $this->other));
        $this->assertSame(0, $this->stockAt($model, $this->kralanh)['total']);
        $this->assertSame([1, 0, 1], $this->counts($this->stockAt($model, $this->other)));
        $this->assertSame($this->other->id, $phones[1]->fresh()->location_id);
    }

    public function test_a_location_cannot_send_more_than_it_has(): void
    {
        $phones = $this->iphonesAtOffice(3);
        $this->accept($this->send([$phones[0]], $this->office, $this->kralanh));

        // Kralanh has 1; the 2 still at the Office can't be sent from Kralanh.
        $this->actingAs($this->hr)->postJson('/api/asset-transfers', [
            'asset_id' => $phones[0]->id, 'asset_ids' => [$phones[0]->id, $phones[1]->id],
            'from_location_id' => $this->kralanh->id, 'to_location_id' => $this->other->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
    }

    public function test_transfer_history_records_source_destination_asset_quantity_date_and_user(): void
    {
        $phones = $this->iphonesAtOffice(4);
        $transfer = $this->send([$phones[0], $phones[1]], $this->office, $this->kralanh);
        $this->accept($transfer);

        $row = collect($this->actingAs($this->hr)->getJson('/api/asset-transfers')->assertOk()->json())
            ->firstWhere('id', $transfer->id);

        $this->assertSame($this->office->name, $row['from_location']['name']);
        $this->assertSame($this->kralanh->name, $row['to_location']['name']);
        $this->assertSame('iPhone 18 Pro Max', $row['asset']['name']);
        $this->assertSame(2, (int) $row['quantity']);
        $this->assertSame(now()->toDateString(), substr($row['transfer_date'], 0, 10));
        $this->assertSame($this->hr->name, $row['requester']['name']);
        $this->assertSame($this->leads[$this->kralanh->id]->name, $row['receiver']['name']);
    }

    public function test_verification_confirms_an_asset_where_the_register_has_it(): void
    {
        [$phone] = $this->iphonesAtOffice(1);

        $this->actingAs($this->hr)->postJson('/api/asset-verifications', [
            'asset_id' => $phone->id, 'location_id' => $this->kralanh->id, 'quantity_verified' => 1, 'condition' => 'good',
        ])->assertStatus(422)->assertJsonValidationErrors('location_id');

        $this->actingAs($this->hr)->postJson('/api/asset-verifications', [
            'asset_id' => $phone->id, 'location_id' => $this->office->id, 'quantity_verified' => 1, 'condition' => 'good',
        ])->assertCreated();
    }

    public function test_an_assignment_is_made_where_the_asset_is(): void
    {
        [$phone] = $this->iphonesAtOffice(1);
        $teacher = Staff::create(['full_name' => 'Kralanh Teacher', 'phone' => '012', 'location_id' => $this->kralanh->id]);
        $payload = [
            'asset_id' => $phone->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $teacher->id,
            'quantity' => 1, 'assigned_date' => now()->toDateString(),
        ];

        $this->actingAs($this->hr)->postJson('/api/asset-assignments', $payload + ['location_id' => $this->kralanh->id])
            ->assertStatus(422)->assertJsonValidationErrors('location_id');

        $this->actingAs($this->hr)->postJson('/api/asset-assignments', $payload + ['location_id' => $this->office->id])
            ->assertCreated();
        $this->assertSame([1, 1, 0], $this->counts($this->stockAt($phone, $this->office)));
    }

    public function test_staff_must_pick_a_condition_to_accept_and_it_is_recorded(): void
    {
        $phones = $this->iphonesAtOffice(2);
        $teacher = Staff::create(['full_name' => 'Kralanh Teacher', 'phone' => '012', 'location_id' => $this->kralanh->id]);
        $teacherLogin = User::factory()->create(['role' => 'staff', 'staff_id' => $teacher->id]);

        $id = $this->actingAs($this->hr)->postJson('/api/asset-transfers', [
            'asset_id' => $phones[0]->id, 'asset_ids' => [$phones[0]->id, $phones[1]->id],
            'from_location_id' => $this->office->id, 'to_location_id' => $this->kralanh->id,
            'transfer_date' => now()->toDateString(), 'assigned_to_type' => 'staff', 'assigned_to_id' => $teacher->id,
        ])->assertCreated()->assertJsonPath('status', 'pending')->json('id');

        // No condition, or one outside New / Good / Fair: refused, nothing moves.
        $this->actingAs($teacherLogin)->postJson("/api/asset-transfers/{$id}/confirm")->assertStatus(422)->assertJsonValidationErrors('condition');
        $this->actingAs($teacherLogin)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'broken'])->assertStatus(422);
        $this->assertSame($this->office->id, $phones[0]->fresh()->location_id);

        $this->actingAs($teacherLogin)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'new'])
            ->assertOk()
            ->assertJsonPath('status', 'received')
            ->assertJsonPath('verification_status', 'accepted')
            ->assertJsonPath('received_condition', 'new')
            ->assertJsonPath('verifier.id', $teacherLogin->id);

        $transfer = AssetTransfer::findOrFail($id);
        $this->assertNotNull($transfer->verified_at);
        // The sender's bell says who accepted it, by their staff name.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->hr->id, 'type' => 'transfer_received',
            'message' => "iPhone 18 Pro Max was accepted at {$this->kralanh->name} by Kralanh Teacher.",
        ]);
        $this->assertDatabaseHas('asset_assignments', ['asset_id' => $phones[0]->id, 'assigned_to_id' => $teacher->id, 'condition' => 'new']);
        $this->assertDatabaseHas('asset_assignments', ['asset_id' => $phones[1]->id, 'assigned_to_id' => $teacher->id, 'condition' => 'new']);
    }

    public function test_staff_must_give_a_reason_to_reject_and_it_is_recorded(): void
    {
        [$phone] = $this->iphonesAtOffice(1);
        $transfer = $this->send([$phone], $this->office, $this->kralanh);
        $lead = $this->leads[$this->kralanh->id];

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/decline")
            ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');
        $this->assertSame('pending', $transfer->fresh()->status);

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$transfer->id}/decline", ['rejection_reason' => 'Damaged on arrival'])
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('verification_status', 'rejected')
            ->assertJsonPath('rejection_reason', 'Damaged on arrival')
            ->assertJsonPath('verifier.id', $lead->id);

        $this->assertNotNull($transfer->fresh()->verified_at);
        $this->assertSame($this->office->id, $phone->fresh()->location_id);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->hr->id, 'type' => 'transfer_rejected',
            'message' => "Lead {$this->kralanh->id} at {$this->kralanh->name} rejected the transfer of iPhone 18 Pro Max.",
        ]);
    }

    /** @return int[] [total, transferred (assigned + on the way out), available] */
    private function counts(array $stock): array
    {
        return [$stock['total'], $stock['transferred'], $stock['available']];
    }
}
