<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\MailConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function opm(): User
    {
        return User::factory()->create(['role' => 'operations_hr_manager']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'sender@example.com',
            'mail_password' => 'super-secret',
            'mail_from_address' => 'sender@example.com',
            'mail_from_name' => 'PEPY Assets',
        ], $overrides);
    }

    public function test_smtp_password_is_stored_encrypted_not_in_plain_text(): void
    {
        $this->actingAs($this->opm())->postJson('/api/settings', $this->payload())->assertStatus(200);

        $stored = Setting::where('key', 'mail_password')->value('value');

        $this->assertNotSame('super-secret', $stored);
        $this->assertSame('super-secret', Crypt::decryptString($stored));
    }

    public function test_the_password_is_never_returned_to_the_client(): void
    {
        $this->actingAs($this->opm())->postJson('/api/settings', $this->payload())->assertStatus(200);

        $response = $this->actingAs($this->opm())->getJson('/api/settings')->assertStatus(200);

        $response->assertJsonMissingPath('mail_password');
        $response->assertJsonPath('mail_password_set', true);
        $this->assertStringNotContainsString('super-secret', $response->getContent());
    }

    public function test_saving_with_a_blank_password_keeps_the_existing_one(): void
    {
        $opm = $this->opm();
        $this->actingAs($opm)->postJson('/api/settings', $this->payload())->assertStatus(200);

        // The form reloads with an empty password field every time, so a plain
        // "Save" must not wipe a working password.
        $this->actingAs($opm)->postJson('/api/settings', $this->payload(['mail_password' => '']))->assertStatus(200);

        $this->assertSame('super-secret', Crypt::decryptString(Setting::where('key', 'mail_password')->value('value')));
    }

    public function test_stored_settings_override_the_environment_mail_config(): void
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);

        $this->actingAs($this->opm())->postJson('/api/settings', $this->payload())->assertStatus(200);

        MailConfigService::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('super-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('sender@example.com', config('mail.from.address'));
    }

    public function test_an_unconfigured_host_leaves_the_environment_config_untouched(): void
    {
        config(['mail.default' => 'log']);

        MailConfigService::apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_a_password_encrypted_under_a_different_app_key_is_treated_as_unset(): void
    {
        Setting::create(['key' => 'mail_host', 'value' => 'smtp.gmail.com']);
        Setting::create(['key' => 'mail_password', 'value' => 'not-valid-ciphertext']);

        // Must not throw a DecryptException and take every request down with it.
        $this->assertNull(MailConfigService::stored()['mail_password']);
    }

    public function test_only_opm_can_read_or_change_mail_settings(): void
    {
        foreach (['staff', 'finance_manager', 'executive_director'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/api/settings')->assertStatus(403);
            $this->actingAs($user)->postJson('/api/settings', $this->payload())->assertStatus(403);
            $this->actingAs($user)->postJson('/api/settings/test-mail', ['email' => 'a@b.com'])->assertStatus(403);
        }
    }

    public function test_test_mail_refuses_to_pretend_it_sent_when_the_driver_is_log(): void
    {
        $this->actingAs($this->opm())->postJson('/api/settings', $this->payload([
            'mail_mailer' => 'log',
        ]))->assertStatus(200);

        $this->actingAs($this->opm())
            ->postJson('/api/settings/test-mail', ['email' => 'someone@example.com'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Mail driver is set to "log", so nothing is actually delivered. Set an SMTP host and save before testing.']);
    }
}
