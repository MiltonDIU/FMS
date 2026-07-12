<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportOldTeachersAwardsCommand extends Command
{
    protected $signature = 'import:old-teachers-awards
                            {--file=teachers_awards_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0 : Limit the number of records to process}
                            {--dry-run : Preview without writing to DB}
                            {--skip-existing : Skip already existing database entries}';

    protected $description = 'Import teacher awards/scholarships from exported JSON into the new database';

    public function handle(): int
    {
        $file = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run: php artisan export:old-teachers-awards first.");
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

        $this->info($dryRun ? "🔍 DRY RUN — no changes will be written" : "🚀 Importing awards...");
        $bar = $this->output->createProgressBar($processCount);
        $bar->start();

        // Build country name to ID mapping (case-insensitive keys for easy lookup)
        $countryMap = [];
        foreach (\App\Models\Country::all() as $country) {
            $countryMap[mb_strtolower($country->name)] = [
                'id'   => $country->id,
                'name' => $country->name,
            ];
        }

        $limit = (int) $this->option('limit');
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $count = 0;

        foreach ($data as $record) {
            if ($limit > 0 && $count >= $limit) break;
            $count++;

            $employeeId = $record['employee_id'] ?? $record['_employee_id'] ?? null;
            $oldTeacherId = $record['old_teacher_id'] ?? $record['_old_teacher_id'] ?? null;
            $awards = $record['awards'] ?? [];

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

            foreach ($awards as $awardData) {
                if ($dryRun) {
                    $imported++;
                    continue;
                }

                try {
                    if ($this->option('skip-existing')) {
                        $exists = \App\Models\Award::where([
                            'teacher_id'    => $teacher->id,
                            'title'         => $awardData['title'],
                            'year'          => $awardData['year'],
                        ])->exists();
                        if ($exists) {
                            $imported++;
                            continue;
                        }
                    }

                    // Resolve Country ID if provided
                    $countryId = null;
                    $extractedCountry = trim($awardData['country'] ?? '');
                    if ($extractedCountry !== '') {
                        $extractedCountry = \App\Models\Organization::normalizeCountryName($extractedCountry);
                        $cSearch = mb_strtolower($extractedCountry);
                        if (isset($countryMap[$cSearch])) {
                            $countryId = $countryMap[$cSearch]['id'];
                        } else {
                            foreach ($countryMap as $cKey => $cInfo) {
                                if (stripos($cKey, $cSearch) !== false || stripos($cSearch, $cKey) !== false) {
                                    $countryId = $cInfo['id'];
                                    break;
                                }
                            }
                        }
                    }

                    // Resolve Awarding Body Organization ID
                    $awardingBodyOrgId = null;
                    $awardingBody = trim($awardData['awarding_body'] ?? '');
                    if ($awardingBody !== '') {
                        $awardingBodyOrgId = \App\Models\Organization::findOrCreateWithAutoApproval(
                            $awardingBody,
                            null,
                            $countryId,
                            ['is_awarding_body' => true]
                        )->id;
                    }

                    \App\Models\Award::updateOrCreate(
                        [
                            'teacher_id'    => $teacher->id,
                            'title'         => $awardData['title'],
                            'year'          => $awardData['year'],
                        ],
                        [
                            'awarding_body' => $awardingBody,
                            'awarding_body_organization_id' => $awardingBodyOrgId,
                            'type'          => $awardData['type'],
                            'remarks'       => $awardData['remarks'],
                            'sort_order'    => 0,
                        ]
                    );
                    $imported++;
                } catch (\Exception $e) {
                    $this->error("\nFailed to import award for {$employeeId}: " . $e->getMessage());
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
                ['Awards Processed (Update/Create)', $imported],
                ['Records Skipped', $skipped],
                ['Teachers Not Found', $failed],
            ]
        );

        return Command::SUCCESS;
    }
}
