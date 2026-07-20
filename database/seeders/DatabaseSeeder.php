<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
    }
}
