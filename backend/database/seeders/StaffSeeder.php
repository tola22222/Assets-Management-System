<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['full_name' => 'Savoeng Skut',    'position' => 'Executive Director'],
            ['full_name' => 'Manin Oem',       'position' => 'Operations and HR Manager'],
            ['full_name' => 'Konnitha Sien',   'position' => 'Finance Manager'],
            ['full_name' => 'Sreyneth Lann',   'position' => 'SHE Program Officer'],
            ['full_name' => 'Channra Roeut',   'position' => 'SHE Program Coordinator'],
            ['full_name' => 'Thomorn Lao',     'position' => 'ICT Teacher'],
            ['full_name' => 'Sokvoeun Choeng', 'position' => 'English Project Officer'],
            ['full_name' => 'Dena By',         'position' => 'Youth Empowerment Project Officer'],
            ['full_name' => 'Soury Phonn',     'position' => 'SHE&LC Program Coordinator'],
            ['full_name' => 'Channarun You',   'position' => 'Data Management Coordinator'],
            ['full_name' => 'Sambat Chhin',    'position' => 'Accounting and Administrative Assistant'],
            ['full_name' => 'Phally Sung',     'position' => 'Youth Empowerment Project Volunteer'],
            ['full_name' => 'Sony Kheurt',     'position' => 'HR and ICT Volunteer'],
            ['full_name' => 'Socheata Im',     'position' => 'Program Intern'],
            ['full_name' => 'Sokhom Chik',     'position' => 'Office Cleaner'],
            ['full_name' => 'Seen Yoeum',      'position' => 'Dream Management Program Officer'],
            ['full_name' => 'Sievlong Lai',    'position' => 'Dream Management Program Officer'],
            ['full_name' => 'Mara Chek',       'position' => 'Dream Management Program Coordinator'],
        ];

        // Read every image in storage/app/public/staff (any file name)
        $dir = storage_path('app/public/staff');
        $images = File::exists($dir)
            ? collect(File::files($dir))
                ->filter(fn ($f) => in_array(strtolower($f->getExtension()), ['jpg', 'jpeg', 'png', 'webp']))
                ->map(fn ($f) => 'staff/' . $f->getFilename())
                ->sort()
                ->values()
                ->all()
            : [];

        foreach ($staff as $i => $s) {
            // Assign images in order; if there are fewer images than staff, start again from the first
            $photo = count($images) ? $images[$i % count($images)] : null;

            DB::table('staff')->updateOrInsert(
                ['full_name' => $s['full_name']],
                $s + [
                    'email'       => null,
                    'phone'       => null,
                    'photo_path'  => $photo,
                    'hire_date'   => null,
                    'status'      => 'active',
                    'location_id' => null,
                    'program_id'  => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }
    }
}