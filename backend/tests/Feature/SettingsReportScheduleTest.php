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

    public function test_the_interval_can_be_days_months_or_years(): void
    {
        Setting::create(['key' => 'last_scheduled_report_at', 'value' => '2026-08-15 07:46:04']);
        $opm = $this->opm();

        $this->actingAs($opm)->postJson('/api/settings', ['report_interval' => 10, 'report_interval_unit' => 'day'])
            ->assertOk()
            ->assertJsonPath('report_interval', 10)
            ->assertJsonPath('report_interval_unit', 'day')
            ->assertJsonPath('next_report_due', '2026-08-25');

        $this->actingAs($opm)->postJson('/api/settings', ['report_interval' => 1, 'report_interval_unit' => 'year'])
            ->assertOk()->assertJsonPath('next_report_due', '2027-08-15');

        $this->actingAs($opm)->postJson('/api/settings', ['report_interval' => 2, 'report_interval_unit' => 'month'])
            ->assertOk()->assertJsonPath('next_report_due', '2026-10-15');

        $this->actingAs($opm)->postJson('/api/settings', ['report_interval_unit' => 'week'])
            ->assertStatus(422)->assertJsonValidationErrors('report_interval_unit');
    }

    public function test_the_scheduled_report_attaches_the_inventory_list_and_respects_a_days_interval(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $opm = $this->opm();
        Setting::create(['key' => 'report_interval', 'value' => '7']);
        Setting::create(['key' => 'report_interval_unit', 'value' => 'day']);

        // 3 days after the last send: not due on a 7-day interval.
        Setting::create(['key' => 'last_scheduled_report_at', 'value' => now()->subDays(3)->toDateTimeString()]);
        $this->artisan('app:send-scheduled-asset-report')->assertSuccessful();
        \Illuminate\Support\Facades\Mail::assertNothingSent();

        // 8 days after: due, and the register goes along in the Inventory List template.
        Setting::where('key', 'last_scheduled_report_at')->update(['value' => now()->subDays(8)->toDateTimeString()]);
        $this->artisan('app:send-scheduled-asset-report')->assertSuccessful();

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ScheduledAssetReportMail::class, function ($mail) use ($opm) {
            $attachments = $mail->attachments();

            return $mail->hasTo($opm->email)
                && count($attachments) === 1
                && str_ends_with($attachments[0]->as, '.xlsx')
                && str_starts_with($mail->file, 'PK');
        });
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

        foreach (['staff', 'executive_director'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/settings')
                ->assertStatus(403);
        }

        // The Accountant gets the Appearance part only — no report schedule.
        $this->actingAs(User::factory()->create(['role' => 'finance_manager']))
            ->getJson('/api/settings')->assertOk()
            ->assertJsonMissingPath('next_report_due')
            ->assertJsonMissingPath('last_scheduled_report_at');
    }
}
