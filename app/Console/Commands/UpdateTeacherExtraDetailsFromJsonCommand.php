<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateTeacherExtraDetailsFromJsonCommand extends Command
{
    protected $signature = 'update:teacher-details-from-json
                            {--file=C:\Users\Milton\Desktop\all profile.json : Absolute or relative path to the JSON file}
                            {--limit=0                                      : Limit the number of records to process}
                            {--dry-run                                      : Preview without writing changes to DB}';

    protected $description = 'Update joining_date, work_location, personal_phone, office_room, and secondary_email in teachers table from profile JSON file';

    public function handle(): int
    {
        $filePath = $this->option('file');
        $dryRun   = (bool) $this->option('dry-run');
        $limit    = (int) $this->option('limit');

        // Resolve Windows path format when running inside WSL environment
        if (!file_exists($filePath)) {
            if (preg_match('/^([a-zA-Z]):[\\\\\/](.+)$/', $filePath, $matches)) {
                $drive = strtolower($matches[1]);
                $path  = str_replace('\\', '/', $matches[2]);
                $wslPath = "/mnt/{$drive}/{$path}";
                if (file_exists($wslPath)) {
                    $filePath = $wslPath;
                }
            }
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($filePath), true);
        if (!is_array($data)) {
            $this->error("Invalid JSON content in {$filePath}");
            return Command::FAILURE;
        }

        $totalRecords = count($data);
        $processCount = ($limit > 0 && $limit < $totalRecords) ? $limit : $totalRecords;

        $this->info($dryRun
            ? "🔍 DRY RUN — no changes will be written to database"
            : "🚀 Updating teacher profile extra details..."
        );
        $this->info("Total JSON records: {$totalRecords} | Processing: {$processCount}");
        $this->newLine();

        $bar = $this->output->createProgressBar($processCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $matchedTeachers       = 0;
        $joiningDateUpdated    = 0;
        $workLocationUpdated   = 0;
        $personalPhoneUpdated  = 0;
        $officeRoomUpdated     = 0;
        $secondaryEmailUpdated = 0;
        $teachersNotFound      = 0;
        $skippedNoEmployeeId   = 0;
        $failed                = 0;
        $count                 = 0;

        foreach ($data as $record) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }
            $count++;

            $employeeId = trim($record['employee_id'] ?? '');

            if ($employeeId === '') {
                $skippedNoEmployeeId++;
                $bar->advance();
                continue;
            }

            $bar->setMessage("Processing: {$employeeId}");

            $teacher = Teacher::where('employee_id', $employeeId)->first();
            if (!$teacher) {
                $teachersNotFound++;
                $bar->advance();
                continue;
            }

            $matchedTeachers++;

            try {
                $updates = [];

                // 1. joining_date
                $rawJoiningDate = trim($record['joining_date'] ?? '');
                if ($rawJoiningDate !== '' && $rawJoiningDate !== '-' && $rawJoiningDate !== '0000-00-00') {
                    try {
                        $parsedDate = Carbon::parse($rawJoiningDate)->toDateString();
                        if ($teacher->joining_date !== $parsedDate) {
                            $updates['joining_date'] = $parsedDate;
                            $joiningDateUpdated++;
                        }
                    } catch (\Throwable $e) {
                        // Ignore unparseable date
                    }
                }

                // 2. work_location
                $rawWorkLocation = trim($record['work_location'] ?? '');
                if ($rawWorkLocation !== '' && $rawWorkLocation !== '-') {
                    if ($teacher->work_location !== $rawWorkLocation) {
                        $updates['work_location'] = $rawWorkLocation;
                        $workLocationUpdated++;
                    }
                }

                // 3. personal_phone
                $rawPersonalPhone = trim($record['personal_phone'] ?? '');
                if ($rawPersonalPhone !== '' && $rawPersonalPhone !== '-') {
                    if ($teacher->personal_phone !== $rawPersonalPhone) {
                        $updates['personal_phone'] = $rawPersonalPhone;
                        $personalPhoneUpdated++;
                    }
                }

                // 4. office_room
                $rawOfficeRoom = trim($record['office_room'] ?? '');
                if ($rawOfficeRoom !== '' && $rawOfficeRoom !== '-') {
                    if ($teacher->office_room !== $rawOfficeRoom) {
                        $updates['office_room'] = $rawOfficeRoom;
                        $officeRoomUpdated++;
                    }
                }

                // 5. email -> secondary_email logic
                $rawEmail = trim($record['email'] ?? '');
                if ($rawEmail !== '' && $rawEmail !== '-' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                    $primaryEmail = trim($teacher->user?->email ?? '');

                    // If incoming email is DIFFERENT from primary email
                    if (strcasecmp($rawEmail, $primaryEmail) !== 0) {
                        if ($teacher->secondary_email !== $rawEmail) {
                            $updates['secondary_email'] = $rawEmail;
                            $secondaryEmailUpdated++;
                        }
                    }
                }

                if (!empty($updates)) {
                    if (!$dryRun) {
                        $teacher->update($updates);
                    }
                }

            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed to update teacher {$employeeId}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total JSON records processed',       $count],
                ['Skipped (empty employee_id)',         $skippedNoEmployeeId],
                ['Teachers not found in DB',            $teachersNotFound],
                ['Matched teachers in DB',              $matchedTeachers],
                ['joining_date field updates',          $joiningDateUpdated],
                ['work_location field updates',         $workLocationUpdated],
                ['personal_phone field updates',        $personalPhoneUpdated],
                ['office_room field updates',           $officeRoomUpdated],
                ['secondary_email field updates',       $secondaryEmailUpdated],
                ['Failed updates',                      $failed],
            ]
        );

        if (!$dryRun) {
            $this->info("✅ Teacher extra details update complete.");
        }

        return Command::SUCCESS;
    }
}
