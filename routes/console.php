<?php

use App\Console\Commands\GenerateSitemap;
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

/*
|--------------------------------------------------------------------------
| Sitemap — Rebuilt Nightly at 01:30, Production Only
|--------------------------------------------------------------------------
| The directory publishes roughly twelve thousand URLs, far more than search
| engines would find by crawling links alone, so the full list is written to
| public/sitemap.xml and referenced from robots.txt.
|
| Runs after the profile score sync so both read a settled database. Publication
| and teacher records change often enough that a nightly rebuild keeps lastmod
| honest without the cost of generating it per request.
|
| Restricted to production because the URLs are built from APP_URL: on a dev
| machine it would spend every night rewriting a 3 MB file full of localhost
| addresses. Generate it by hand there instead.
|
| MANUAL RUN — this is what to use when going live:
|   php artisan sitemap:generate               builds the file, updates
|                                              robots.txt, and prints the
|                                              generation date, the URL count
|                                              and the Search Console link
|   php artisan sitemap:generate --no-robots   leave robots.txt untouched
|   php artisan sitemap:generate --path=…      write somewhere else
|
| Needs APP_URL set to the public origin — the command refuses to run otherwise,
| since relative sitemap entries are worthless to a crawler. Submission itself is
| manual and one-off: Google and Bing both removed their sitemap ping endpoints,
| so the URL is entered in Search Console once and re-read automatically after.
|--------------------------------------------------------------------------
*/
Schedule::command(GenerateSitemap::class)
    ->dailyAt('01:30')
    ->environments(['production'])
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sitemap.log'))
    ->name('sitemap:generate')
    ->description('Nightly rebuild of public/sitemap.xml');

