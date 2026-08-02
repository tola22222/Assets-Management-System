<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledReportStaffInclusionTest extends TestCase
{
    use RefreshDatabase;

    private function makeDue(): void
    {
        Setting::updateOrCreate(['key' => 'report_interval_months'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'last_scheduled_report_at'], ['value' => now()->subMonths(2)->toDateTimeString()]);
    }

    public function test_staff_are_excluded_by_default(): void
    {
        $this->makeDue();
        $staff = User::factory()->create(['role' => 'staff', 'email' => 'staff@example.com']);
        User::factory()->create(['role' => 'operations_hr_manager']);

        Artisan::call('app:send-scheduled-asset-report');

        $this->assertDatabaseMissing('notifications', ['user_id' => $staff->id, 'type' => 'scheduled_report']);
    }

    public function test_staff_are_included_once_the_setting_is_turned_on(): void
    {
        $this->makeDue();
        Setting::updateOrCreate(['key' => 'include_staff_in_reports'], ['value' => '1']);
        $staff = User::factory()->create(['role' => 'staff', 'email' => 'staff@example.com']);
        User::factory()->create(['role' => 'operations_hr_manager']);

        Artisan::call('app:send-scheduled-asset-report');

        $this->assertDatabaseHas('notifications', ['user_id' => $staff->id, 'type' => 'scheduled_report']);
    }

    public function test_the_settings_endpoint_persists_the_toggle_as_a_clean_1_or_0(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);

        $this->actingAs($opm)->postJson('/api/settings', ['include_staff_in_reports' => true])->assertStatus(200);
        $this->assertSame('1', Setting::where('key', 'include_staff_in_reports')->value('value'));

        $this->actingAs($opm)->postJson('/api/settings', ['include_staff_in_reports' => false])->assertStatus(200);
        $this->assertSame('0', Setting::where('key', 'include_staff_in_reports')->value('value'));
    }

    public function test_a_staff_member_who_personally_opted_out_is_skipped_even_when_the_role_is_included(): void
    {
        $this->makeDue();
        Setting::updateOrCreate(['key' => 'include_staff_in_reports'], ['value' => '1']);
        $optedOut = User::factory()->create(['role' => 'staff', 'email' => 'optout@example.com', 'receive_reports' => false]);
        $optedIn = User::factory()->create(['role' => 'staff', 'email' => 'optin@example.com', 'receive_reports' => true]);
        User::factory()->create(['role' => 'operations_hr_manager']);

        Artisan::call('app:send-scheduled-asset-report');

        $this->assertDatabaseMissing('notifications', ['user_id' => $optedOut->id, 'type' => 'scheduled_report']);
        $this->assertDatabaseHas('notifications', ['user_id' => $optedIn->id, 'type' => 'scheduled_report']);
    }

    public function test_a_staff_member_can_opt_out_via_their_own_profile(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'A Staffer']);

        $response = $this->actingAs($staff)->postJson('/api/profile', [
            'name' => $staff->name,
            'receive_reports' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($staff->fresh()->receive_reports);
    }

    public function test_receive_reports_defaults_true_for_a_newly_created_user(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->assertTrue($user->fresh()->receive_reports);
    }
}
