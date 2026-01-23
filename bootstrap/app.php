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
            'plan.limits' => \App\Http\Middleware\CheckPlanLimits::class,
        ]);

        // Disable Sanctum stateful behaviour to avoid session/CSRF issues
        $middleware->statefulApi(null);

        // Exclude all API routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/login',
            '*', // Aggressive exclusion for testing
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
