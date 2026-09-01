<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a user account used to fail with a raw SQL 500 the moment that
 * account had signed in even once: AuthController::login writes an ActivityLog
 * row on every login, and activity_logs.user_id was NOT NULL with a restrictive
 * foreign key. In practice that meant no real user could ever be deleted.
 *
 * The other half of the same problem pointed the opposite way — the workflow
 * tables cascaded, so a successful delete would have taken the person's
 * transfer and disposal requests with it. Those rows are history about an
 * asset, not personal data, and must outlive the account.
 */
class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Operations Manager',
            'email' => 'opm@example.test',
            'password' => bcrypt('password123'),
            'role' => 'operations_hr_manager',
            'is_active' => true,
        ]);
    }

    private function staffUser(string $email = 'staff@example.test'): User
    {
        return User::create([
            'name' => 'Site Staff',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    public function test_a_user_who_has_signed_in_can_still_be_deleted(): void
    {
        $admin = $this->admin();
        $staff = $this->staffUser();

        // Exactly what login() does, and the thing that used to block deletion.
        $this->postJson('/api/login', ['email' => $staff->email, 'password' => 'password123'])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'Login']);

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$staff->id}")
            ->assertOk()
            ->assertJson(['message' => 'User deleted.']);

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_deleting_a_user_keeps_their_activity_log_entries(): void
    {
        $admin = $this->admin();
        $staff = $this->staffUser();

        ActivityLog::create([
            'user_id' => $staff->id,
            'action' => 'Flag',
            'description' => 'Site Staff flagged an issue on an asset.',
        ]);

        $this->actingAs($admin)->deleteJson("/api/users/{$staff->id}")->assertOk();

        // The row survives with no owner — the activity log screen renders that
        // as "System", and the description still names who did it.
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'description' => 'Site Staff flagged an issue on an asset.',
        ]);
    }

    public function test_deleting_a_user_does_not_delete_their_transfer_requests(): void
    {
        $admin = $this->admin();
        $staff = $this->staffUser();

        // The 13 PEPY sites are created by migration, so look them up rather
        // than inserting duplicates of a unique site code.
        $category = AssetCategory::firstOrCreate(['short_name' => 'COM'], ['name' => 'Computer Equipment']);
        $from = Location::firstOrCreate(['code' => 'SR'], ['name' => 'PEPY Office', 'type' => 'office']);
        $to = Location::firstOrCreate(['code' => 'KL'], ['name' => 'Kralanh HS', 'type' => 'program']);
        $asset = Asset::create([
            'asset_code' => 'PEY-SR-COM-9001',
            'name' => 'Laptop',
            'category_id' => $category->id,
            'location_id' => $from->id,
            'condition' => 'good',
            'status' => 'active',
        ]);

        $transfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'requested_by' => $staff->id,
            'status' => 'pending',
            'transfer_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->deleteJson("/api/users/{$staff->id}")->assertOk();

        // The request is a record about the ASSET. Losing it because the person
        // who raised it left would put a hole in the register's history.
        $this->assertDatabaseHas('asset_transfers', ['id' => $transfer->id, 'requested_by' => null]);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJson(['message' => 'You cannot delete your own account.']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_only_an_operations_manager_may_delete_a_user(): void
    {
        $target = $this->staffUser('target@example.test');

        foreach (['finance_manager', 'executive_director', 'staff'] as $role) {
            $actor = User::create([
                'name' => ucfirst($role),
                'email' => "{$role}@example.test",
                'password' => bcrypt('password123'),
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($actor)
                ->deleteJson("/api/users/{$target->id}")
                ->assertForbidden();
        }

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
