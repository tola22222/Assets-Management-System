<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The bulk import must not damage the register it is loading into: a
 * re-import only fills blanks, a failure saves nothing, and every guess it
 * makes about a row is reported rather than applied silently.
 */
class AssetImportSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** Column headers of the real PEPY fixed-asset register. */
    private const PEPY_HEADER = ['Description', 'Asset ID', 'Purchase Date', 'Location', 'Price', 'Serial No.', 'Currently Using', 'Used By', 'Remark'];

    private User $opm;

    private AssetCategory $computers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->computers = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);
    }

    public function test_a_reimport_fills_blank_fields_but_never_changes_status_condition_or_location(): void
    {
        $kralanh = Location::where('code', 'KL')->firstOrFail();
        // Registered at the office, since transferred to Kralanh, flagged
        // broken and disposed — all things the spreadsheet knows nothing about.
        $asset = Asset::create([
            'asset_code' => 'PEY-SR-COM-0005',
            'name' => 'Dell Laptop',
            'category_id' => $this->computers->id,
            'location_id' => $kralanh->id,
            'status' => 'disposed',
            'condition' => 'broken',
            'purchase_date' => '2020-01-01',
            'purchase_price' => null,
            'serial_number' => null,
            'description' => 'Original note',
        ]);

        $file = $this->pepyCsv([
            ['Dell Laptop (sheet name)', 'PEY-SR-COM-0005', '2021-01-15', 'PEPY Office', '650', 'SN-777', 'Yes', 'Dara', 'good'],
        ]);

        $response = $this->actingAs($this->opm)->postJson('/api/assets/import', ['file' => $file, 'generate_qr' => '0']);

        $response->assertOk();
        $response->assertJson(['created' => 0, 'updated' => 1, 'unchanged' => 0, 'errors' => [], 'warnings' => []]);

        $asset->refresh();
        $this->assertSame('disposed', $asset->status);
        $this->assertSame('broken', $asset->condition);
        $this->assertSame($kralanh->id, $asset->location_id);
        // Blanks filled from the sheet...
        $this->assertEquals(650, $asset->purchase_price);
        $this->assertSame('SN-777', $asset->serial_number);
        // ...values already on the register left alone.
        $this->assertSame('2020-01-01', $asset->purchase_date);
        $this->assertSame('Original note', $asset->description);
        $this->assertSame('Dell Laptop', $asset->name);

        $this->assertDatabaseHas('activity_logs', ['user_id' => $this->opm->id, 'action' => 'Import']);

        // Loading the same file again has nothing left to fill.
        $again = $this->pepyCsv([
            ['Dell Laptop (sheet name)', 'PEY-SR-COM-0005', '2021-01-15', 'PEPY Office', '999', 'SN-000', 'Yes', 'Dara', 'good'],
        ]);
        $this->actingAs($this->opm)->postJson('/api/assets/import', ['file' => $again, 'generate_qr' => '0'])
            ->assertOk()
            ->assertJson(['created' => 0, 'updated' => 0, 'unchanged' => 1]);
        $this->assertEquals(650, $asset->fresh()->purchase_price);
        $this->assertSame('SN-777', $asset->fresh()->serial_number);
    }

    public function test_a_single_placeholder_photo_does_not_replace_an_existing_assets_photo(): void
    {
        Storage::disk('public')->put('assets/original.jpg', 'original');
        $asset = Asset::create([
            'asset_code' => 'PEY-SR-COM-0006',
            'name' => 'Projector',
            'category_id' => $this->computers->id,
            'location_id' => Location::where('code', 'SR')->value('id'),
            'image_path' => 'assets/original.jpg',
        ]);

        $response = $this->actingAs($this->opm)->postJson('/api/assets/import', [
            'file' => $this->pepyCsv([['Projector', 'PEY-SR-COM-0006', '', 'PEPY Office', '', '', '', '', '']]),
            'generate_qr' => '0',
            'images' => [UploadedFile::fake()->image('placeholder.jpg', 20, 20)],
        ]);

        $response->assertOk()->assertJson(['images_attached' => 0]);
        $this->assertSame('assets/original.jpg', $asset->fresh()->image_path);
        Storage::disk('public')->assertExists('assets/original.jpg');
    }

    public function test_an_unrecognised_location_falls_back_to_the_asset_codes_site_and_is_reported(): void
    {
        $file = $this->pepyCsv([
            // Typo in the name: placed by the KL in its asset ID, with a warning.
            ['Laptop A', 'PEY-KL-COM-0001', '', 'Kralan High School', '', '', '', '', ''],
            // Same site, different case and spacing: a plain name match, no warning.
            ['Laptop B', 'PEY-KL-COM-0002', '', '  kralanh   hs ', '', '', '', '', ''],
            // Neither the name nor the site code is known: the office default, with a warning.
            ['Laptop C', 'PEY-ZZ-COM-0003', '', 'Nowhere', '', '', '', '', ''],
        ]);

        $response = $this->actingAs($this->opm)->postJson('/api/assets/import', ['file' => $file, 'generate_qr' => '0']);

        $response->assertOk();
        $response->assertJson(['created' => 3, 'errors' => []]);

        $kralanh = Location::where('code', 'KL')->firstOrFail();
        $office = Location::where('code', 'SR')->firstOrFail();
        $this->assertSame($kralanh->id, Asset::where('asset_code', 'PEY-KL-COM-0001')->value('location_id'));
        $this->assertSame($kralanh->id, Asset::where('asset_code', 'PEY-KL-COM-0002')->value('location_id'));
        $this->assertSame($office->id, Asset::where('asset_code', 'PEY-ZZ-COM-0003')->value('location_id'));

        $warnings = $response->json('warnings');
        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('Row 2 (PEY-KL-COM-0001)', $warnings[0]);
        $this->assertStringContainsString('Kralan High School', $warnings[0]);
        $this->assertStringContainsString('"Kralanh HS" from site code KL', $warnings[0]);
        $this->assertStringContainsString('Row 4 (PEY-ZZ-COM-0003)', $warnings[1]);
        $this->assertStringContainsString('default site "PEPY Office"', $warnings[1]);
    }

    public function test_a_row_in_a_custom_category_is_imported_and_its_sequence_kept_ahead(): void
    {
        // Stored mixed-case on purpose: the code segment is matched case-insensitively.
        $solar = AssetCategory::create(['name' => 'Solar Equipment', 'short_name' => 'Sol2']);
        // A site code containing a digit.
        $lab = Location::create(['name' => 'Solar Lab 1', 'code' => 'L1', 'type' => 'lab']);

        $file = $this->pepyXlsx([
            ['Solar ( SOL2 )', 'PEY-L1-SOL2', '', '', '', '', '', '', ''], // section header — not an asset
            ['Solar Panel', 'PEY-L1-SOL2-0042', '2021-01-15', 'Solar Lab 1', '1200', 'SP-1', '', '', ''],
            ['Mystery Box', 'PEY-SR-XYZ-0001', '', 'PEPY Office', '', '', '', '', ''], // category nobody set up
        ]);

        $response = $this->actingAs($this->opm)->postJson('/api/assets/import', ['file' => $file, 'generate_qr' => '1']);

        $response->assertOk();
        $response->assertJson(['created' => 1, 'skipped' => 1, 'warnings' => []]);
        $errors = $response->json('errors');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Row 4', $errors[0]);
        $this->assertStringContainsString('"XYZ"', $errors[0]);

        $asset = Asset::where('asset_code', 'PEY-L1-SOL2-0042')->firstOrFail();
        $this->assertSame($solar->id, $asset->category_id);
        $this->assertSame($lab->id, $asset->location_id);

        // QR codes are generated after the rows are committed.
        $this->assertSame('qrcodes/PEY-L1-SOL2-0042.png', $asset->qr_code_path);
        Storage::disk('public')->assertExists('qrcodes/PEY-L1-SOL2-0042.png');

        // The imported number is reserved, so the next registered asset can't collide with the printed tag.
        $this->assertSame(42, (int) DB::table('asset_code_sequences')->where('category_code', 'SOL2')->value('last_sequence'));
        $this->assertSame('PEY-L1-SOL2-0043', AssetCodeService::nextCode($lab->id, $solar->id));
    }

    public function test_a_failure_part_way_through_saves_nothing_and_does_not_leak_sql(): void
    {
        Log::spy();

        // A tag already on the register that the sequence counter doesn't know
        // about: the second generated code collides with it on the unique index.
        DB::table('asset_code_sequences')->updateOrInsert(['category_code' => 'COM'], ['last_sequence' => 0]);
        Asset::create([
            'asset_code' => 'PEY-SR-COM-0002',
            'name' => 'Old Laptop',
            'category_id' => $this->computers->id,
            'location_id' => Location::where('code', 'SR')->value('id'),
        ]);

        $csv = "name,category,location,serial_number\n"
            ."Laptop One,Computer Equipment,PEPY Office,SN-AAA\n"
            ."Laptop Two,Computer Equipment,PEPY Office,SN-BBB\n";

        $response = $this->actingAs($this->opm)->postJson('/api/assets/import', [
            'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
            'generate_qr' => '0',
            'images' => [
                UploadedFile::fake()->image('SN-AAA.jpg', 20, 20),
                UploadedFile::fake()->image('SN-BBB.jpg', 20, 20),
            ],
        ]);

        $response->assertStatus(422);
        $message = $response->json('message');
        $this->assertStringContainsString('nothing from this file was saved', $message);
        foreach (['SQLSTATE', 'insert into', 'UNIQUE', 'asset_code', 'Connection'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $message);
        }

        // Row 1 was written before row 2 failed — and rolled back with it.
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseMissing('assets', ['serial_number' => 'SN-AAA']);
        $this->assertSame(0, (int) DB::table('asset_code_sequences')->where('category_code', 'COM')->value('last_sequence'));
        // So were the photos stored for those rows.
        $this->assertSame([], Storage::disk('public')->allFiles('assets'));
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Import']);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_an_oversized_photo_is_skipped_and_reported_instead_of_failing_the_import(): void
    {
        $file = $this->pepyCsv([
            ['Laptop One', 'PEY-SR-COM-0001', '', 'PEPY Office', '', '', '', '', ''],
            ['Laptop Two', 'PEY-SR-COM-0002', '', 'PEPY Office', '', '', '', '', ''],
        ]);

        $response = $this->actingAs($this->opm)->postJson('/api/assets/import', [
            'file' => $file,
            'generate_qr' => '0',
            'images' => [
                UploadedFile::fake()->create('PEY-SR-COM-0001.jpg', 9 * 1024, 'image/jpeg'), // 9 MB
                UploadedFile::fake()->image('PEY-SR-COM-0002.jpg', 20, 20),
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'created' => 2,
            'errors' => [],
            'images_attached' => 1,
            'images_unmatched' => ['PEY-SR-COM-0001.jpg'],
        ]);
        $this->assertSame('PEY-SR-COM-0001.jpg', $response->json('images_rejected.0.name'));
        $this->assertStringContainsString('8 MB', $response->json('images_rejected.0.reason'));

        // The surviving photo still goes to its own asset only — it does not
        // become a placeholder for every row just because it's the last one left.
        $this->assertNull(Asset::where('asset_code', 'PEY-SR-COM-0001')->value('image_path'));
        $this->assertNotNull(Asset::where('asset_code', 'PEY-SR-COM-0002')->value('image_path'));
    }

    /** A PEPY-layout register as CSV. Each row: description, asset id, date, location, price, serial, using, used by, remark. */
    private function pepyCsv(array $rows): UploadedFile
    {
        $lines = [];
        foreach (array_merge([self::PEPY_HEADER], $rows) as $row) {
            $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $row));
        }

        return UploadedFile::fake()->createWithContent('register.csv', implode("\n", $lines)."\n");
    }

    /** The same layout as a real .xlsx workbook, built with PhpSpreadsheet. */
    private function pepyXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(array_merge([self::PEPY_HEADER], $rows), null, 'A1');

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('asset-import-', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile($path, 'register.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
