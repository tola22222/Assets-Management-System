<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * School -> Program -> Responsible Staff.
 *
 * These two fields are what AssetTransferController reads to decide who may
 * accept a delivery at a site, which is why they are required rather than
 * optional extras. See AssetTransferReceiptTest for the rules that hang off it.
 */
class ProgramResponsibleStaffTest extends TestCase
{
    use RefreshDatabase;

    private Location $school;

    private User $opm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = Location::where('code', '!=', 'SR')->firstOrFail();
        $this->opm = User::factory()->create(['role' => 'operations_hr_manager']);
    }

    private function staffAt(Location $location, string $name = 'Program Lead'): Staff
    {
        return Staff::create([
            'full_name' => $name,
            'phone' => '012345678',
            'location_id' => $location->id,
        ]);
    }

    public function test_a_program_cannot_be_created_without_a_school_and_responsible_staff(): void
    {
        $this->actingAs($this->opm)
            ->postJson('/api/programs', ['name' => 'Dream Management'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id', 'responsible_staff_id']);
    }

    public function test_the_responsible_staff_must_work_at_the_selected_school(): void
    {
        $elsewhere = Location::whereNot('id', $this->school->id)->firstOrFail();
        $outsider = $this->staffAt($elsewhere, 'Someone Elsewhere');

        $this->actingAs($this->opm)
            ->postJson('/api/programs', [
                'name' => 'Dream Management',
                'location_id' => $this->school->id,
                'responsible_staff_id' => $outsider->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['responsible_staff_id']);
    }

    public function test_a_program_is_created_with_its_school_and_lead(): void
    {
        $lead = $this->staffAt($this->school);

        $this->actingAs($this->opm)
            ->postJson('/api/programs', [
                'name' => 'Dream Management',
                'location_id' => $this->school->id,
                'responsible_staff_id' => $lead->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('location_id', $this->school->id)
            ->assertJsonPath('responsible_staff_id', $lead->id);
    }

    public function test_a_staff_member_can_only_lead_one_program(): void
    {
        $lead = $this->staffAt($this->school);

        $this->actingAs($this->opm)->postJson('/api/programs', [
            'name' => 'Dream Management',
            'location_id' => $this->school->id,
            'responsible_staff_id' => $lead->id,
        ])->assertStatus(201);

        $this->actingAs($this->opm)
            ->postJson('/api/programs', [
                'name' => 'Scholarship',
                'location_id' => $this->school->id,
                'responsible_staff_id' => $lead->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['responsible_staff_id']);

        $this->assertDatabaseMissing('programs', ['name' => 'Scholarship']);
    }

    public function test_a_program_can_be_edited_without_giving_up_its_own_lead(): void
    {
        // The uniqueness check has to ignore the row being edited, or saving a
        // description change would fail against the program's own lead.
        $lead = $this->staffAt($this->school);
        $created = $this->actingAs($this->opm)->postJson('/api/programs', [
            'name' => 'Dream Management',
            'location_id' => $this->school->id,
            'responsible_staff_id' => $lead->id,
        ])->assertStatus(201);

        $this->actingAs($this->opm)
            ->putJson('/api/programs/'.$created->json('id'), [
                'name' => 'Dream Management',
                'location_id' => $this->school->id,
                'responsible_staff_id' => $lead->id,
                'description' => 'Now with a description',
            ])
            ->assertStatus(200)
            ->assertJsonPath('description', 'Now with a description');
    }

    public function test_a_lead_freed_by_reassignment_can_be_picked_up_again(): void
    {
        $first = $this->staffAt($this->school, 'First Lead');
        $second = $this->staffAt($this->school, 'Second Lead');

        $created = $this->actingAs($this->opm)->postJson('/api/programs', [
            'name' => 'Dream Management',
            'location_id' => $this->school->id,
            'responsible_staff_id' => $first->id,
        ])->assertStatus(201);

        // Hand the program to someone else, which releases the first lead.
        $this->actingAs($this->opm)->putJson('/api/programs/'.$created->json('id'), [
            'name' => 'Dream Management',
            'location_id' => $this->school->id,
            'responsible_staff_id' => $second->id,
        ])->assertStatus(200);

        $this->actingAs($this->opm)
            ->postJson('/api/programs', [
                'name' => 'Scholarship',
                'location_id' => $this->school->id,
                'responsible_staff_id' => $first->id,
            ])
            ->assertStatus(201);
    }

    public function test_editing_a_program_cannot_drop_its_school_or_lead(): void
    {
        $lead = $this->staffAt($this->school);
        $created = $this->actingAs($this->opm)->postJson('/api/programs', [
            'name' => 'Dream Management',
            'location_id' => $this->school->id,
            'responsible_staff_id' => $lead->id,
        ])->assertStatus(201);

        $this->actingAs($this->opm)
            ->putJson('/api/programs/'.$created->json('id'), ['name' => 'Dream Management'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id', 'responsible_staff_id']);
    }
}
