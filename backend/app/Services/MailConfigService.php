<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Runtime SMTP configuration, stored in the `settings` table instead of the
 * environment.
 *
 * Production's `.env` is rewritten from the `APP_ENV` CI secret on every deploy
 * and has repeatedly shipped with no MAIL_* values at all, so `mail.default`
 * silently fell back to config/mail.php's "log" driver — every notification the
 * system "sent" was written to storage/logs/laravel.log and never delivered.
 * Nothing threw, so it went unnoticed for weeks.
 *
 * Keeping this in the database means it survives deploys, is fixable from the
 * Settings UI without a redeploy, and can be verified from the same screen via
 * SettingController@testMail.
 */
class MailConfigService
{
    /** Setting keys that override config/mail.php. */
    public const KEYS = [
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    /**
     * Read the stored mail settings, decrypting the password.
     *
     * Returns every key so callers never have to null-coalesce. Deliberately
     * never throws: it runs during AppServiceProvider::boot(), which also fires
     * for `artisan migrate` on an empty database where `settings` doesn't exist.
     */
    public static function stored(): array
    {
        $defaults = array_fill_keys(self::KEYS, null);

        try {
            $values = Setting::whereIn('key', self::KEYS)->pluck('value', 'key')->toArray();
        } catch (\Throwable $e) {
            return $defaults;
        }

        $values = array_merge($defaults, $values);

        if (filled($values['mail_password'])) {
            try {
                $values['mail_password'] = Crypt::decryptString($values['mail_password']);
            } catch (\Throwable $e) {
                // Encrypted under a different APP_KEY (a rotated or lost key).
                // Treat as unset rather than handing a ciphertext to the SMTP
                // server, which would fail with a confusing auth error.
                $values['mail_password'] = null;
            }
        }

        return $values;
    }

    /**
     * Push the stored settings into the live mail config.
     *
     * A blank host means "not configured here", in which case whatever
     * config/mail.php resolved from the environment is left untouched — so an
     * environment that *is* correctly configured keeps working unchanged.
     *
     * @param  bool  $purge  Drop an already-resolved mailer so the next send
     *                       rebuilds from this config. Needed after saving from
     *                       the UI (the manager is resolved by then); not during
     *                       boot, where resolving it early is wasteful.
     */
    public static function apply(bool $purge = false): void
    {
        $settings = self::stored();

        if (blank($settings['mail_host'])) {
            return;
        }

        $mailer = $settings['mail_mailer'] ?: 'smtp';
        $encryption = $settings['mail_encryption'];

        config([
            'mail.default' => $mailer,
            "mail.mailers.{$mailer}.transport" => $mailer === 'log' ? 'log' : 'smtp',
            "mail.mailers.{$mailer}.host" => $settings['mail_host'],
            "mail.mailers.{$mailer}.port" => (int) ($settings['mail_port'] ?: 587),
            "mail.mailers.{$mailer}.username" => $settings['mail_username'] ?: null,
            "mail.mailers.{$mailer}.password" => $settings['mail_password'] ?: null,
            // 'none' is the UI's way of saying "no TLS"; config expects null.
            "mail.mailers.{$mailer}.encryption" => $encryption === 'none' ? null : ($encryption ?: 'tls'),
        ]);

        if (filled($settings['mail_from_address'])) {
            config(['mail.from.address' => $settings['mail_from_address']]);
        }

        if (filled($settings['mail_from_name'])) {
            config(['mail.from.name' => $settings['mail_from_name']]);
        }

        if ($purge) {
            Mail::purge($mailer);
        }
    }
}
