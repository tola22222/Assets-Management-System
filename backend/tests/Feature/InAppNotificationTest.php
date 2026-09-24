<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Register changes (create / edit / delete of categories, programs, etc.) used
 * to create no bell notification at all, and registering or verifying an asset
 * only notified the person who did it. They now notify the admins —
 * Operations & HR Manager and Finance — plus the actor, and nobody else.
 */
class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'is_active' => true,
        ], $extra));
    }

    public function test_creating_a_category_notifies_the_admins_and_links_to_the_page(): void
    {
        $opm = $this->user('operations_hr_manager', 'opm@example.test');
        $finance = $this->user('finance_manager', 'fin@example.test');
        $staff = $this->user('staff', 'staff@example.test');
        $locked = $this->user('finance_manager', 'locked@example.test', ['is_locked' => true]);

        Sanctum::actingAs($opm);
        $this->postJson('/api/categories', ['name' => 'Kitchen', 'short_name' => 'KIT'])->assertCreated();

        foreach ([$opm, $finance] as $recipient) {
            $n = Notification::where('user_id', $recipient->id)->sole();
            $this->assertSame('Created category: Kitchen', $n->message);
            $this->assertSame('/app/categories', $n->url);
            $this->assertFalse((bool) $n->is_read);
        }
        $this->assertSame(0, Notification::where('user_id', $staff->id)->count());
        $this->assertSame(0, Notification::where('user_id', $locked->id)->count());
    }

    public function test_the_actor_is_notified_once_even_when_they_are_an_admin(): void
    {
        $opm = $this->user('operations_hr_manager', 'opm@example.test');

        Sanctum::actingAs($opm);
        $this->postJson('/api/categories', ['name' => 'Kitchen', 'short_name' => 'KIT'])->assertCreated();

        $this->assertSame(1, Notification::where('user_id', $opm->id)->count());
    }
}
