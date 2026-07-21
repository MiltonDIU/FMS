<?php

use App\Console\Commands\SyncTeacherProfileScores;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Teacher Profile Score Sync — Scheduled Daily at Midnight (00:00)
|--------------------------------------------------------------------------
| Runs ProfileGapEvaluator on all active, non-archived teachers in chunks
| and stores the result in teachers.profile_score for fast dashboard display.
|
| HOW IT WORKS:
|   1. This file defines the schedule (WHEN to run).
|   2. A server cron entry calls `schedule:run` every minute — Laravel
|      then checks if any scheduled task is due and runs it.
|
| Server cron entry (add via `crontab -e`):
|   * * * * * cd /home/Project/FMS && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
|
| Manual run:
|   php artisan teachers:sync-profile-scores           (skip recently synced)
|   php artisan teachers:sync-profile-scores --force   (re-sync all)
|   php artisan teachers:sync-profile-scores --teacher=5  (single teacher)
|--------------------------------------------------------------------------
*/
Schedule::command(SyncTeacherProfileScores::class, ['--chunk=100'])
    ->dailyAt('00:00')                // রাত ১২টায় প্রতিদিন একবার
    ->withoutOverlapping(60)          // আগের run শেষ না হলে skip (max 60 min lock)
    ->runInBackground()               // Non-blocking — web request block করবে না
    ->appendOutputTo(storage_path('logs/profile-score-sync.log'))
    ->name('teachers:sync-profile-scores')
    ->description('Daily midnight sync of cached profile completion scores');

