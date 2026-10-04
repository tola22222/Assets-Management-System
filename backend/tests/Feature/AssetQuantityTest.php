<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Quantity on Add Asset and on the import template: N identical units in one
 * go, each its own asset with its own code.
 */
class AssetQuantityTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private Location $office;

    private AssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->office = Location::where('code', 'SR')->firstOrFail();
        $this->category = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'name' => 'Smart Phone', 'category_id' => $this->category->id, 'location_id' => $this->office->id,
            'status' => 'active', 'condition' => 'good',
        ];
    }

    public function test_add_asset_with_quantity_registers_each_unit_with_its_own_code(): void
    {
        $this->actingAs($this->hr)->postJson('/api/assets', $this->payload(['quantity' => 5]))
            ->assertCreated()
            ->assertJsonPath('created_count', 5);

        $codes = Asset::where('name', 'Smart Phone')->orderBy('id')->pluck('asset_code')->all();
        $this->assertSame(['PEY-SR-COM-0001', 'PEY-SR-COM-0002', 'PEY-SR-COM-0003', 'PEY-SR-COM-0004', 'PEY-SR-COM-0005'], $codes);
        $this->assertSame(5, Asset::where('name', 'Smart Phone')->where('location_id', $this->office->id)->count());
    }

    public function test_no_quantity_still_registers_one(): void
    {
        $this->actingAs($this->hr)->postJson('/api/assets', $this->payload())->assertCreated()->assertJsonPath('created_count', 1);
        $this->assertSame(1, Asset::count());
    }

    public function test_quantity_limits_and_serial_number_rule(): void
    {
        $this->actingAs($this->hr)->postJson('/api/assets', $this->payload(['quantity' => 0]))->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->actingAs($this->hr)->postJson('/api/assets', $this->payload(['quantity' => Asset::MAX_BATCH_QUANTITY + 1]))->assertStatus(422);
        $this->actingAs($this->hr)->postJson('/api/assets', $this->payload(['quantity' => 2, 'serial_number' => 'SN-1']))
            ->assertStatus(422)->assertJsonValidationErrors('serial_number');

        $this->assertSame(0, Asset::count());
    }

    public function test_the_import_preview_counts_each_unit_of_a_quantity(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $csv = "name,category,location,quantity\n"
            ."Dell Laptop,Computer Equipment,PEPY Office,\n"
            ."Smart Phone,Computer Equipment,PEPY Office,20\n"
            ."Bad Row,Computer Equipment,PEPY Office,abc\n"
            .",,,\n";

        $this->actingAs($opm)->postJson('/api/assets/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
        ])->assertOk()->assertExactJson(['rows' => 3, 'assets' => 21]);

        $this->assertSame(0, Asset::count());
    }

    public function test_the_import_template_quantity_column_does_the_same(): void
    {
        $csv = "name,category,location,quantity,serial_number\n"
            ."Dell Laptop,Computer Equipment,PEPY Office,,SN-9\n"
            ."Smart Phone,Computer Equipment,PEPY Office,20,\n"
            ."Tablet,Computer Equipment,PEPY Office,3,SN-X\n"
            ."Mouse,Computer Equipment,PEPY Office,abc,\n";
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, $csv);

        $result = app(AssetImportService::class)->import(new UploadedFile($path, 'assets.csv', 'text/csv', null, true), false);

        $this->assertSame(21, $result['created']);
        $this->assertSame(20, Asset::where('name', 'Smart Phone')->count());
        $this->assertSame(20, Asset::where('name', 'Smart Phone')->distinct()->count('asset_code'));
        $this->assertSame(1, Asset::where('name', 'Dell Laptop')->count());
        $this->assertSame(0, Asset::whereIn('name', ['Tablet', 'Mouse'])->count());
        $this->assertCount(2, $result['errors']);
    }
}
