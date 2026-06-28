<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::morphMap([
            'staff'   => \App\Models\Staff::class,
            'program' => \App\Models\Program::class,
        ]);

        try {
            $appSettings = Setting::pluck('value', 'key')->toArray();

            if (!empty($appSettings['locale'])) {
                App::setLocale($appSettings['locale']);
            }
        } catch (\Exception $e) {
            $appSettings = [];
        }
        View::share('appSettings', $appSettings);
    }
}
