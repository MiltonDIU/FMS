<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExportAllOldTeachersDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'export:old-teachers-all
                            {--limit=0               : Limit the number of teachers processed per command (0 = all)}
                            {--provider=auto         : AI provider to use: auto|openrouter|vertex|gemini|groq|anthropic|deepseek|heuristic}
                            {--overwrite             : Overwrite output files and re-process all records}';

    /**
     * The console command description.
     */
    protected $description = 'Export all old teacher data in sequence using AI parsers (core profiles, educations, experiences, memberships, awards, publications, teaching areas, and trainings)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $provider = $this->option('provider');
        $overwrite = (bool) $this->option('overwrite');

        $commonOptions = [];
        if ($limit > 0) {
            $commonOptions['--limit'] = $limit;
        }
        if ($overwrite) {
            $commonOptions['--overwrite'] = true;
        }

        // List of all export commands and their specific options
        $commands = [
            'export:old-teachers'                  => [], // Core profiles
            'export:old-teachers-educations'        => ['--provider' => $provider],
            'export:old-teachers-job-experiences'  => ['--provider' => $provider],
            'export:old-teachers-memberships'      => ['--provider' => $provider],
            'export:old-teachers-awards'            => ['--provider' => $provider],
            'export:old-teachers-publications'      => ['--provider' => $provider],
            'export:old-teachers-teaching-areas'   => [],
            'export:training-experiences'          => [],
        ];

        $this->info("🏁 Starting master export for all old teacher data...");
        $this->newLine();
        \App\Models\Setting::set('export_progress', 'Started master export...');

        $totalCommands = count($commands);
        $currentIndex = 0;

        foreach ($commands as $command => $specificOptions) {
            $currentIndex++;
            \App\Models\Setting::set('export_progress', "Running: php artisan {$command} ({$currentIndex} / {$totalCommands})");
            $this->info("================================================================================");
            $this->info("➡️ Running: php artisan {$command}");
            $this->info("================================================================================");
            
            // Merge options
            $options = array_merge($commonOptions, $specificOptions);
            
            $exitCode = $this->call($command, $options);
            
            if ($exitCode !== 0) {
                \App\Models\Setting::set('export_progress', "Failed on command: {$command}");
                $this->error("❌ Command {$command} failed with exit code: {$exitCode}");
            }
            $this->newLine();
        }

        \App\Models\Setting::set('export_progress', 'Master export completed successfully!');

        $this->info("🎉 Master export completed successfully!");
        return Command::SUCCESS;
    }
}
