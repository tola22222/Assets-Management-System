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

        $csv = "name,category,description,model,brand,serial_number,purchase_date,purchase_price,condition,status\n"
            ."Dell Laptop,Computer Equipment,Core i5,Latitude 5420,Dell,SN123456,2026-01-15,650.00,good,active\n";
        $file = UploadedFile::fake()->createWithContent('register.csv', $csv);

        $response = $this->actingAs($user)->postJson('/api/assets/import', ['file' => $file, 'generate_qr' => '0']);

        $response->assertStatus(200);
        $response->assertJson(['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []]);
        $this->assertDatabaseHas('assets', ['name' => 'Dell Laptop', 'serial_number' => 'SN123456']);
    }
}
