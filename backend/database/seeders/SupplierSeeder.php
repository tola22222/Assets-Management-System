<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'GIZ',                             'phone' => '+49 228 4460-0',  'address' => 'Friedrich-Ebert-Allee 32 + 36, 53113 Bonn, Germany'],
            ['name' => 'Australian Volunteers Programme', 'phone' => '1800 331 292',    'address' => '160 Johnson St, Fitzroy, VIC 3065, Australia'],
            ['name' => 'Zendesk',                         'phone' => '+1 888-851-9456', 'address' => '181 Fremont St, San Francisco, CA 94105, USA'],
            ['name' => 'Team4Tech',                       'phone' => '+1 415-640-4300', 'address' => 'PO Box 7084, Menlo Park, CA 94026, USA'],
            ['name' => 'GlobalGiving',                    'phone' => '+1 877-605-2314', 'address' => '1 Thomas Circle NW, Suite 800, Washington, DC 20005, USA'],
        ];

        foreach ($suppliers as $s) {
            DB::table('suppliers')->updateOrInsert(
                ['name' => $s['name']],
                $s + ['image_path' => null, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}