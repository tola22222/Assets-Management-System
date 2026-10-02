<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Program;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A staff user sees and acts on only the location HR assigned them — on every
 * sidebar page — and nothing at all until that location is set (fail closed).
 * A Project Coordinator (a program's Responsible Staff) sees only their own
 * program. OPM, Finance and the ED are never restricted.
 */
class StaffScopeTest extends TestCase
{
    use RefreshDatabase;

    private Location $office;

    private Location $school;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Location::where('code', 'SR')->firstOrFail();
        $this->school = Location::where('code', '!=', 'SR')->whereNotNull('code')->firstOrFail();
    }

    private function asset(Location $at, string $name = 'Chair'): Asset
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'FAF'], ['name' => 'Furniture & Fixture']);

        return Asset::create([
            'asset_code' => sprintf('PEY-SR-FAF-%04d', ++$this->seq), 'name' => $name,
            'category_id' => $category->id, 'location_id' => $at->id, 'status' => 'active', 'condition' => 'good',
        ]);
    }

    /** @return array{0: User, 1: Staff} */
    private function staffAt(?Location $at, string $name = 'Staff'): array
    {
        $staff = Staff::create(['full_name' => $name, 'phone' => '0', 'location_id' => $at?->id]);

        return [User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]), $staff];
    }

    private function ids(User $user, string $url, string $key = 'id'): array
    {
        return collect($this->actingAs($user)->getJson($url)->assertOk()->json())->pluck($key)->sort()->values()->all();
    }

    public function test_staff_see_everything_at_their_location_and_nothing_elsewhere(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        [$staff] = $this->staffAt($this->school);
        $here = $this->asset($this->school);
        $alsoHere = $this->asset($this->school);
        $elsewhere = $this->asset($this->office);

        $this->assertSame([$here->id, $alsoHere->id], $this->ids($staff, '/api/assets'));
        $this->actingAs($staff)->getJson("/api/assets/{$elsewhere->id}")->assertNotFound();

        // Transfers: only those arriving at or leaving their site.
        $in = AssetTransfer::create([
            'asset_id' => $elsewhere->id, 'from_location_id' => $this->office->id, 'to_location_id' => $this->school->id,
            'requested_by' => $opm->id, 'transfer_date' => now(), 'status' => 'pending',
        ]);
        $unrelated = Location::whereNotIn('id', [$this->office->id, $this->school->id])->firstOrFail();
        AssetTransfer::create([
            'asset_id' => $elsewhere->id, 'from_location_id' => $this->office->id, 'to_location_id' => $unrelated->id,
            'requested_by' => $opm->id, 'transfer_date' => now(), 'status' => 'rejected',
        ]);
        $this->assertSame([$in->id], $this->ids($staff, '/api/asset-transfers'));

        // Verifications recorded at their site only.
        foreach ([[$here, $this->school], [$elsewhere, $this->office]] as [$asset, $site]) {
            AssetVerification::create([
                'asset_id' => $asset->id, 'location_id' => $site->id, 'verified_by' => $opm->id,
                'quantity_verified' => 1, 'condition' => 'good', 'verified_at' => now(),
            ]);
        }
        $this->assertSame([$here->id], $this->ids($staff, '/api/asset-verifications', 'asset_id'));
    }

    public function test_the_stock_page_and_site_list_show_only_their_site(): void
    {
        [$staff] = $this->staffAt($this->school);
        $this->asset($this->school);
        $this->asset($this->office);

        $rows = $this->actingAs($staff)->getJson('/api/stock-items/by-location')->assertOk()->json();
        $this->assertSame([[$this->school->id, 1]], array_map(fn ($r) => [$r['location_id'], $r['total']], $rows));

        $mine = StockItem::create(['stock_code' => 'PEY-STK-0001', 'name' => 'Paper', 'unit' => 'box', 'balance' => 5, 'location_id' => $this->school->id]);
        $theirs = StockItem::create(['stock_code' => 'PEY-STK-0002', 'name' => 'Toner', 'unit' => 'box', 'balance' => 5, 'location_id' => $this->office->id]);
        $this->assertSame([$mine->id], $this->ids($staff, '/api/stock-items'));
        $this->actingAs($staff)->getJson("/api/stock-items/{$theirs->id}")->assertNotFound();

        // Locations page: their site only. Transfer "To" list: every site's name.
        $this->assertSame([$this->school->id], $this->ids($staff, '/api/locations'));
        $this->assertCount(Location::count(), $this->ids($staff, '/api/locations?scope=destinations'));
        $this->actingAs($staff)->getJson("/api/locations/{$this->office->id}")->assertNotFound();
    }

    public function test_the_transfer_stock_box_counts_only_their_site(): void
    {
        [$staff] = $this->staffAt($this->school);
        $here = $this->asset($this->school, 'Dell');
        $this->asset($this->school, 'Dell');
        $this->asset($this->office, 'Dell');

        $stock = $this->actingAs($staff)->getJson('/api/asset-transfers/stock?asset_id='.$here->id)->assertOk()->json();
        $this->assertSame(2, $stock['total']);
    }

    public function test_staff_see_only_the_program_they_belong_to(): void
    {
        [$lead, $leadStaff] = $this->staffAt($this->school, 'Coordinator');
        [$staff, $staffRow] = $this->staffAt($this->school);
        [, $otherLead] = $this->staffAt($this->school, 'Other lead');
        [, $officeLead] = $this->staffAt($this->office, 'Office lead');

        $dream = Program::create(['name' => 'Dream', 'location_id' => $this->school->id, 'responsible_staff_id' => $leadStaff->id]);
        Program::create(['name' => 'Other', 'location_id' => $this->school->id, 'responsible_staff_id' => $otherLead->id]);
        Program::create(['name' => 'Office', 'location_id' => $this->office->id, 'responsible_staff_id' => $officeLead->id]);
        $staffRow->update(['program_id' => $dream->id]);

        // The lead and a member of Dream each see Dream only — not the other
        // program running at the same school.
        $this->assertSame([$dream->id], $this->ids($lead, '/api/programs'));
        $this->assertSame([$dream->id], $this->ids($staff, '/api/programs'));
    }

    public function test_categories_show_only_their_sites_and_suppliers_are_hidden(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        [$staff] = $this->staffAt($this->school);
        $this->asset($this->school);
        $this->asset($this->school);
        $this->asset($this->office); // same category, other site
        $unused = AssetCategory::create(['name' => 'Motor & Vehicle', 'short_name' => 'MOV']);

        $rows = $this->actingAs($staff)->getJson('/api/categories')->assertOk()->json();
        $this->assertSame([['FAF', 2]], array_map(fn ($c) => [$c['short_name'], $c['assets_count']], $rows));
        $this->assertCount(2, $this->ids($opm, '/api/categories'));
        $this->assertNotContains($unused->id, array_column($rows, 'id'));

        // Suppliers: not in the staff role's default permissions, so hidden.
        $this->actingAs($staff)->getJson('/api/suppliers')->assertForbidden();
        $this->assertNotContains('suppliers', array_keys($this->actingAs($staff)->getJson('/api/me/permissions')->json('permissions')));
        $this->actingAs($opm)->getJson('/api/suppliers')->assertOk();
    }

    public function test_staff_get_the_hr_dashboard_counted_over_their_location_only(): void
    {
        [$staff] = $this->staffAt($this->school);
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->asset($this->school);
        $this->asset($this->school);
        $this->asset($this->office);

        $mine = $this->actingAs($staff)->getJson('/api/dashboard')->assertOk();
        $mine->assertJsonPath('total_assets', 2)->assertJsonPath('total_locations', 1)->assertJsonPath('recent_activity', []);
        $this->assertSame([$this->school->name], array_column($mine->json('assets_by_location'), 'location'));
        $this->assertCount(2, $mine->json('recent_assets'));
        $this->assertArrayNotHasKey('my_assignments', $mine->json());

        // The trend chart is theirs too, counting their site only.
        $trend = $this->actingAs($staff)->getJson('/api/dashboard/by-period?period=day')->assertOk()->json('data');
        $this->assertSame(2, array_sum(array_column($trend, 'count')));

        $this->actingAs($opm)->getJson('/api/dashboard')->assertJsonPath('total_assets', 3);
    }

    public function test_staff_with_no_location_see_nothing(): void
    {
        [$staff] = $this->staffAt(null, 'Unassigned');
        $this->asset($this->office);
        StockItem::create(['stock_code' => 'PEY-STK-0001', 'name' => 'Paper', 'unit' => 'box', 'balance' => 5, 'location_id' => $this->office->id]);

        foreach (['/api/assets', '/api/asset-transfers', '/api/asset-verifications', '/api/programs', '/api/stock-items', '/api/stock-items/by-location', '/api/locations', '/api/staff'] as $url) {
            $this->assertSame([], $this->ids($staff, $url), $url);
        }
    }

    public function test_hr_must_give_a_new_staff_member_a_location(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        [, $lead] = $this->staffAt($this->school, 'Lead');
        $program = Program::create(['name' => 'Dream', 'location_id' => $this->school->id, 'responsible_staff_id' => $lead->id]);

        $this->actingAs($opm)->postJson('/api/staff', ['full_name' => 'No Location'])
            ->assertStatus(422)->assertJsonValidationErrors('location_ids');
        // The program comes from the location.
        $this->actingAs($opm)->postJson('/api/staff', ['full_name' => 'Has Location', 'location_id' => $this->school->id])
            ->assertCreated()->assertJsonPath('program_id', $program->id);

        // Nor can an edit take it away again.
        $staff = Staff::where('full_name', 'Has Location')->firstOrFail();
        $this->actingAs($opm)->putJson("/api/staff/{$staff->id}", ['full_name' => 'Has Location', 'status' => 'active', 'location_ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('location_ids');
    }

    public function test_other_roles_are_not_restricted(): void
    {
        $finance = User::factory()->create(['role' => 'finance_manager']);
        $a = $this->asset($this->office);
        $b = $this->asset($this->school);

        $this->assertSame([$a->id, $b->id], $this->ids($finance, '/api/assets'));
        $this->assertCount(Location::count(), $this->ids($finance, '/api/locations'));
    }
}
