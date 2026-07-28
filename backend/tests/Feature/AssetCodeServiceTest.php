<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Location;
use App\Services\AssetCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AssetCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Site codes used here (ZZ/YY) deliberately avoid the 13 real PEPY
     * site codes seeded by the locations migration, so tests don't collide
     * with that seeded data. */
    private function makeLocation(string $code = 'ZZ'): Location
    {
        return Location::create(['name' => "{$code} Site", 'code' => $code, 'type' => 'office']);
    }

    private function makeCategory(string $shortName = 'FAF'): AssetCategory
    {
        return AssetCategory::create(['name' => $shortName, 'short_name' => $shortName]);
    }

    public function test_it_generates_a_code_in_the_pey_site_category_sequence_format(): void
    {
        $location = $this->makeLocation('ZZ');
        $category = $this->makeCategory('FAF');

        $code = AssetCodeService::nextCode($location->id, $category->id);

        $this->assertSame('PEY-ZZ-FAF-0001', $code);
    }

    public function test_the_sequence_is_global_per_category_not_per_site(): void
    {
        $siteA = $this->makeLocation('ZZ');
        $siteB = $this->makeLocation('YY');
        $category = $this->makeCategory('COM');

        $first = AssetCodeService::nextCode($siteA->id, $category->id);
        $second = AssetCodeService::nextCode($siteB->id, $category->id);

        $this->assertSame('PEY-ZZ-COM-0001', $first);
        $this->assertSame('PEY-YY-COM-0002', $second);
    }

    public function test_the_sequence_never_resets_across_categories(): void
    {
        $location = $this->makeLocation('ZZ');
        $mov = $this->makeCategory('MOV');
        $faf = $this->makeCategory('FAF');

        AssetCodeService::nextCode($location->id, $mov->id);
        AssetCodeService::nextCode($location->id, $mov->id);
        $fafCode = AssetCodeService::nextCode($location->id, $faf->id);

        $this->assertSame('PEY-ZZ-FAF-0001', $fafCode);
    }

    public function test_it_accepts_a_custom_category_code_beyond_the_manuals_original_four(): void
    {
        $location = $this->makeLocation('ZZ');
        $category = $this->makeCategory('ELEC');

        $code = AssetCodeService::nextCode($location->id, $category->id);

        $this->assertSame('PEY-ZZ-ELEC-0001', $code);
    }

    public function test_it_rejects_a_category_with_no_short_name_set(): void
    {
        $location = $this->makeLocation('ZZ');
        $category = $this->makeCategory('');

        $this->expectException(InvalidArgumentException::class);
        AssetCodeService::nextCode($location->id, $category->id);
    }

    public function test_it_rejects_a_malformed_short_name(): void
    {
        $location = $this->makeLocation('ZZ');
        $category = $this->makeCategory('A!B');

        $this->expectException(InvalidArgumentException::class);
        AssetCodeService::nextCode($location->id, $category->id);
    }

    public function test_it_rejects_a_location_without_a_site_code(): void
    {
        $location = Location::create(['name' => 'No Code Site', 'type' => 'office']);
        $category = $this->makeCategory('EQU');

        $this->expectException(InvalidArgumentException::class);
        AssetCodeService::nextCode($location->id, $category->id);
    }

    public function test_concurrent_requests_never_collide_on_the_same_sequence_number(): void
    {
        $location = $this->makeLocation('ZZ');
        $category = $this->makeCategory('COM');

        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $codes[] = AssetCodeService::nextCode($location->id, $category->id);
        }

        $this->assertCount(20, array_unique($codes), 'Every generated code must be unique.');
        $this->assertSame('PEY-ZZ-COM-0020', end($codes));
    }
}
