<?php

namespace App\Console\Commands;

use App\Models\JobExperience;
use App\Models\Teacher;
use Illuminate\Console\Command;

class ImportOldTeachersJobExperiencesCommand extends Command
{
    protected $signature = 'import:old-teachers-job-experiences
                            {--file=teachers_job_experiences_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0                                   : Limit the number of teachers to process}
                            {--dry-run                                   : Preview without writing to DB}
                            {--skip-existing                             : Skip already existing database entries}';

    protected $description = 'Import teacher previous employment and job experiences from exported JSON into the new database (default country set to Bangladesh/18)';

    public function handle(): int
    {
        $file   = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run: php artisan export:old-teachers-job-experiences first.");
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

        $limit        = (int) $this->option('limit');
        $processCount = ($limit > 0 && $limit < count($data)) ? $limit : count($data);

        $this->info($dryRun
            ? "🔍 DRY RUN — no changes will be written to DB"
            : "🚀 Importing job experiences..."
        );
        $this->info("Total records to process: {$processCount}");
        $this->newLine();

        $bar = $this->output->createProgressBar($processCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $imported      = 0;
        $updated       = 0;
        $skipped       = 0;
        $teacherFailed = 0;
        $recordFailed  = 0;
        $count         = 0;

        // Build country name to ID mapping (case-insensitive keys for easy lookup)
        $countryMap = [];
        foreach (\App\Models\Country::all() as $country) {
            $countryMap[mb_strtolower($country->name)] = [
                'id'   => $country->id,
                'name' => $country->name,
            ];
        }

        foreach ($data as $record) {
            if ($limit > 0 && $count >= $limit) break;
            $count++;

            $employeeId = $record['_employee_id'] ?? $record['employee_id'] ?? null;
            $experiences = $record['job_experiences'] ?? [];

            if (!$employeeId) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $teacher = Teacher::where('employee_id', $employeeId)->first();
            if (!$teacher) {
                $teacherFailed++;
                $bar->advance();
                continue;
            }

            if (empty($experiences)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $bar->setMessage("Processing: {$employeeId}");

            foreach ($experiences as $exp) {
                $position     = trim($exp['position'] ?? '');
                $organization = trim($exp['organization'] ?? '');

                if ($position === '' || $organization === '') {
                    $skipped++;
                    continue;
                }

                // Resolve country ID from DB using extracted country name
                $countryId = 18; // Default to Bangladesh ID
                $countryName = 'Bangladesh';

                $extractedCountry = trim($exp['country'] ?? '');
                if ($extractedCountry !== '') {
                    $extractedCountry = \App\Models\Organization::normalizeCountryName($extractedCountry);
                    $searchKey = mb_strtolower($extractedCountry);
                    
                    // 1. Direct case-insensitive match
                    if (isset($countryMap[$searchKey])) {
                        $countryId = $countryMap[$searchKey]['id'];
                        $countryName = $countryMap[$searchKey]['name'];
                    } else {
                        // 2. Substring matching as fallback
                        foreach ($countryMap as $key => $info) {
                            if (stripos($key, $searchKey) !== false || stripos($searchKey, $key) !== false) {
                                $countryId = $info['id'];
                                $countryName = $info['name'];
                                break;
                            }
                        }
                    }
                }

                // Format dates safely: YYYY-MM-DD
                $startDate = null;
                if (!empty($exp['start_year'])) {
                    $month = !empty($exp['start_month']) ? str_pad($exp['start_month'], 2, '0', STR_PAD_LEFT) : '01';
                    $startDate = "{$exp['start_year']}-{$month}-01";
                }

                $endDate = null;
                if (!empty($exp['end_year'])) {
                    $month = !empty($exp['end_month']) ? str_pad($exp['end_month'], 2, '0', STR_PAD_LEFT) : '28';
                    $endDate = "{$exp['end_year']}-{$month}-28";
                }

                // Resolve Position ID
                $positionId = null;
                if ($position !== '') {
                    $positionId = \App\Models\Position::firstOrCreate(
                        ['name' => $position],
                        ['is_active' => true]
                    )->id;
                }

                // Resolve Organization ID
                $organizationId = null;
                if ($organization !== '') {
                    $organizationId = \App\Models\Organization::findOrCreateWithAutoApproval(
                        $organization,
                        null,
                        $countryId,
                        ['is_employer' => true]
                    )->id;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        "\n  [DRY RUN] %-15s → Position: %-30s | Organization: %-30s | Dates: %s to %s",
                        $employeeId,
                        mb_substr($position, 0, 30),
                        mb_substr($organization, 0, 30),
                        $startDate ?? '—',
                        $exp['is_current'] ? 'Current' : ($endDate ?? '—')
                    ));
                    $imported++;
                    continue;
                }

                try {
                    if ($this->option('skip-existing')) {
                        $exists = JobExperience::where([
                            'teacher_id'      => $teacher->id,
                            'position_id'     => $positionId,
                            'organization_id' => $organizationId,
                        ])->exists();
                        if ($exists) {
                            $imported++;
                            continue;
                        }
                    }

                    $result = JobExperience::updateOrCreate(
                        [
                            'teacher_id'      => $teacher->id,
                            'position_id'     => $positionId,
                            'organization_id' => $organizationId,
                        ],
                        [
                            'position'         => $position,
                            'organization'     => $organization,
                            'department'       => $exp['department'] ?? null,
                            'location'         => $exp['location'] ?? null,
                            'country'          => $countryName,
                            'country_id'       => $countryId,
                            'start_date'       => $startDate,
                            'end_date'         => $endDate,
                            'is_current'       => (bool)($exp['is_current'] ?? false),
                            'responsibilities' => $exp['responsibilities'] ?? null,
                            'source'           => 'manual',
                            'sort_order'       => 0,
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Failed to import job experience for {$employeeId} at {$organization}: " . $e->getMessage());
                    $recordFailed++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Teachers processed',                         $count],
                ['Job experiences → NEW (created)',            $imported],
                ['Job experiences → UPDATED (existing)',        $updated],
                ['Records skipped (empty data)',               $skipped],
                ['Teachers not found in new DB',               $teacherFailed],
                ['Individual record failures',                 $recordFailed],
            ]
        );

        if (!$dryRun) {
            $this->info("✅ Import complete.");
        }

        return Command::SUCCESS;
    }
}
