<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Auto-process queue jobs on every web request (after response)
        $middleware->appendToGroup('web', \App\Http\Middleware\ProcessPendingQueueJobs::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
