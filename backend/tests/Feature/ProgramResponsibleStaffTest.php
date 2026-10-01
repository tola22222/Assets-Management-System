<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Program → Schools → Staff.
 *
 * A program links to one or more schools and has one Responsible Staff; a
 * staff member belongs to exactly one program and can see and manage every
 * school it is linked to. AssetTransferController reads the same chain to
 * decide who may accept a delivery at a school.
 */
class ProgramResponsibleStaffTest extends TestCase
{
    use RefreshDatabase;

    private Location $school;

    private Location $otherSchool;

    private User $opm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = Location::where('code', '!=', 'SR')->orderBy('id')->firstOrFail();
        $this->otherSchool = Location::where('code', '!=', 'SR')->whereKeyNot($this->school->id)->orderBy('id')->firstOrFail();
        $this->opm = User::factory()->create(['role' => 'operations_hr_manager']);
    }

    private function staff(string $name = 'Program Lead'): Staff
    {
        return Staff::create(['full_name' => $name, 'phone' => '012345678']);
    }

    private function createProgram(string $name, array $schools, Staff $lead)
    {
        return $this->actingAs($this->opm)->postJson('/api/programs', [
            'name' => $name,
            'location_ids' => array_map(fn (Location $l) => $l->id, $schools),
            'responsible_staff_id' => $lead->id,
        ]);
    }

    private function chair(Location $at, string $code): Asset
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'FAF'], ['name' => 'Furniture & Fixture']);

        return Asset::create([
            'asset_code' => $code, 'name' => 'Chair', 'category_id' => $category->id,
            'location_id' => $at->id, 'status' => 'active', 'condition' => 'good',
        ]);
    }

    public function test_a_program_cannot_be_created_without_schools_and_responsible_staff(): void
    {
        $this->actingAs($this->opm)
            ->postJson('/api/programs', ['name' => 'Dream Management'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_ids', 'responsible_staff_id']);
    }

    public function test_a_program_links_to_several_schools_and_its_lead_joins_it(): void
    {
        $lead = $this->staff();

        $id = $this->createProgram('Dream Management', [$this->school, $this->otherSchool], $lead)
            ->assertStatus(201)
            ->assertJsonPath('responsible_staff_id', $lead->id)
            ->assertJsonCount(2, 'locations')
            ->json('id');

        $this->assertSame($id, $lead->fresh()->program_id);
    }

    public function test_a_staff_member_belongs_to_only_one_program(): void
    {
        $lead = $this->staff();
        $this->createProgram('Dream Management', [$this->school], $lead)->assertStatus(201);

        // Already in Dream Management: cannot lead (and so join) another one.
        $this->createProgram('Scholarship', [$this->otherSchool], $lead)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['responsible_staff_id']);

        $this->assertDatabaseMissing('programs', ['name' => 'Scholarship']);
    }

    public function test_a_program_can_be_edited_without_giving_up_its_own_lead(): void
    {
        // The uniqueness check has to ignore the row being edited, or saving a
        // description change would fail against the program's own lead.
        $lead = $this->staff();
        $id = $this->createProgram('Dream Management', [$this->school], $lead)->assertStatus(201)->json('id');

        $this->actingAs($this->opm)
            ->putJson("/api/programs/{$id}", [
                'name' => 'Dream Management',
                'location_ids' => [$this->school->id, $this->otherSchool->id],
                'responsible_staff_id' => $lead->id,
                'description' => 'Now with a description',
            ])
            ->assertStatus(200)
            ->assertJsonPath('description', 'Now with a description')
            ->assertJsonCount(2, 'locations');
    }

    public function test_editing_a_program_cannot_drop_its_schools_or_lead(): void
    {
        $id = $this->createProgram('Dream Management', [$this->school], $this->staff())->json('id');

        $this->actingAs($this->opm)
            ->putJson("/api/programs/{$id}", ['name' => 'Dream Management'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_ids', 'responsible_staff_id']);
    }

    public function test_hr_assigns_a_staff_member_to_a_program_not_a_school(): void
    {
        $id = $this->createProgram('Dream Management', [$this->school, $this->otherSchool], $this->staff())->json('id');

        $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'No Program'])
            ->assertStatus(422)->assertJsonValidationErrors('program_id');

        // No school picked: their base school defaults to the program's first.
        $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'Teacher', 'program_id' => $id])
            ->assertCreated()
            ->assertJsonPath('program_id', $id)
            ->assertJsonPath('location_id', $this->school->id);
    }

    public function test_a_programs_lead_cannot_be_moved_to_another_program(): void
    {
        $lead = $this->staff();
        $dream = $this->createProgram('Dream Management', [$this->school], $lead)->json('id');
        $other = $this->createProgram('Scholarship', [$this->otherSchool], $this->staff('Other Lead'))->json('id');

        $this->actingAs($this->opm)->putJson("/api/staff/{$lead->id}", [
            'full_name' => $lead->full_name, 'status' => 'active', 'program_id' => $other,
        ])->assertStatus(422)->assertJsonValidationErrors('program_id');

        $this->assertSame($dream, $lead->fresh()->program_id);
    }

    public function test_program_staff_see_and_manage_every_school_of_their_program(): void
    {
        $here = $this->chair($this->school, 'PEY-SR-FAF-0001');
        $there = $this->chair($this->otherSchool, 'PEY-SR-FAF-0002');
        $office = $this->chair(Location::where('code', 'SR')->firstOrFail(), 'PEY-SR-FAF-0003');

        $programId = $this->createProgram('Dream Management', [$this->school, $this->otherSchool], $this->staff())->json('id');
        $member = Staff::create(['full_name' => 'Teacher', 'program_id' => $programId]);
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $member->id]);

        $ids = collect($this->actingAs($user)->getJson('/api/assets')->assertOk()->json())->pluck('id')->sort()->values()->all();
        $this->assertSame([$here->id, $there->id], $ids);
        $this->actingAs($user)->getJson("/api/assets/{$office->id}")->assertNotFound();

        $this->assertEqualsCanonicalizing(
            [$this->school->id, $this->otherSchool->id],
            collect($this->actingAs($user)->getJson('/api/locations')->json())->pluck('id')->all()
        );
        $this->assertSame([$programId], collect($this->actingAs($user)->getJson('/api/programs')->json())->pluck('id')->all());
    }

    public function test_the_program_lead_accepts_transfers_at_any_of_its_schools(): void
    {
        $leadStaff = $this->staff();
        $this->createProgram('Dream Management', [$this->school, $this->otherSchool], $leadStaff)->assertStatus(201);
        $lead = User::factory()->create(['role' => 'staff', 'staff_id' => $leadStaff->id]);
        $asset = $this->chair(Location::where('code', 'SR')->firstOrFail(), 'PEY-SR-FAF-0009');

        // Sent to the program's SECOND school — its lead can still accept.
        $id = $this->actingAs($this->opm)->postJson('/api/asset-transfers', [
            'asset_id' => $asset->id, 'from_location_id' => $asset->location_id,
            'to_location_id' => $this->otherSchool->id, 'transfer_date' => now()->toDateString(),
        ])->assertCreated()->json('id');

        $this->actingAs($lead)->postJson("/api/asset-transfers/{$id}/confirm", ['condition' => 'good'])->assertOk();
        $this->assertSame($this->otherSchool->id, $asset->fresh()->location_id);
    }
}
