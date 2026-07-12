<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunMasterImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout in seconds for this job.
     * Set to 30 minutes (1800s) for database imports.
     */
    public int $timeout = 1800;

    protected int $limit;
    protected bool $dryRun;
    protected bool $skipExisting;

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit = 0, bool $dryRun = false, bool $skipExisting = false)
    {
        $this->limit = $limit;
        $this->dryRun = $dryRun;
        $this->skipExisting = $skipExisting;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting RunMasterImportJob in the background...");

        $options = [
            '--limit' => $this->limit,
        ];

        if ($this->dryRun) {
            $options['--dry-run'] = true;
        }

        if ($this->skipExisting) {
            $options['--skip-existing'] = true;
        }

        $exitCode = Artisan::call('import:old-teachers-all', $options);

        if ($exitCode === 0) {
            Log::info("RunMasterImportJob completed successfully.");
        } else {
            Log::error("RunMasterImportJob failed with exit code: {$exitCode}");
        }
    }
}
