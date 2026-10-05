<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Import photos by asset group: assets of one category with different names
 * (COM · Dell, COM · Asus) each get their own photo, and repeats of the same
 * category + name in the file share one.
 */
class AssetImportPhotoGroupTest extends TestCase
{
    use RefreshDatabase;

    private const PEPY_HEADER = ['Description', 'Asset ID', 'Purchase Date', 'Location', 'Price', 'Serial No.', 'Currently Using', 'Used By', 'Remark'];

    private User $opm;

    private AssetCategory $com;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->com = AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);
    }

    private function templateCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('register.csv',
            "name,category,location,quantity\n"
            ."Dell,Computer Equipment,PEPY Office,20\n"
            ."Asus,Computer Equipment,PEPY Office,20\n"
            ." dell ,computer equipment,PEPY Office,5\n"   // same group: case and spacing ignored
            ."Lenovo,Computer Equipment,PEPY Office,10\n"
        );
    }

    public function test_the_preview_groups_by_category_and_name_and_combines_repeats(): void
    {
        $this->actingAs($this->opm)->postJson('/api/assets/import/preview', ['file' => $this->templateCsv()])
            ->assertOk()
            ->assertExactJson(['rows' => 4, 'assets' => 55, 'groups' => [
                ['key' => 'COMPUTER EQUIPMENT|DELL', 'category' => 'COM', 'name' => 'Dell', 'count' => 25],
                ['key' => 'COMPUTER EQUIPMENT|ASUS', 'category' => 'COM', 'name' => 'Asus', 'count' => 20],
                ['key' => 'COMPUTER EQUIPMENT|LENOVO', 'category' => 'COM', 'name' => 'Lenovo', 'count' => 10],
            ]]);

        $this->assertSame(0, Asset::count());
    }

    public function test_each_name_in_the_same_category_gets_its_own_photo(): void
    {
        $groups = collect($this->actingAs($this->opm)
            ->postJson('/api/assets/import/preview', ['file' => $this->templateCsv()])
            ->json('groups'))->keyBy('name');

        $dell = UploadedFile::fake()->image('dell.jpg', 40, 30);
        $asus = UploadedFile::fake()->image('asus.jpg', 60, 45);
        $dellBytes = file_get_contents($dell->getRealPath());
        $asusBytes = file_get_contents($asus->getRealPath());
        $this->assertNotSame($dellBytes, $asusBytes);

        // Lenovo is left without a photo.
        $this->actingAs($this->opm)->post('/api/assets/import', [
            'file' => $this->templateCsv(),
            'generate_qr' => '0',
            'group_keys' => [$groups['Dell']['key'], $groups['Asus']['key']],
            'group_images' => [$dell, $asus],
        ])->assertOk()->assertJson(['created' => 55, 'images_attached' => 45, 'errors' => []]);

        $photo = fn (Asset $a) => $a->image_path ? Storage::disk('public')->get($a->image_path) : null;
        $byName = Asset::all()->groupBy(fn (Asset $a) => strtolower($a->name));

        $this->assertCount(25, $byName['dell']);
        $this->assertTrue($byName['dell']->every(fn (Asset $a) => $photo($a) === $dellBytes));
        $this->assertCount(20, $byName['asus']);
        $this->assertTrue($byName['asus']->every(fn (Asset $a) => $photo($a) === $asusBytes));
        $this->assertCount(10, $byName['lenovo']);
        $this->assertTrue($byName['lenovo']->every(fn (Asset $a) => $a->image_path === null));
    }

    public function test_pepy_layout_groups_by_code_category_and_keeps_existing_photos(): void
    {
        $office = \App\Models\Location::where('code', 'SR')->firstOrFail();
        Storage::disk('public')->put('assets/old.jpg', 'old photo');
        // Already has a photo: a group photo must not replace it.
        Asset::create(['asset_code' => 'PEY-SR-COM-0001', 'name' => 'Dell', 'category_id' => $this->com->id,
            'location_id' => $office->id, 'image_path' => 'assets/old.jpg', 'purchase_price' => null]);
        // No photo yet: a group photo fills it in.
        Asset::create(['asset_code' => 'PEY-SR-COM-0002', 'name' => 'Dell', 'category_id' => $this->com->id,
            'location_id' => $office->id, 'purchase_price' => null]);

        $rows = [
            ['Dell', 'PEY-SR-COM-0001', '', 'PEPY Office', '500', '', '', '', ''],
            ['Dell', 'PEY-SR-COM-0002', '', 'PEPY Office', '500', '', '', '', ''],
            ['Dell', 'PEY-SR-COM-0003', '', 'PEPY Office', '500', '', '', '', ''],
            ['Asus', 'PEY-SR-COM-0004', '', 'PEPY Office', '450', '', '', '', ''],
        ];
        $csv = fn () => UploadedFile::fake()->createWithContent('register.csv', collect(array_merge([self::PEPY_HEADER], $rows))
            ->map(fn ($r) => implode(',', $r))->implode("\n")."\n");

        $this->actingAs($this->opm)->postJson('/api/assets/import/preview', ['file' => $csv()])
            ->assertOk()
            ->assertJsonPath('groups', [
                ['key' => 'COM|DELL', 'category' => 'COM', 'name' => 'Dell', 'count' => 3],
                ['key' => 'COM|ASUS', 'category' => 'COM', 'name' => 'Asus', 'count' => 1],
            ]);

        $dell = UploadedFile::fake()->image('dell.jpg', 40, 30);
        $dellBytes = file_get_contents($dell->getRealPath());
        // A photo named after one asset still wins over its group's photo.
        $own = UploadedFile::fake()->image('PEY-SR-COM-0003.jpg', 20, 20);
        $ownBytes = file_get_contents($own->getRealPath());

        $this->actingAs($this->opm)->post('/api/assets/import', [
            'file' => $csv(),
            'generate_qr' => '0',
            'images' => [$own, UploadedFile::fake()->image('PEY-SR-COM-0099.jpg', 10, 10)],
            'group_keys' => ['COM|DELL'],
            'group_images' => [$dell],
        ])->assertOk()->assertJson(['created' => 2, 'updated' => 2, 'errors' => []]);

        $photo = fn (string $code) => ($p = Asset::where('asset_code', $code)->value('image_path')) ? Storage::disk('public')->get($p) : null;
        $this->assertSame('old photo', $photo('PEY-SR-COM-0001'));
        $this->assertSame($dellBytes, $photo('PEY-SR-COM-0002'));
        $this->assertSame($ownBytes, $photo('PEY-SR-COM-0003'));
        $this->assertNull($photo('PEY-SR-COM-0004'));
    }
}
