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
        // Auto-process queue jobs on every web request (after response)
        $middleware->appendToGroup('web', \App\Http\Middleware\ProcessPendingQueueJobs::class);

        // System Settings → Maintenance Mode. Appended, so the session has
        // started and a signed-in admin can be let through.
        $middleware->appendToGroup('web', \App\Http\Middleware\ShowMaintenancePage::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\ShowMaintenancePage::class);

        // Authentication lives in the Filament panel, so there is no "login"
        // named route for Laravel's Authenticate middleware to fall back on.
        // Without this, any `auth`-guarded web route 500s on a guest request.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // While maintenance mode is on, an unknown URL or a failing page must
        // not show its 404 or error screen either — only the maintenance page.
        // Returns null (normal rendering) for the admin panel and when off.
        $exceptions->render(fn (\Throwable $e, \Illuminate\Http\Request $request) => \App\Helpers\MaintenanceMode::responseFor($request));
    })->create();
