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

    public function test_a_program_is_created_on_its_own(): void
    {
        // Program → Location → Staff: no schools or lead are picked here.
        $this->actingAs($this->opm)
            ->postJson('/api/programs', ['name' => 'Dream Management'])
            ->assertStatus(201)
            ->assertJsonPath('responsible_staff_id', null)
            ->assertJsonCount(0, 'locations');
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

    public function test_editing_a_program_keeps_its_schools_and_lead(): void
    {
        $lead = $this->staff();
        $id = $this->createProgram('Dream Management', [$this->school], $lead)->json('id');

        // Schools are set from the Location form, so an edit that does not
        // send them leaves them alone — and the lead with them.
        $this->actingAs($this->opm)
            ->putJson("/api/programs/{$id}", ['name' => 'Dream Management', 'description' => 'Edited'])
            ->assertStatus(200)
            ->assertJsonPath('responsible_staff_id', $lead->id)
            ->assertJsonCount(1, 'locations');
    }

    public function test_staff_take_their_program_from_their_locations(): void
    {
        $id = $this->createProgram('Dream Management', [$this->school, $this->otherSchool], $this->staff())->json('id');

        // At least one location is required…
        $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'No Location'])
            ->assertStatus(422)->assertJsonValidationErrors('location_ids');

        // …several of the same program are fine, and the program comes from
        // them, whatever program was sent.
        $other = $this->createProgram('Scholarship', [], $this->staff('Other Lead'))->json('id');
        $teacher = $this->actingAs($this->opm)->postJson('/api/staff', [
            'full_name' => 'Teacher', 'location_ids' => [$this->school->id, $this->otherSchool->id], 'program_id' => $other,
        ])->assertCreated()
            ->assertJsonPath('program_id', $id)
            ->assertJsonPath('location_id', $this->school->id)
            ->json('id');
        $this->assertEqualsCanonicalizing([$this->school->id, $this->otherSchool->id], Staff::find($teacher)->locations()->pluck('locations.id')->all());

        // A location in no program can't take staff yet.
        $office = Location::where('code', 'SR')->firstOrFail();
        $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'Office Staff', 'location_ids' => [$office->id]])
            ->assertStatus(422)->assertJsonValidationErrors('location_ids');
    }

    public function test_the_first_staff_member_to_join_a_leaderless_program_becomes_its_lead(): void
    {
        // Master Scholarship → Anjila House → Sophit: the program is created
        // with no lead, and the first staff member to join it fills that in.
        $program = $this->actingAs($this->opm)->postJson('/api/programs', ['name' => 'Master Scholarship'])->assertCreated()->json('id');
        $this->actingAs($this->opm)->putJson("/api/locations/{$this->school->id}", [
            'name' => $this->school->name, 'code' => $this->school->code, 'type' => $this->school->type, 'program_ids' => [$program],
        ])->assertOk();

        $sophit = $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'Sophit', 'location_id' => $this->school->id])
            ->assertCreated()->json('id');

        $this->assertSame($sophit, \App\Models\Program::find($program)->responsible_staff_id);

        // The next one does not replace them.
        $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'Second', 'location_id' => $this->school->id])->assertCreated();
        $this->assertSame($sophit, \App\Models\Program::find($program)->responsible_staff_id);
    }

    public function test_a_location_belongs_to_one_program_and_cannot_switch_under_its_staff(): void
    {
        $dream = $this->createProgram('Dream Management', [$this->school], $this->staff())->json('id');
        $scholarship = $this->createProgram('Scholarship', [], $this->staff('Other Lead'))->json('id');
        $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'Teacher', 'location_ids' => [$this->school->id]])->assertCreated();

        $payload = ['name' => $this->school->name, 'code' => $this->school->code, 'type' => $this->school->type];

        // One program only.
        $this->actingAs($this->opm)->putJson("/api/locations/{$this->school->id}", $payload + ['program_ids' => [$dream, $scholarship]])
            ->assertStatus(422)->assertJsonValidationErrors('program_id');

        // Not while a Dream Management teacher is assigned here.
        $this->actingAs($this->opm)->putJson("/api/locations/{$this->school->id}", $payload + ['program_id' => $scholarship])
            ->assertStatus(422)->assertJsonValidationErrors('program_id');

        // And a program can't take a location another program already has.
        $this->actingAs($this->opm)->putJson("/api/programs/{$scholarship}", ['name' => 'Scholarship', 'location_ids' => [$this->school->id]])
            ->assertStatus(422)->assertJsonValidationErrors('location_ids');
    }

    public function test_a_location_still_in_several_programs_keeps_its_staff_editable(): void
    {
        // Saved before the one-program rule (like PEPY Office): two programs.
        $ict = \App\Models\Program::create(['name' => 'LC_ICT']);
        $yes = \App\Models\Program::create(['name' => 'LC_YE']);
        $ict->locations()->attach($this->school->id);
        $yes->locations()->attach($this->school->id);
        $member = Staff::create(['full_name' => 'ICT Lead', 'phone' => '012', 'location_id' => $this->school->id, 'program_id' => $ict->id]);

        // An existing member keeps their program when edited…
        $this->actingAs($this->opm)->putJson("/api/staff/{$member->id}", [
            'full_name' => 'ICT Lead', 'status' => 'active', 'location_ids' => [$this->school->id],
        ])->assertOk()->assertJsonPath('program_id', $ict->id);

        // …but a new one can't be placed until the location keeps one program.
        $this->actingAs($this->opm)->postJson('/api/staff', ['full_name' => 'New', 'location_ids' => [$this->school->id]])
            ->assertStatus(422)->assertJsonValidationErrors('location_ids');
    }

    public function test_a_staff_member_cannot_hold_locations_of_two_programs(): void
    {
        $this->createProgram('Dream Management', [$this->school], $this->staff());
        $this->createProgram('Scholarship', [$this->otherSchool], $this->staff('Other Lead'));

        $this->actingAs($this->opm)->postJson('/api/staff', [
            'full_name' => 'Teacher', 'location_ids' => [$this->school->id, $this->otherSchool->id],
        ])->assertStatus(422)->assertJsonValidationErrors('location_ids');

        $this->assertDatabaseMissing('staff', ['full_name' => 'Teacher']);
    }

    public function test_a_programs_lead_cannot_be_moved_to_another_program(): void
    {
        $lead = $this->staff();
        $dream = $this->createProgram('Dream Management', [$this->school], $lead)->json('id');
        $other = $this->createProgram('Scholarship', [$this->otherSchool], $this->staff('Other Lead'))->json('id');

        // Moving the lead to a school of another program would move them out
        // of the program they lead.
        $this->actingAs($this->opm)->putJson("/api/staff/{$lead->id}", [
            'full_name' => $lead->full_name, 'status' => 'active', 'location_id' => $this->otherSchool->id,
        ])->assertStatus(422)->assertJsonValidationErrors('program_id');
        $this->assertNotNull($other);

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
