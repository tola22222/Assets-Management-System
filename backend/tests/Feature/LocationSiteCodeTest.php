<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Locations endpoint used to validate only name/type/description, so a site
 * added through the app was saved with a null `code`. Nothing complained until
 * someone registered or imported an asset there, which then failed deep inside
 * AssetCodeService with "Asset location must be an approved site with a site
 * code." — a message that named neither the site nor the fix.
 */
class LocationSiteCodeTest extends TestCase
{
    use RefreshDatabase;

    private function opm(): User
    {
        return User::factory()->create(['role' => 'operations_hr_manager']);
    }

    public function test_a_location_cannot_be_created_without_a_site_code(): void
    {
        $response = $this->actingAs($this->opm())->postJson('/api/locations', [
            'name' => 'New Learning Center',
            'type' => 'program',
            'program_ids' => [\App\Models\Program::firstOrCreate(['name' => 'Dream Program'])->id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertDatabaseMissing('locations', ['name' => 'New Learning Center']);
    }

    public function test_a_location_must_belong_to_a_program(): void
    {
        $this->actingAs($this->opm())->postJson('/api/locations', [
            'name' => 'School A', 'code' => 'SA', 'type' => 'program',
        ])->assertStatus(422)->assertJsonValidationErrors('program_id');

        $dream = \App\Models\Program::create(['name' => 'Dream Program']);
        $id = $this->actingAs($this->opm())->postJson('/api/locations', [
            'name' => 'School A', 'code' => 'SA', 'type' => 'program', 'program_ids' => [$dream->id],
        ])->assertStatus(201)->assertJsonPath('programs.0.name', 'Dream Program')->json('id');

        $this->assertDatabaseHas('location_program', ['location_id' => $id, 'program_id' => $dream->id]);
    }

    public function test_a_site_code_is_stored_upper_cased(): void
    {
        $response = $this->actingAs($this->opm())->postJson('/api/locations', [
            'name' => 'New Learning Center',
            'code' => 'nl',
            'type' => 'program',
            'program_ids' => [\App\Models\Program::firstOrCreate(['name' => 'Dream Program'])->id],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('locations', ['name' => 'New Learning Center', 'code' => 'NL']);
    }

    public function test_a_site_code_already_in_use_is_rejected(): void
    {
        $response = $this->actingAs($this->opm())->postJson('/api/locations', [
            'name' => 'Another Office',
            'code' => 'sr', // PEPY Office already holds SR.
            'type' => 'office',
            'program_ids' => [\App\Models\Program::firstOrCreate(['name' => 'Dream Program'])->id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_a_malformed_site_code_is_rejected(): void
    {
        $response = $this->actingAs($this->opm())->postJson('/api/locations', [
            'name' => 'Bad Code Site',
            'code' => 'X-1',
            'type' => 'office',
            'program_ids' => [\App\Models\Program::firstOrCreate(['name' => 'Dream Program'])->id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_a_location_keeps_its_own_code_when_edited(): void
    {
        $location = Location::where('code', 'SR')->firstOrFail();

        $response = $this->actingAs($this->opm())->putJson("/api/locations/{$location->id}", [
            'name' => 'PEPY Head Office',
            'code' => 'SR',
            'type' => 'office',
            'program_ids' => [\App\Models\Program::firstOrCreate(['name' => 'Dream Program'])->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'PEPY Head Office', 'code' => 'SR']);
    }

    public function test_registering_an_asset_at_a_code_less_site_is_a_422_naming_the_site(): void
    {
        // Simulated the way it happened in production: a row that predates the
        // validation fix, so the code column is still null.
        $location = Location::create(['name' => 'Legacy Site', 'type' => 'program']);
        $category = AssetCategory::create(['name' => 'Furniture', 'short_name' => 'FAF']);

        $response = $this->actingAs($this->opm())->postJson('/api/assets', [
            'name' => 'Wooden Desk',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => 'active',
            'condition' => 'good',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('location_id');
        $this->assertStringContainsString('Legacy Site', $response->json('errors.location_id.0'));
    }

    public function test_a_site_with_staff_assigned_cannot_be_deleted(): void
    {
        $location = Location::where('code', 'SR')->firstOrFail();
        Staff::create(['full_name' => 'Site Staff', 'location_id' => $location->id]);

        $response = $this->actingAs($this->opm())->deleteJson("/api/locations/{$location->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
        $this->assertDatabaseHas('staff', ['full_name' => 'Site Staff', 'location_id' => $location->id]);
    }
}
