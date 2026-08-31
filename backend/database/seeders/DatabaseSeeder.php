<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The four categories the Asset Checking & Counting Manual ships with.
     * Their short names are the CATEGORY segment of the asset tag scheme
     * (PEY-[SITE]-[CATEGORY]-[####]), so they must match AssetCodeService.
     *
     * Sites are seeded by migration, but categories were not — a fresh install
     * started with an empty category list, and since category is required to
     * create an asset, no asset could be registered until someone added one by
     * hand. Categories are not locked to this list; it is the starting set.
     *
     * Names are kept identical to AssetImportService::CATEGORY_NAMES so that
     * seeding first and importing the real register later resolve to the same
     * four categories instead of creating near-duplicates.
     */
    private const DEFAULT_CATEGORIES = [
        ['short_name' => 'MOV', 'name' => 'Motor & Vehicle'],
        ['short_name' => 'FAF', 'name' => 'Fixture & Furniture'],
        ['short_name' => 'COM', 'name' => 'Computer Equipment'],
        ['short_name' => 'EQU', 'name' => 'Equipment Unit'],
    ];

    public function run(): void
    {
        if (! User::where('email', 'admin@ams.com')->exists()) {
            User::create([
                'name' => 'Operations & HR Manager',
                'email' => 'admin@ams.com',
                'password' => bcrypt('password123'),
                'role' => 'operations_hr_manager',
                'is_active' => true,
            ]);
        }

        // Keyed on short_name, so re-seeding an existing install never
        // duplicates a category an admin has already renamed.
        foreach (self::DEFAULT_CATEGORIES as $category) {
            AssetCategory::firstOrCreate(
                ['short_name' => $category['short_name']],
                ['name' => $category['name']]
            );
        }
    }
}
