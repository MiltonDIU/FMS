<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunMasterExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout in seconds for this job.
     * Set to 2 hours (7200s) since AI parsing exports can take a long time.
     */
    public int $timeout = 7200;

    protected int $limit;
    protected string $provider;
    protected bool $overwrite;

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit = 0, string $provider = 'auto', bool $overwrite = false)
    {
        $this->limit = $limit;
        $this->provider = $provider;
        $this->overwrite = $overwrite;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting RunMasterExportJob in the background...");

        $options = [
            '--limit' => $this->limit,
            '--provider' => $this->provider,
        ];

        if ($this->overwrite) {
            $options['--overwrite'] = true;
        }

        $exitCode = Artisan::call('export:old-teachers-all', $options);

        if ($exitCode === 0) {
            Log::info("RunMasterExportJob completed successfully.");
        } else {
            Log::error("RunMasterExportJob failed with exit code: {$exitCode}");
        }
    }
}
