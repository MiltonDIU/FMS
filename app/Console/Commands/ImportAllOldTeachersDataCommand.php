<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportAllOldTeachersDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'import:old-teachers-all
                            {--limit=0               : Limit the number of records to process per command}
                            {--dry-run               : Preview without writing to DB}
                            {--skip-existing         : Skip already existing database entries}';

    /**
     * The console command description.
     */
    protected $description = 'Import all old teacher data in sequence (core profiles first, then educations, experiences, memberships, awards, publications, and trainings)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        $options = [];
        if ($limit > 0) {
            $options['--limit'] = $limit;
        }
        if ($dryRun) {
            $options['--dry-run'] = true;
        }
        if ($skipExisting) {
            $options['--skip-existing'] = true;
        }

        $commands = [
            'import:old-teachers',
            'import:old-teachers-educations',
            'import:old-teachers-job-experiences',
            'import:old-teachers-memberships',
            'import:old-teachers-awards',
            'import:old-teachers-publications',
            'import:old-teachers-teaching-areas',
            'import:training-experiences',
        ];

        $this->info("🏁 Starting master import for all old teacher data...");
        $this->newLine();

        foreach ($commands as $command) {
            $this->info("================================================================================");
            $this->info("➡️ Running: php artisan {$command}");
            $this->info("================================================================================");
            
            $exitCode = $this->call($command, $options);
            
            if ($exitCode !== 0) {
                $this->error("❌ Command {$command} failed with exit code: {$exitCode}");
            }
            $this->newLine();
        }

        $this->info("🎉 Master import completed successfully!");
        return Command::SUCCESS;
    }
}
