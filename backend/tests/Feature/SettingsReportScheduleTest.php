<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The settings screen's "Next report due" indicator. It is derived on read
 * rather than stored, so these lock it to the same arithmetic
 * SendScheduledAssetReport uses to decide whether a report is actually due —
 * a screen that disagreed with the scheduler would be worse than no screen.
 */
class SettingsReportScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function opm(): User
    {
        return User::factory()->create(['role' => 'operations_hr_manager']);
    }

    public function test_next_report_due_is_the_last_send_plus_the_configured_interval(): void
    {
        Setting::create(['key' => 'last_scheduled_report_at', 'value' => '2026-08-15 07:46:04']);
        Setting::create(['key' => 'report_interval_months', 'value' => '6']);

        $this->actingAs($this->opm())
            ->getJson('/api/settings')
            ->assertStatus(200)
            ->assertJsonPath('next_report_due', '2027-02-15');
    }

    public function test_next_report_due_falls_back_to_the_six_month_default_interval(): void
    {
        Setting::create(['key' => 'last_scheduled_report_at', 'value' => '2026-08-15 07:46:04']);

        $this->actingAs($this->opm())
            ->getJson('/api/settings')
            ->assertJsonPath('next_report_due', '2027-02-15');
    }

    public function test_next_report_due_is_null_when_no_report_has_ever_been_sent(): void
    {
        Setting::create(['key' => 'report_interval_months', 'value' => '6']);

        $this->actingAs($this->opm())
            ->getJson('/api/settings')
            ->assertJsonPath('next_report_due', null);
    }

    public function test_saving_a_new_interval_returns_the_recalculated_due_date(): void
    {
        Setting::create(['key' => 'last_scheduled_report_at', 'value' => '2026-08-15 07:46:04']);

        // update() responds with the fresh index() payload, which is what lets
        // the page show the new due date without a reload.
        $this->actingAs($this->opm())
            ->postJson('/api/settings', ['report_interval_months' => 12])
            ->assertStatus(200)
            ->assertJsonPath('next_report_due', '2027-08-15');
    }

    public function test_the_settings_payload_stays_opm_only(): void
    {
        Setting::create(['key' => 'last_scheduled_report_at', 'value' => '2026-08-15 07:46:04']);

        foreach (['staff', 'finance_manager', 'executive_director'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/settings')
                ->assertStatus(403);
        }
    }
}
