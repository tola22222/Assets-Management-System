<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A photo or logo per supplier: saved, replaced, kept, and removed with it. */
class SupplierImageTest extends TestCase
{
    use RefreshDatabase;

    private User $opm;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->opm = User::factory()->create(['role' => 'operations_hr_manager']);
    }

    public function test_a_supplier_photo_is_saved_replaced_kept_and_deleted_with_it(): void
    {
        $created = $this->actingAs($this->opm)->post('/api/suppliers', [
            'name' => 'ABC Computer Shop',
            'phone' => '012345678',
            'image' => UploadedFile::fake()->image('logo.png', 200, 200),
        ], ['Accept' => 'application/json'])->assertCreated();

        $supplier = Supplier::findOrFail($created->json('id'));
        $first = $supplier->image_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);
        $this->assertStringEndsWith('storage/'.$first, $created->json('image_url'));

        // The list carries the photo URL.
        $this->actingAs($this->opm)->getJson('/api/suppliers')
            ->assertOk()->assertJsonPath('0.image_url', $supplier->image_url);

        // Saving without a new photo keeps the old one.
        $this->actingAs($this->opm)->post("/api/suppliers/{$supplier->id}", [
            '_method' => 'PUT', 'name' => 'ABC Computer Shop Ltd',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame($first, $supplier->fresh()->image_path);

        // A new photo replaces it, and the old file goes.
        $this->actingAs($this->opm)->post("/api/suppliers/{$supplier->id}", [
            '_method' => 'PUT', 'name' => 'ABC Computer Shop Ltd',
            'image' => UploadedFile::fake()->image('new-logo.jpg', 200, 200),
        ], ['Accept' => 'application/json'])->assertOk();
        $second = $supplier->fresh()->image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Deleting the supplier deletes its photo.
        $this->actingAs($this->opm)->deleteJson("/api/suppliers/{$supplier->id}")->assertOk();
        Storage::disk('public')->assertMissing($second);
    }

    public function test_only_an_image_is_accepted(): void
    {
        $this->actingAs($this->opm)->post('/api/suppliers', [
            'name' => 'ABC Computer Shop',
            'image' => UploadedFile::fake()->create('price-list.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertSame(0, Supplier::count());
    }
}
