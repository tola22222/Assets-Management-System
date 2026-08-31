<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        // There is no server-rendered login page anymore — send unauthenticated
        // browser traffic to the SPA's login route. API clients send
        // Accept: application/json and still get a 401 instead of this redirect.
        $middleware->redirectGuestsTo(fn () => '/app/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
