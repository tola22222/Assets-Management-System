<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Setting;
use App\Services\AssetNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Fires two emails per count cycle, per the Asset Checking & Counting Manual
 * (counts happen the 1st of February and August): one ~2 weeks before, one
 * on the count date itself. Idempotent per-day via a Setting row so running
 * the daily schedule twice never double-sends.
 *
 * NOTE: this overlaps in purpose with the pre-existing
 * `app:send-scheduled-asset-report` command, which reminds on a rolling
 * `report_interval_months` interval since the last count rather than a fixed
 * Feb/Aug calendar. Both are wired into the schedule for now — pick one as
 * the canonical reminder before this ships to avoid HR getting two different
 * "count is due" emails.
 */
class SendCountReminderNotification extends Command
{
    protected $signature = 'notifications:count-reminder';

    protected $description = 'Email a count reminder ~2 weeks before, and on, each Feb/Aug asset count date';

    public function handle(AssetNotificationService $notifications): int
    {
        $today = Carbon::today();
        $lastSentFor = Setting::where('key', 'last_count_reminder_sent_for')->value('value');

        foreach ($this->upcomingCountDates($today) as $countDate) {
            foreach (['reminder' => $countDate->copy()->subDays(14), 'due' => $countDate] as $kind => $fireOn) {
                $marker = $countDate->toDateString().':'.$kind;

                if (! $today->isSameDay($fireOn) || $lastSentFor === $marker) {
                    continue;
                }

                $notifications->send('COUNT_REMINDER', [
                    'note' => 'Locations to cover: '.Location::orderBy('name')->pluck('name')->implode(', '),
                    'url' => route('reports.inventory'),
                    'extraData' => ['date' => $countDate->toFormattedDateString()],
                ]);

                Setting::updateOrCreate(['key' => 'last_count_reminder_sent_for'], ['value' => $marker]);
                $this->info("Sent {$kind} reminder for the ".$countDate->toDateString().' count.');

                return self::SUCCESS;
            }
        }

        $this->info('No count reminder due today.');

        return self::SUCCESS;
    }

    /** The two count dates (Feb 1 / Aug 1) nearest to today, past and future. */
    private function upcomingCountDates(Carbon $today): array
    {
        $year = $today->year;

        return [
            Carbon::create($year, 2, 1),
            Carbon::create($year, 8, 1),
            Carbon::create($year + 1, 2, 1),
        ];
    }
}
