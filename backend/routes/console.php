<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Checked every hour, not once a day: with a "First send on" date set for
// today, a midnight-only check (00:00 UTC = 07:00 in Cambodia) had already
// passed and the report waited until the next morning. The command itself
// decides whether one is due, so running it hourly never sends twice.
Schedule::command('app:send-scheduled-asset-report')->hourly();
Schedule::command('notifications:missing-fields')->weeklyOn(1, '08:00');
Schedule::command('notifications:count-reminder')->daily();
Schedule::command('notifications:count-discrepancy')->weeklyOn(1, '08:00');
