<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'role' => 'staff',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'is_locked' => false,
        ]);
    }

    public function test_a_normal_login_issues_a_short_lived_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertStatus(200);

        $token = $user->tokens()->latest()->first();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->between(now()->addHours(11), now()->addHours(13)));
    }

    public function test_remember_me_issues_a_thirty_day_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ])->assertStatus(200);

        $token = $user->tokens()->latest()->first();
        $this->assertTrue($token->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_locking_a_user_revokes_their_existing_tokens(): void
    {
        $user = $this->makeUser();
        $user->createToken('spa');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->actingAs($opm)->postJson("/api/users/{$user->id}/lock")->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_user_cannot_lock_their_own_account(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager', 'is_locked' => false]);

        $this->actingAs($opm)->postJson("/api/users/{$opm->id}/lock")->assertStatus(422);

        $this->assertFalse($opm->fresh()->is_locked);
    }
}
