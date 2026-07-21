<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AssetImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_corrupted_or_renamed_file_gets_a_clean_user_facing_error(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        // Plain text given an .xlsx extension — PhpSpreadsheet's Xlsx reader
        // will fail deep inside its zip parser; that raw exception (which
        // leaks the server's temp file path) must never reach the response.
        $file = UploadedFile::fake()->createWithContent('register.xlsx', "not actually an excel file\n");

        $response = $this->actingAs($user)->postJson('/api/assets/import', ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Could not read this file as a spreadsheet. Make sure it\'s a valid, unmodified .xlsx, .xls, or .csv export — not a renamed or corrupted file — then try again.']);
        $this->assertStringNotContainsString('zip', strtolower($response->json('message')));
        $this->assertStringNotContainsString(sys_get_temp_dir(), $response->json('message'));
    }

    public function test_a_file_with_no_recognizable_header_row_gets_a_clean_error(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        $file = UploadedFile::fake()->createWithContent('register.csv', "Foo,Bar,Baz\n1,2,3\n");

        $response = $this->actingAs($user)->postJson('/api/assets/import', ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Could not find a header row. Expected a "Description"/"Asset ID" or "name"/"category" column.']);
    }

    public function test_a_well_formed_template_csv_imports_successfully(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        Location::where('code', 'SR')->firstOrFail();
        AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        $csv = "name,category,location,description,model,brand,serial_number,purchase_date,purchase_price,condition,status\n"
            ."Dell Laptop,Computer Equipment,PEPY Office,Core i5,Latitude 5420,Dell,SN123456,2026-01-15,650.00,good,active\n";
        $file = UploadedFile::fake()->createWithContent('register.csv', $csv);

        $response = $this->actingAs($user)->postJson('/api/assets/import', ['file' => $file, 'generate_qr' => '0']);

        $response->assertStatus(200);
        $response->assertJson(['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []]);
        $this->assertDatabaseHas('assets', [
            'name' => 'Dell Laptop',
            'serial_number' => 'SN123456',
            'purchase_date' => '2026-01-15',
            'purchase_price' => 650,
        ]);
    }

    /**
     * The downloadable template's own columns are snake_case
     * (purchase_date, purchase_price) — detectHeader() only recognized the
     * space-separated "purchase date"/"purchase price" phrasing used by the
     * real PEPY register, so a file built from PEPY's own template silently
     * imported with no price or date at all.
     */
    public function test_the_templates_own_snake_case_headers_are_recognized(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        Location::where('code', 'SR')->firstOrFail();
        AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        $csv = "name,category,location,serial_number,purchase_date,purchase_price\n"
            ."Dell Laptop,Computer Equipment,PEPY Office,SN123456,2026-01-15,650.00\n";
        $file = UploadedFile::fake()->createWithContent('register.csv', $csv);

        $response = $this->actingAs($user)->postJson('/api/assets/import', ['file' => $file, 'generate_qr' => '0']);

        $response->assertStatus(200);
        $response->assertJson(['created' => 1, 'errors' => []]);
        $this->assertDatabaseHas('assets', [
            'name' => 'Dell Laptop',
            'purchase_date' => '2026-01-15',
            'purchase_price' => 650,
        ]);
    }

    public function test_the_template_layout_requires_a_recognized_location(): void
    {
        $user = User::factory()->create(['role' => 'operations_hr_manager']);
        AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);

        $csv = "name,category,location,description,model,brand,serial_number,purchase_date,purchase_price,condition,status\n"
            ."Dell Laptop,Computer Equipment,,Core i5,Latitude 5420,Dell,SN123456,2026-01-15,650.00,good,active\n"
            ."HP Desktop,Computer Equipment,Nonexistent Site,,,,,,,\n";
        $file = UploadedFile::fake()->createWithContent('register.csv', $csv);

        $response = $this->actingAs($user)->postJson('/api/assets/import', ['file' => $file, 'generate_qr' => '0']);

        $response->assertStatus(200);
        $response->assertJson(['created' => 0, 'updated' => 0, 'skipped' => 0]);
        $this->assertCount(2, $response->json('errors'));
        $this->assertStringContainsString('location is required', $response->json('errors')[0]);
        $this->assertStringContainsString('location "Nonexistent Site" not found', $response->json('errors')[1]);
        $this->assertDatabaseCount('assets', 0);
    }
}
