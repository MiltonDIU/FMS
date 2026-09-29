<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Support\TeacherServicePeriods;
use Illuminate\Console\Command;

/**
 * Put every teacher's service periods in step with their joining date,
 * leaving date and employment status. For anything written around the usual
 * paths — an import, a bulk update — and safe to run again: a teacher already
 * in step is left exactly as they are.
 */
class SyncTeacherServicePeriodsCommand extends Command
{
    protected $signature = 'teachers:sync-service-periods';

    protected $description = 'Bring every teacher\'s service periods in step with their joining date, leaving date and status (safe to re-run)';

    public function handle(): int
    {
        $checked = 0;

        Teacher::query()
            ->select(['id', 'joining_date', 'leaving_date', 'employment_status_id'])
            ->chunkById(500, function ($teachers) use (&$checked) {
                foreach ($teachers as $teacher) {
                    TeacherServicePeriods::sync($teacher, 'reconcile');
                    $checked++;
                }
            });

        $this->info("Checked {$checked} teachers.");

        return self::SUCCESS;
    }
}
