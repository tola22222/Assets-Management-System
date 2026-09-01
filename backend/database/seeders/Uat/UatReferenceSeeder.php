<?php

namespace Database\Seeders\Uat;

use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Supplier;
use Database\Seeders\Support\PepyInventory;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Reference data the asset rows depend on.
 *
 * Locations are NOT created here — all 13 PEPY sites are seeded by migration
 * (2026_07_20_161520 + 2026_08_31_120000) and carry the site codes the asset
 * tag scheme depends on. This seeder verifies they are present and fails loudly
 * if they are not, rather than creating a second, code-less copy of a site that
 * would then break AssetCodeService::nextCode().
 *
 * Programs are taken from the register's own "Used By / Program" column, so
 * every program here is one the source document actually references.
 */
class UatReferenceSeeder extends Seeder
{
    /**
     * Programs named in the register's Used By column and in the COM range
     * descriptions. Descriptions are ours; the names are the source's.
     */
    private const PROGRAMS = [
        'Dream Program' => 'Partner high-school program — the largest holder of tagged assets in the register.',
        'Office' => 'PEPY Siem Reap office general use.',
        'LC_English' => 'Learning Centre — English classes.',
        'LC_ICT' => 'Learning Centre — ICT classes and computer lab.',
        'LC_YE' => 'Learning Centre — Youth Employment.',
        'Comm. Team' => 'Communications team — cameras, drone and stabiliser equipment.',
        'Office Storage' => 'Held in office storage, not yet issued to a person or site.',
        'Scholarship Program' => 'Scholarship student laptop borrowing scheme.',
        'Bright Future Labs' => 'Bright Future Labs laptop cohort.',
    ];

    /** Suppliers are not in the register source; these support the Suppliers CRUD screen only. */
    private const SUPPLIERS = [
        ['name' => 'Angkor IT Supply', 'phone' => '063 964 111', 'address' => 'Sivatha Blvd, Siem Reap'],
        ['name' => 'Mekong Office Furniture', 'phone' => '023 987 220', 'address' => 'St 271, Phnom Penh'],
        ['name' => 'Bayon Electronics', 'phone' => '063 760 452', 'address' => 'Wat Bo Road, Siem Reap'],
        ['name' => 'Toyota Cambodia', 'phone' => '023 900 900', 'address' => 'Russian Blvd, Phnom Penh'],
        ['name' => 'Sokimex Hardware', 'phone' => '063 963 388', 'address' => 'National Road 6, Siem Reap'],
        ['name' => 'Green Power Cooling', 'phone' => '012 848 019', 'address' => 'Taphul Village, Siem Reap'],
    ];

    private const SETTINGS = [
        'organization_name' => 'PEPY Empowering Youth',
        'system_name' => 'PEPY Assets',
        'theme_color' => '#128a43',
        'email' => 'info@pepyempoweringyouth.org',
        'phone' => '012 782 785',
        'address' => 'Siem Reap, Cambodia',
        'qr_size' => '300',
        'locale' => 'en',
        'report_interval_months' => '6',
        'report_recipient_email' => 'manin@pepyempoweringyouth.org',
        'include_staff_in_reports' => '0',
        'mail_mailer' => 'log',
        'mail_from_address' => 'noreply@pepyempoweringyouth.org',
        'mail_from_name' => 'PEPY Assets',
    ];

    public function run(): void
    {
        $this->assertSitesPresent();
        $this->seedCategories();

        foreach (self::PROGRAMS as $name => $description) {
            Program::updateOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach (self::SUPPLIERS as $supplier) {
            Supplier::updateOrCreate(['name' => $supplier['name']], $supplier);
        }

        foreach (self::SETTINGS as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Every site named in the register's Location column must already exist
     * with a site code, or asset rows would land with location_id = null and
     * the by-location reporting would be wrong.
     */
    private function assertSitesPresent(): void
    {
        $missing = [];

        foreach (PepyInventory::SITE_NAMES as $name) {
            if (! Location::where('name', $name)->exists()) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'These PEPY sites are missing from the locations table: '.implode(', ', $missing).
                '. They are created by migration — run `php artisan migrate` before seeding.'
            );
        }

        $codeless = Location::whereNull('code')->orWhere('code', '')->pluck('name')->all();

        if ($codeless !== []) {
            $this->command?->warn('  Locations with no site code (cannot generate new asset tags): '.implode(', ', $codeless));
        }
    }

    /**
     * The four manual categories, plus one extra to prove categories are no
     * longer hard-locked to MOV/FAF/COM/EQU. Keyed on short_name so a re-run
     * never duplicates a category an admin renamed.
     */
    private function seedCategories(): void
    {
        $categories = [
            ['short_name' => 'MOV', 'name' => 'Motor & Vehicle', 'description' => 'Vehicles, motorbikes and helmets.'],
            ['short_name' => 'FAF', 'name' => 'Fixture & Furniture', 'description' => 'Chairs, desks, fans, cabinets and boards.'],
            ['short_name' => 'COM', 'name' => 'Computer Equipment', 'description' => 'Laptops, desktops, printers, projectors and peripherals.'],
            ['short_name' => 'EQU', 'name' => 'Equipment Unit', 'description' => 'Cameras, air-conditioners, speakers and safe boxes.'],
            ['short_name' => 'TOOL', 'name' => 'Tools & Hardware', 'description' => 'Added by the UAT seeder to exercise a category outside the original four.'],
        ];

        foreach ($categories as $category) {
            AssetCategory::updateOrCreate(
                ['short_name' => $category['short_name']],
                ['name' => $category['name'], 'description' => $category['description']]
            );
        }
    }
}
