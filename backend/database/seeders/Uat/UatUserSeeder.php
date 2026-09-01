<?php

namespace Database\Seeders\Uat;

use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Login accounts for every role the system supports, plus the two account
 * states that gate login (locked, inactive) and the three staff site states
 * (own site, other site, no site set).
 *
 * Two Operations/HR accounts are deliberate, not a typo: AssetTransferController
 * aborts with 403 when requested_by === Auth::id(), so with a single OPM the
 * transfer approve/reject path cannot be reached at all.
 *
 * Staff names come from the people actually named in the register source and
 * the kickoff document (Manin Oem, Chhin Chhunly, and the borrower names in the
 * COM range rows) rather than being invented.
 */
class UatUserSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    /** full_name => [position, site code or null, staff status] */
    private const STAFF = [
        'Oem Manin' => ['Operations & HR Manager', 'SR', 'active'],
        'Chhin Chhunly' => ['Finance Manager', 'SR', 'active'],
        'Sok Chamreun' => ['Executive Director', 'SR', 'active'],
        'Nhem Sievlong' => ['Program Officer', 'KL', 'active'],
        'Meas Sokvoeun' => ['ICT Trainer', 'SR', 'active'],
        'Chan Narun' => ['School Focal Point', 'SS', 'active'],
        'Ariel Sophea' => ['Communications Officer', 'SR', 'active'],
        'Ny Konnitha' => ['English Teacher', 'SR', 'active'],
        'Pich Savoeng' => ['School Focal Point', 'VR', 'active'],
        'Kim Solin' => ['School Focal Point', 'BS', 'active'],
        'Vann Samath' => ['Youth Employment Officer', 'SR', 'active'],
        'Sao Korng' => ['School Focal Point', 'KD', 'active'],
        'Ros Dena' => ['Scholarship Officer', 'SR', 'active'],
        'David Chenda' => ['Volunteer Coordinator', null, 'inactive'],
        'Long Pisey' => ['Admin Assistant', 'ST', 'active'],
        'Heng Rithy' => ['School Focal Point', 'RO', 'active'],
    ];

    /** email => [name, role, staff full_name or null, is_active, is_locked] */
    private const USERS = [
        'opm@pepy.test' => ['Oem Manin', 'operations_hr_manager', 'Oem Manin', true, false],
        'opm2@pepy.test' => ['Long Pisey', 'operations_hr_manager', 'Long Pisey', true, false],
        'finance@pepy.test' => ['Chhin Chhunly', 'finance_manager', 'Chhin Chhunly', true, false],
        'ed@pepy.test' => ['Sok Chamreun', 'executive_director', 'Sok Chamreun', true, false],
        'staff.sr@pepy.test' => ['Meas Sokvoeun', 'staff', 'Meas Sokvoeun', true, false],
        'staff.kl@pepy.test' => ['Nhem Sievlong', 'staff', 'Nhem Sievlong', true, false],
        'staff.ss@pepy.test' => ['Chan Narun', 'staff', 'Chan Narun', true, false],
        'staff.nosite@pepy.test' => ['David Chenda', 'staff', 'David Chenda', true, false],
        'locked@pepy.test' => ['Kim Solin', 'staff', 'Kim Solin', true, true],
        'inactive@pepy.test' => ['Sao Korng', 'staff', 'Sao Korng', false, false],
    ];

    public function run(): void
    {
        $sites = Location::whereNotNull('code')->pluck('id', 'code');

        foreach (self::STAFF as $fullName => [$position, $siteCode, $status]) {
            Staff::updateOrCreate(
                ['full_name' => $fullName],
                [
                    'email' => $this->staffEmail($fullName),
                    'phone' => '012 '.str_pad((string) (crc32($fullName) % 1000000), 6, '0', STR_PAD_LEFT),
                    'position' => $position,
                    'hire_date' => now()->subDays(300 + (crc32($fullName) % 1500))->toDateString(),
                    'status' => $status,
                    'location_id' => $siteCode ? ($sites[$siteCode] ?? null) : null,
                ]
            );
        }

        foreach (self::USERS as $email => [$name, $role, $staffName, $isActive, $isLocked]) {
            $staffId = $staffName ? Staff::where('full_name', $staffName)->value('id') : null;

            $user = User::firstOrNew(['email' => $email]);
            $user->fill([
                'name' => $name,
                'role' => $role,
                'staff_id' => $staffId,
                'phone' => '012 '.str_pad((string) (crc32($email) % 1000000), 6, '0', STR_PAD_LEFT),
                'is_active' => $isActive,
                'is_locked' => $isLocked,
                'receive_reports' => true,
            ]);

            // Only set the password on first creation so a re-run never
            // silently resets a password a tester has changed.
            if (! $user->exists) {
                $user->password = Hash::make(self::PASSWORD);
            }

            $user->save();
        }
    }

    private function staffEmail(string $fullName): string
    {
        $slug = strtolower(str_replace(' ', '.', $fullName));

        return $slug.'@pepyempoweringyouth.org';
    }
}
