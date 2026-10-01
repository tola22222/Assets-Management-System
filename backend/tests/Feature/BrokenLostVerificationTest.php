<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HR / the Accountant verify an asset as broken or lost: the unit keeps its
 * record and code for history, but it is out of use — not available, not
 * assignable, not transferable — and the verification is audited.
 */
class BrokenLostVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Location $office;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Location::where('code', 'SR')->firstOrFail();
        $this->hr = User::factory()->create(['role' => 'operations_hr_manager']);
    }

    /** @return Asset[] Tablade × $n, PEY-SR-TOOL-0001 … */
    private function tablades(int $n): array
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'TOOL'], ['name' => 'Tools']);

        return array_map(fn ($i) => Asset::create([
            'asset_code' => sprintf('PEY-SR-TOOL-%04d', $i), 'name' => 'Tablade', 'category_id' => $category->id,
            'location_id' => $this->office->id, 'status' => 'active', 'condition' => 'good',
        ]), range(1, $n));
    }

    private function scanVerify(Asset $unit, string $condition, ?string $remark = 'Screen cracked')
    {
        return $this->actingAs($this->hr)->postJson("/api/qr-scan/{$unit->asset_code}/verify", array_filter([
            'location_id' => $this->office->id, 'condition' => $condition, 'remark' => $remark,
        ]));
    }

    private function stock(Asset $unit): array
    {
        return $this->actingAs($this->hr)->getJson("/api/asset-transfers/stock?asset_id={$unit->id}&location_id={$this->office->id}")->assertOk()->json();
    }

    public function test_one_broken_tablade_leaves_nine_in_use_and_keeps_its_code(): void
    {
        $units = $this->tablades(10);
        $broken = $units[9];

        $this->scanVerify($broken, 'broken')->assertOk();

        $stock = $this->stock($units[0]);
        $this->assertSame(10, $stock['total']);
        $this->assertSame(1, $stock['lost_broken']);
        $this->assertSame(9, $stock['available']);
        $this->assertNotContains($broken->id, $stock['available_ids']);

        // Never deleted: same row, same code, just out of use.
        $this->assertDatabaseHas('assets', ['id' => $broken->id, 'asset_code' => 'PEY-SR-TOOL-0010', 'condition' => 'broken', 'status' => 'active']);
    }

    public function test_the_verification_is_audited(): void
    {
        [$unit] = $this->tablades(1);

        $this->scanVerify($unit, 'lost', 'Missing after the workshop')->assertOk();

        $verification = AssetVerification::where('asset_id', $unit->id)->latest('id')->firstOrFail();
        $this->assertSame('lost', $verification->condition);
        $this->assertSame('good', $verification->previous_condition);
        $this->assertSame(1, (int) $verification->quantity_affected);
        $this->assertSame($this->hr->id, (int) $verification->verified_by);
        $this->assertNotNull($verification->verified_at);
        $this->assertSame('Missing after the workshop', $verification->remark);
    }

    public function test_a_reason_is_required_for_broken_or_lost_only(): void
    {
        [$unit] = $this->tablades(1);

        $this->scanVerify($unit, 'broken', null)->assertStatus(422)->assertJsonValidationErrors('remark');
        $this->assertSame('good', $unit->fresh()->condition);

        $this->scanVerify($unit, 'good', null)->assertOk();
    }

    public function test_all_broken_means_none_available_and_every_code_kept(): void
    {
        $units = $this->tablades(3);
        foreach ($units as $unit) {
            $this->scanVerify($unit, 'broken')->assertOk();
        }

        $this->assertSame(0, $this->stock($units[0])['available']);
        $this->assertSame(3, Asset::where('name', 'Tablade')->count());
    }

    public function test_a_broken_unit_cannot_be_transferred_or_assigned(): void
    {
        $units = $this->tablades(2);
        $this->scanVerify($units[0], 'broken')->assertOk();
        $other = Location::where('code', '!=', 'SR')->firstOrFail();

        $this->actingAs($this->hr)->postJson('/api/asset-transfers', [
            'asset_id' => $units[0]->id, 'asset_ids' => [$units[0]->id],
            'from_location_id' => $this->office->id, 'to_location_id' => $other->id,
            'transfer_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('asset_ids');

        $holder = Staff::create(['full_name' => 'Holder', 'phone' => '012', 'location_id' => $this->office->id]);
        $this->actingAs($this->hr)->postJson('/api/asset-assignments', [
            'asset_id' => $units[0]->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $holder->id,
            'location_id' => $this->office->id, 'quantity' => 1, 'assigned_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_an_assigned_unit_that_breaks_is_not_subtracted_twice(): void
    {
        $units = $this->tablades(2);
        $holder = Staff::create(['full_name' => 'Holder', 'phone' => '012', 'location_id' => $this->office->id]);
        $this->actingAs($this->hr)->postJson('/api/asset-assignments', [
            'asset_id' => $units[0]->id, 'assigned_to_type' => 'staff', 'assigned_to_id' => $holder->id,
            'location_id' => $this->office->id, 'quantity' => 1, 'assigned_date' => now()->toDateString(),
        ])->assertCreated();

        $this->scanVerify($units[0], 'broken')->assertOk();

        $stock = $this->stock($units[1]);
        $this->assertSame([2, 1, 0, 1], [$stock['total'], $stock['lost_broken'], $stock['transferred'], $stock['available']]);
    }

    public function test_repaired_good_again_comes_back_into_use(): void
    {
        $units = $this->tablades(2);
        $this->scanVerify($units[0], 'broken')->assertOk();
        $this->assertSame(1, $this->stock($units[1])['available']);

        $this->scanVerify($units[0], 'good', null)->assertOk();
        $this->assertSame(2, $this->stock($units[1])['available']);
        $this->assertSame(0, (int) AssetVerification::where('asset_id', $units[0]->id)->latest('id')->first()->quantity_affected);
    }
}
