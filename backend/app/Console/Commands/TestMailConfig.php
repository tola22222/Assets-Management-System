<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * On-server diagnostic for the "works locally, not on the deployed
 * container" class of mail failures — prints the mailer config Laravel
 * actually resolved (so a stale/uncached .env is obvious) and attempts a
 * real send, surfacing the underlying transport exception instead of the
 * generic message the UI shows.
 */
class TestMailConfig extends Command
{
    protected $signature = 'app:test-mail {email : Address to send the test message to}';

    protected $description = 'Send a test email using the current mail config, to diagnose deploy-only failures';

    public function handle(): int
    {
        $this->info('Resolved mail config:');
        $this->line('  MAIL_MAILER   = ' . config('mail.default'));
        $this->line('  MAIL_HOST     = ' . config('mail.mailers.smtp.host'));
        $this->line('  MAIL_PORT     = ' . config('mail.mailers.smtp.port'));
        $this->line('  MAIL_USERNAME = ' . (config('mail.mailers.smtp.username') ? '(set)' : '(empty)'));
        $this->line('  MAIL_PASSWORD = ' . (config('mail.mailers.smtp.password') ? '(set)' : '(empty)'));
        $this->line('  MAIL_FROM     = ' . config('mail.from.address'));
        $this->newLine();

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER is "log" — emails are written to the log file, not actually sent. This is almost certainly why production isn\'t sending: the deployed .env either never had MAIL_* set or is stale from before it was added.');
        }

        $email = $this->argument('email');

        try {
            Mail::raw('This is a test email from app:test-mail.', function ($message) use ($email) {
                $message->to($email)->subject('PEPY Asset Management — mail test');
            });
            $this->info("Sent successfully to {$email}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Send failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
