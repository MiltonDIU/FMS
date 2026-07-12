<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportOldTeachersTeachingAreasCommand extends Command
{
    protected $signature = 'import:old-teachers-teaching-areas
                            {--file=teachers_teaching_areas_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0 : Limit the number of records to process}
                            {--dry-run : Preview without writing to DB}
                            {--skip-existing : Skip already existing database entries}';

    protected $description = 'Import teacher teaching areas from exported JSON into the new database';

    public function handle(): int
    {
        $file = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run: php artisan export:old-teachers-teaching-areas first.");
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

        $this->info($dryRun ? "🔍 DRY RUN — no changes will be written" : "🚀 Importing teaching areas...");
        $bar = $this->output->createProgressBar($processCount);
        $bar->start();

        $limit = (int) $this->option('limit');
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $count = 0;

        foreach ($data as $record) {
            if ($limit > 0 && $count >= $limit) break;
            $count++;

            $employeeId = $record['employee_id'] ?? null;
            $areas = $record['teaching_areas'] ?? [];

            if (!$employeeId) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $teacher = \App\Models\Teacher::where('employee_id', $employeeId)->first();
            
            if (!$teacher) {
                $failed++;
                $bar->advance();
                continue;
            }

            foreach ($areas as $areaData) {
                if ($dryRun) {
                    $imported++;
                    continue;
                }

                try {
                    if ($this->option('skip-existing')) {
                        $exists = \App\Models\TeachingArea::where([
                            'teacher_id'    => $teacher->id,
                            'area'          => $areaData['area'],
                        ])->exists();
                        if ($exists) {
                            $imported++;
                            continue;
                        }
                    }

                    \App\Models\TeachingArea::updateOrCreate(
                        [
                            'teacher_id'    => $teacher->id,
                            'area'          => $areaData['area'],
                        ],
                        [
                            'description'   => $areaData['description'],
                            'sort_order'    => 0,
                        ]
                    );
                    $imported++;
                } catch (\Exception $e) {
                    $this->error("\nFailed to import teaching area for {$employeeId}: " . $e->getMessage());
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
                ['Areas Processed (Update/Create)', $imported],
                ['Records Skipped', $skipped],
                ['Teachers Not Found', $failed],
            ]
        );

        return Command::SUCCESS;
    }
}
