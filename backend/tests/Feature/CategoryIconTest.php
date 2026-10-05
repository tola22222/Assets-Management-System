<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A category's icon: one of the app's own, changed or cleared on edit. */
class CategoryIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_icon_is_saved_changed_and_cleared(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);

        $id = $this->actingAs($opm)->postJson('/api/categories', ['name' => 'Motor & Vehicle', 'short_name' => 'MOV', 'icon' => 'truck'])
            ->assertCreated()->assertJsonPath('icon', 'truck')->json('id');

        $this->actingAs($opm)->postJson('/api/categories', ['name' => 'Repair Tools', 'icon' => 'wrench'])
            ->assertCreated()->assertJsonPath('icon', 'wrench');

        $this->actingAs($opm)->getJson('/api/categories')->assertOk()->assertJsonPath('1.icon', 'truck');

        $this->actingAs($opm)->putJson("/api/categories/{$id}", ['name' => 'Motor & Vehicle', 'short_name' => 'MOV', 'icon' => 'computer'])
            ->assertOk()->assertJsonPath('icon', 'computer');

        // No icon ("Automatic"): the list picks one from the name again.
        $this->actingAs($opm)->putJson("/api/categories/{$id}", ['name' => 'Motor & Vehicle', 'short_name' => 'MOV', 'icon' => null])
            ->assertOk();
        $this->assertNull(AssetCategory::find($id)->icon);
    }

    public function test_only_the_apps_own_icons_are_accepted(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);

        $this->actingAs($opm)->postJson('/api/categories', ['name' => 'Kitchen', 'icon' => 'rocket'])
            ->assertStatus(422)->assertJsonValidationErrors('icon');
    }
}
