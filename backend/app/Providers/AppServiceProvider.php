<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\MailConfigService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::morphMap([
            'staff' => \App\Models\Staff::class,
            'program' => \App\Models\Program::class,
        ]);

        try {
            $appSettings = Setting::pluck('value', 'key')->toArray();

            if (! empty($appSettings['locale'])) {
                App::setLocale($appSettings['locale']);
            }
        } catch (\Exception $e) {
            $appSettings = [];
        }
        View::share('appSettings', $appSettings);

        // SMTP credentials live in the settings table, not the environment —
        // production's .env is regenerated from a CI secret each deploy and has
        // shipped without MAIL_* more than once, silently downgrading every
        // notification to the "log" driver. Runs for CLI too, so the scheduled
        // notification commands send with the same config as the web app.
        MailConfigService::apply();
    }
}
