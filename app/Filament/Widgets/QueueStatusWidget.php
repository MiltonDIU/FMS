<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class QueueStatusWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // Check if queue worker is running
        $status = $this->getQueueWorkerStatus();

        // Get pending jobs count
        $pendingJobs = DB::table('jobs')->count();

        // Get failed jobs count
        $failedJobs = DB::table('failed_jobs')->count();

        return [
            Stat::make('Queue Worker Status', $status['label'])
                ->description($status['description'])
                ->color($status['color'])
                ->icon($status['icon']),

            Stat::make('Pending Jobs', $pendingJobs)
                ->description('Jobs waiting to be processed')
                ->color($pendingJobs > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),

            Stat::make('Failed Jobs', $failedJobs)
                ->description($failedJobs > 0 ? 'Check failed jobs table' : 'No failures')
                ->color($failedJobs > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    /**
     * Get queue worker status with detailed info
     */
    protected function getQueueWorkerStatus(): array
    {
        $pendingCount = DB::table('jobs')->count();

        // If no pending jobs, we can't determine if worker is running
        if ($pendingCount === 0) {
            return [
                'label' => 'Idle',
                'description' => 'No jobs to process',
                'color' => 'gray',
                'icon' => 'heroicon-o-pause-circle',
            ];
        }

        // Check if there are jobs that have been reserved (being processed)
        $reservedCount = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->count();

        // If jobs are reserved, worker is definitely running
        if ($reservedCount > 0) {
            return [
                'label' => 'Running ✓',
                'description' => "Processing {$reservedCount} job(s)",
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ];
        }

        // Jobs pending but nothing reserved = worker is stopped
        return [
            'label' => 'Stopped ✗',
            'description' => "Start: php artisan queue:work",
            'color' => 'danger',
            'icon' => 'heroicon-o-x-circle',
        ];
    }

    /**
     * Refresh widget every 10 seconds
     */
    protected  ?string $pollingInterval = '10s';
}
