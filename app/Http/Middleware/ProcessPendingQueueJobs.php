<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProcessPendingQueueJobs
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->processPendingJobs();
    }

    /**
     * Process pending jobs in background (non-blocking)
     */
    protected function processPendingJobs(): void
    {
        try {
            // Check if there are any pending jobs
            $pendingCount = DB::table('jobs')
                ->whereNull('reserved_at')
                ->count();

            if ($pendingCount === 0) {
                return;
            }

            // Check if a job is already being processed
            $reservedCount = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->count();

            if ($reservedCount > 0) {
                return;
            }

            // Use lock file to prevent multiple workers
            $lockFile = storage_path('app/queue.lock');
            if (file_exists($lockFile)) {
                $lockTime = filemtime($lockFile);
                // If lock is older than 5 minutes, remove it (stale lock)
                if (time() - $lockTime > 300) {
                    @unlink($lockFile);
                } else {
                    return; // Another process is working
                }
            }

            // Create lock
            touch($lockFile);

            // Start worker in TRULY background mode (non-blocking)
            $phpPath = PHP_BINARY;
            $artisanPath = base_path('artisan');
            $logFile = storage_path('logs/queue-worker.log');
            
            // Use nohup and & to run in background, redirect output
            $command = sprintf(
                'nohup %s %s queue:work --stop-when-empty --tries=3 --timeout=300 >> %s 2>&1 &',
                escapeshellarg($phpPath),
                escapeshellarg($artisanPath),
                escapeshellarg($logFile)
            );

            // Execute without waiting
            if (function_exists('exec')) {
                exec($command);
            }

            // Remove lock after starting (worker will handle its own locking)
            @unlink($lockFile);

        } catch (\Exception $e) {
            \Log::error('Queue processing error: ' . $e->getMessage());
        }
    }
}

