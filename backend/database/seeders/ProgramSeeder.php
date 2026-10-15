<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            ['name' => 'Dream Program',       'description' => 'Dream Management program supporting students at partner high schools.', 'lead' => 'Mara Chek'],
            ['name' => 'Office',              'description' => 'Main office operations and administration.',                             'lead' => null],
            ['name' => 'LC_English',          'description' => 'Learning Center - English classes.',                                     'lead' => null],
            ['name' => 'LC_ICT',              'description' => 'Learning Center - ICT / computer classes.',                              'lead' => null],
            ['name' => 'LC_YE',               'description' => 'Learning Center - Youth Empowerment activities.',                        'lead' => null],
            ['name' => 'Office Storage',      'description' => 'Storage for office equipment and supplies.',                             'lead' => null],
            ['name' => 'Scholarship Program', 'description' => 'Scholarship support for students.',                                      'lead' => null],
        ];

        // Find which column (if any) stores the program lead
        $leadColumn = collect(['lead_id', 'lead_staff_id', 'staff_id', 'leader_id'])
            ->first(fn ($c) => Schema::hasColumn('programs', $c));

        foreach ($programs as $p) {
            $data = [
                'name'        => $p['name'],
                'description' => $p['description'],
                'updated_at'  => now(),
            ];

            if (Schema::hasColumn('programs', 'status')) {
                $data['status'] = 'active';
            }

            if ($leadColumn && $p['lead']) {
                $data[$leadColumn] = DB::table('staff')->where('full_name', $p['lead'])->value('id');
            }

            DB::table('programs')->updateOrInsert(
                ['name' => $p['name']],
                $data + ['created_at' => now()]
            );
        }

        // Remove Kralanh HS and Varin HS from Dream Program
        $dreamId = DB::table('programs')->where('name', 'Dream Program')->value('id');

        if ($dreamId && Schema::hasTable('locations')) {
            $locationIds = DB::table('locations')
                ->whereIn('name', ['Kralanh HS', 'Varin HS'])
                ->pluck('id');

            if (Schema::hasColumn('locations', 'program_id')) {
                DB::table('locations')
                    ->whereIn('id', $locationIds)
                    ->where('program_id', $dreamId)
                    ->update(['program_id' => null]);
            }

            if (Schema::hasTable('location_program')) {
                DB::table('location_program')
                    ->where('program_id', $dreamId)
                    ->whereIn('location_id', $locationIds)
                    ->delete();
            }
        }
    }
}