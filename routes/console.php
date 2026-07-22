<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-scheduled-asset-report')->daily();
Schedule::command('notifications:missing-fields')->weeklyOn(1, '08:00');
Schedule::command('notifications:count-reminder')->daily();
