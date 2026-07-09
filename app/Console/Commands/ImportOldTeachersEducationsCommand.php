<?php

namespace App\Console\Commands;

use App\Models\Education;
use App\Models\Teacher;
use Illuminate\Console\Command;

class ImportOldTeachersEducationsCommand extends Command
{
    protected $signature = 'import:old-teachers-educations
                            {--file=teachers_educations_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0                             : Limit the number of teachers to process}
                            {--dry-run                             : Preview without writing to DB}
                            {--skip-existing                       : Skip already existing database entries}';

    protected $description = 'Import teacher educational qualifications and academic history from exported JSON (resolving degree types, result types, and countries dynamically)';

    public function handle(): int
    {
        $file   = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info("Run: php artisan export:old-teachers-educations first.");
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error("Invalid JSON in {$file}");
            return Command::FAILURE;
        }

        $limit        = (int) $this->option('limit');
        $processCount = ($limit > 0 && $limit < count($data)) ? $limit : count($data);

        $this->info($dryRun
            ? "🔍 DRY RUN — no changes will be written to DB"
            : "🚀 Importing educational qualifications..."
        );
        $this->info("Total records to process: {$processCount}");
        $this->newLine();

        // 1. Build Country lookup map
        $countryMap = [];
        foreach (\App\Models\Country::all() as $country) {
            $countryMap[mb_strtolower($country->name)] = [
                'id'   => $country->id,
                'name' => $country->name,
            ];
        }

        // 2. Build ResultType lookup map
        $resultTypeMap = [];
        foreach (\App\Models\ResultType::all() as $rt) {
            $resultTypeMap[mb_strtolower($rt->type_name)] = $rt->id;
        }

        // 3. Build DegreeType lookup map with custom normalized names
        $degreeTypeMap = [];
        foreach (\App\Models\DegreeType::all() as $dt) {
            $normalizedName = $this->normalizeString($dt->name);
            $degreeTypeMap[$normalizedName] = [
                'id'   => $dt->id,
                'name' => $dt->name,
            ];
        }

        // Custom mappings for common short degree names
        $shortDegreeMappings = [
            'phd'   => 'doctorofphilosophy',
            'ph.d'  => 'doctorofphilosophy',
            'msc'   => 'masterofscience',
            'bsc'   => 'bachelorofscience',
            'mba'   => 'masterofbusinessadministration',
            'bba'   => 'bachelorofbusinessadministration',
            'hsc'   => 'highersecondarycertificate',
            'ssc'   => 'secondaryschoolcertificate',
            'mphil' => 'masterofphilosophy',
            'ma'    => 'masterofarts',
            'ba'    => 'bachelorofarts',
            'llb'   => 'bacheloroflaws',
            'llm'   => 'masteroflaws',
        ];

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

        foreach ($data as $record) {
            if ($limit > 0 && $count >= $limit) break;
            $count++;

            $employeeId = $record['_employee_id'] ?? $record['employee_id'] ?? null;
            $educations = $record['educations'] ?? [];

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

            if (empty($educations)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $bar->setMessage("Processing: {$employeeId}");

            foreach ($educations as $edu) {
                $degreeName  = trim($edu['degree_name'] ?? '');
                $institution = trim($edu['institution'] ?? '');

                if ($degreeName === '' || $institution === '') {
                    $skipped++;
                    continue;
                }

                // 1. Resolve Country ID (Default 18 - Bangladesh)
                $countryId = 18;
                $extractedCountry = trim($edu['country'] ?? '');
                if ($extractedCountry !== '') {
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

                // 2. Resolve Result Type ID
                $resType = mb_strtolower(trim($edu['result_type'] ?? 'Not Applicable'));
                $resultTypeId = $resultTypeMap[$resType] ?? $resultTypeMap['not applicable'] ?? null;

                // 3. Resolve Degree Type ID
                $degreeTypeId = null;
                $degreeSearch = $this->normalizeString($degreeName);

                // Use short name mapping if applicable
                if (isset($shortDegreeMappings[$degreeSearch])) {
                    $degreeSearch = $shortDegreeMappings[$degreeSearch];
                }

                // Match with database degree types
                if (isset($degreeTypeMap[$degreeSearch])) {
                    $degreeTypeId = $degreeTypeMap[$degreeSearch]['id'];
                } else {
                    // Try containing search
                    foreach ($degreeTypeMap as $dbNorm => $dbInfo) {
                        if (stripos($dbNorm, $degreeSearch) !== false || stripos($degreeSearch, $dbNorm) !== false) {
                            $degreeTypeId = $dbInfo['id'];
                            break;
                        }
                    }
                }

                $cgpa        = isset($edu['cgpa']) ? (float)$edu['cgpa'] : null;
                $scale       = isset($edu['scale']) ? (float)$edu['scale'] : null;
                $marks       = isset($edu['marks']) ? (float)$edu['marks'] : null;
                $passingYear = isset($edu['passing_year']) ? (int)$edu['passing_year'] : null;

                if ($dryRun) {
                    $this->line(sprintf(
                        "\n  [DRY RUN] %-15s → Degree: %-30s | Institution: %-30s | Result: %s (CGPA: %s/%s) | Year: %s",
                        $employeeId,
                        mb_substr($degreeName, 0, 30),
                        mb_substr($institution, 0, 30),
                        $edu['result_type'] ?? '—',
                        $cgpa ?? '—',
                        $scale ?? '—',
                        $passingYear ?? '—'
                    ));
                    $imported++;
                    continue;
                }

                // Resolve Educational Institution ID
                $educationalInstitutionId = null;
                $institutionName = trim($institution);
                if ($institutionName !== '') {
                    $educationalInstitutionId = \App\Models\EducationalInstitution::firstOrCreate(
                        ['name' => $institutionName],
                        ['is_active' => true]
                    )->id;
                }

                // Resolve Major ID
                $majorId = null;
                $majorName = trim($edu['major'] ?? '');
                if ($majorName !== '') {
                    $majorId = \App\Models\Major::firstOrCreate(
                        ['name' => $majorName],
                        ['is_active' => true]
                    )->id;
                }

                try {
                    if ($this->option('skip-existing')) {
                        $exists = Education::where([
                            'teacher_id'                 => $teacher->id,
                            'degree_type_id'             => $degreeTypeId,
                            'educational_institution_id' => $educationalInstitutionId,
                            'major_id'                   => $majorId,
                        ])->exists();
                        if ($exists) {
                            $skipped++;
                            continue;
                        }
                    }

                    $result = Education::updateOrCreate(
                        [
                            'teacher_id'                 => $teacher->id,
                            'degree_type_id'             => $degreeTypeId,
                            'educational_institution_id' => $educationalInstitutionId,
                            'major_id'                   => $majorId,
                        ],
                        [
                            'country_id'     => $countryId,
                            'result_type_id' => $resultTypeId,
                            'cgpa'           => $cgpa,
                            'scale'          => $scale,
                            'marks'          => $marks,
                            'grade'          => $edu['grade'] ?? null,
                            'passing_year'   => $passingYear,
                            'duration'       => $edu['duration'] ?? null,
                            'institution'    => $institution,
                            'major'          => $majorName,
                            'sort_order'     => 0,
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Failed to import education for {$employeeId} degree {$degreeName}: " . $e->getMessage());
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
                ['Educations → NEW (created)',                 $imported],
                ['Educations → UPDATED (existing)',             $updated],
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

    /**
     * Normalize string by converting to lowercase and stripping all non-alphanumeric chars.
     */
    private function normalizeString(string $value): string
    {
        $value = mb_strtolower($value);
        return preg_replace('/[^a-z0-9]/', '', $value);
    }
}
