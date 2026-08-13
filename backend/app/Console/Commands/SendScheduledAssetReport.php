<?php

namespace App\Console\Commands;

use App\Mail\ScheduledAssetReportMail;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledAssetReport extends Command
{
    protected $signature = 'app:send-scheduled-asset-report {--force : Send immediately, ignoring the baseline/interval throttle — for verifying mail delivery on demand}';

    protected $description = 'Email Finance Manager/Executive Director/Operations-HR Manager a periodic asset register summary';

    public function handle(): int
    {
        $intervalMonths = (int) (Setting::where('key', 'report_interval_months')->value('value') ?? 6);
        $lastSentAt = Setting::where('key', 'last_scheduled_report_at')->value('value');
        $force = (bool) $this->option('force');

        if (! $lastSentAt && ! $force) {
            Setting::updateOrCreate(['key' => 'last_scheduled_report_at'], ['value' => now()->toDateTimeString()]);
            $this->info('No prior report on record — baseline set to now. First report will send in '.$intervalMonths.' month(s). Use --force to send immediately instead.');

            return self::SUCCESS;
        }

        if ($lastSentAt && ! $force) {
            $lastSentAt = Carbon::parse($lastSentAt);
            if (now()->lessThan($lastSentAt->copy()->addMonths($intervalMonths))) {
                $this->info('Not due yet. Next due: '.$lastSentAt->copy()->addMonths($intervalMonths)->toDateString().'. Use --force to send immediately instead.');

                return self::SUCCESS;
            }
        }

        // --force on a database with no prior report has no real "since" date
        // to summarize from — fall back to one interval ago so the summary
        // still covers a sensible window instead of "everything ever".
        $lastSentAt = $lastSentAt ? Carbon::parse($lastSentAt) : now()->copy()->subMonths($intervalMonths);

        $summary = ScheduledAssetReportMail::buildSummary($lastSentAt);

        $periodLabel = now()->format('F Y');
        $recipients = User::whereIn('role', ['finance_manager', 'executive_director', 'operations_hr_manager'])->get();

        // Off by default — staff aren't normally part of the counting-cycle
        // report audience (they can't even pull reports themselves), but an
        // admin can opt the whole role in from Settings. Even then, each
        // staff member can personally opt back out from their own Profile —
        // unlike the three roles above, which have no opt-out.
        if (Setting::where('key', 'include_staff_in_reports')->value('value') === '1') {
            $recipients = $recipients->merge(User::where('role', 'staff')->where('receive_reports', true)->get());
        }

        $emailedTo = [];

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'type' => 'scheduled_report',
                'message' => "Your {$periodLabel} asset summary report is ready — {$summary['total_assets']} assets on register.",
                'url' => route('reports.index'),
            ]);

            if ($recipient->email) {
                try {
                    Mail::to($recipient->email)->send(new ScheduledAssetReportMail($summary, $periodLabel));
                    $emailedTo[] = strtolower($recipient->email);
                } catch (\Throwable $e) {
                    Log::warning('Scheduled asset report email failed for '.$recipient->email.': '.$e->getMessage());
                }
            }
        }

        // An extra recipient configured on the Settings page, alongside the
        // role-based ones above — lets an admin route the report to an inbox
        // that isn't necessarily any user's own account email.
        $extraEmail = Setting::where('key', 'report_recipient_email')->value('value');
        if ($extraEmail && ! in_array(strtolower($extraEmail), $emailedTo, true)) {
            try {
                Mail::to($extraEmail)->send(new ScheduledAssetReportMail($summary, $periodLabel));
                $emailedTo[] = strtolower($extraEmail);
            } catch (\Throwable $e) {
                Log::warning('Scheduled asset report email failed for '.$extraEmail.': '.$e->getMessage());
            }
        }

        Setting::updateOrCreate(['key' => 'last_scheduled_report_at'], ['value' => now()->toDateTimeString()]);

        $this->info('Scheduled asset report sent to '.count($emailedTo).' recipient(s).');

        return self::SUCCESS;
    }
}
