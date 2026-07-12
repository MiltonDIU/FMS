<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Teacher;
use App\Models\TrainingExperience;

class ImportTrainingExperiencesCommand extends Command
{
    protected $signature = 'import:training-experiences
                            {--file=training_experiences_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0 : Limit the number of records to process}
                            {--dry-run : Preview without writing to DB}
                            {--skip-existing : Skip already existing database entries}';

    protected $description = 'Import teacher training experiences from exported JSON into the new database';

    public function handle(): int
    {
        $file = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run: php artisan export:training-experiences first.");
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error("Invalid JSON in {$file}");
            return Command::FAILURE;
        }

        // Trim all string values recursively
        array_walk_recursive($data, function (&$val) {
            if (is_string($val)) {
                $val = trim($val);
            }
        });

        $limit = (int) $this->option('limit');
        $processCount = ($limit > 0 && $limit < count($data)) ? $limit : count($data);

        $this->info($dryRun ? "🔍 DRY RUN — no changes will be written" : "🚀 Importing training experiences...");
        $bar = $this->output->createProgressBar($processCount);
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $count = 0;

        foreach ($data as $record) {
            if ($limit > 0 && $count >= $limit) break;
            $count++;

            $employeeId = $record['employee_id'] ?? $record['_employee_id'] ?? null;
            $trainings = $record['training_experiences'] ?? [];

            if (!$employeeId) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $teacher = Teacher::where('employee_id', $employeeId)->first();
            
            if (!$teacher) {
                $failed++;
                $bar->advance();
                continue;
            }

            foreach ($trainings as $trData) {
                if ($dryRun) {
                    $imported++;
                    continue;
                }

                try {
                    if ($this->option('skip-existing')) {
                        $exists = TrainingExperience::where([
                            'teacher_id'   => $teacher->id,
                            'title'        => $trData['title'],
                            'organization' => $trData['organization'] ?? '',
                            'year'         => $trData['year'] ?? null,
                        ])->exists();
                        if ($exists) {
                            $imported++;
                            continue;
                        }
                    }

                    // Resolve Organization ID
                    $organizationId = null;
                    $orgName = trim($trData['organization'] ?? '');
                    if ($orgName !== '') {
                        $organizationId = \App\Models\Organization::findOrCreateWithAutoApproval(
                            $orgName,
                            null,
                            $trData['country_id'] ?? null,
                            ['is_training_center' => true]
                        )->id;
                    }

                    // Match uniquely by teacher_id, title, and organization/year/completion_date to avoid duplicates
                    TrainingExperience::updateOrCreate(
                        [
                            'teacher_id'   => $teacher->id,
                            'title'        => $trData['title'],
                            'year'         => $trData['year'] ?? null,
                        ],
                        [
                            'organization'    => $orgName,
                            'organization_id' => $organizationId,
                            'category'        => $trData['category'] ?? null,
                            'duration_days'   => $trData['duration_days'] ?? null,
                            'completion_date' => $trData['completion_date'] ?? null,
                            'country_id'      => $trData['country_id'] ?? null,
                            'is_online'       => (bool) ($trData['is_online'] ?? false),
                            'description'     => $trData['description'] ?? null,
                            'sort_order'      => $trData['sort_order'] ?? 0,
                        ]
                    );
                    $imported++;
                } catch (\Exception $e) {
                    $this->error("\nFailed to import training experience for {$employeeId}: " . $e->getMessage());
                    $failed++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Records Processed', $count],
                ['Training Records Processed (Update/Create)', $imported],
                ['Records Skipped', $skipped],
                ['Teachers Not Found', $failed],
            ]
        );

        return Command::SUCCESS;
    }
}
