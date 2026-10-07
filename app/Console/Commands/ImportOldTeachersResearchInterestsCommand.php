<?php

namespace App\Console\Commands;

use App\Models\ResearchInterest;
use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings the research interests parsed from the old site onto teacher profiles.
 *
 * Reads what export:old-teachers-research-interests wrote — one entry per
 * teacher, each with an ordered list of {interest, description, sort_order} —
 * and adds it to research_interests.
 *
 * Adds, never replaces. A teacher may already have interests here, typed in on
 * their own profile, and those are theirs; an interest already present (same
 * words, any case) is left as it is and a new one goes after the teacher's last.
 * That also makes a re-run harmless: everything it would add is already there.
 */
class ImportOldTeachersResearchInterestsCommand extends Command
{
    protected $signature = 'import:old-teachers-research-interests
                            {--file=teachers_research_interests_export.json : JSON file name inside storage/app/public/exports/}
                            {--limit=0 : Limit the number of records to process}
                            {--dry-run : Preview without writing to DB}
                            {--skip-existing : Leave alone any teacher who already has research interests}';

    protected $description = 'Import teacher research interests from the exported JSON (old teacher.currentResearch) into research_interests';

    public function handle(): int
    {
        $file = storage_path('app/public/exports/' . $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            $this->info('Run: php artisan export:old-teachers-research-interests first.');

            return self::FAILURE;
        }

        $records = json_decode(file_get_contents($file), true);

        if (! is_array($records)) {
            // The reason, because the file is meant to be checked and edited by
            // hand, and a hand edit is what usually breaks it — a trailing comma
            // left after deleting an entry reads as just "Syntax error".
            $this->error("Invalid JSON in {$file}: " . json_last_error_msg());
            $this->line('Check for a trailing comma before ] or } — e.g. at https://jsonlint.com or with: python3 -m json.tool <file>');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $records = array_slice($records, 0, $limit);
        }

        $this->info($dryRun
            ? '🔍 Dry run — nothing will be written'
            : '🚀 Importing research interests...');

        $stats = ['matched' => 0, 'added' => 0, 'present' => 0, 'skipped' => 0, 'empty' => 0];
        $notFound = [];

        $bar = $this->output->createProgressBar(count($records));
        $bar->start();

        foreach ($records as $record) {
            $bar->advance();

            $employeeId = trim((string) ($record['_employee_id'] ?? $record['employee_id'] ?? ''));
            $interests = $record['research_interests'] ?? [];

            if ($employeeId === '' || ! is_array($interests) || $interests === []) {
                $stats['empty']++;

                continue;
            }

            $teacher = $this->teacherFor($employeeId);

            if (! $teacher) {
                $notFound[] = $employeeId;

                continue;
            }

            $stats['matched']++;

            if ($this->option('skip-existing') && $teacher->researchInterests()->exists()) {
                $stats['skipped']++;

                continue;
            }

            // A closure with &$stats, not fn(): an arrow function captures by
            // value, and the counts would never leave it.
            $apply = function () use ($teacher, $interests, &$stats, $dryRun) {
                $this->applyInterests($teacher, $interests, $stats, $dryRun);
            };

            $dryRun ? $apply() : DB::transaction($apply);
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'Count'], [
            ['Records in file' . ($limit > 0 ? " (limited to {$limit})" : ''), count($records)],
            ['Records with no interests', $stats['empty']],
            ['Matched to a teacher', $stats['matched']],
            ['Teachers not found', count($notFound)],
            ['Teachers skipped (--skip-existing)', $stats['skipped']],
            [$dryRun ? 'Research interests that would be added' : 'Research interests added', $stats['added']],
            ['Already present, left alone', $stats['present']],
        ]);

        if ($notFound !== []) {
            $this->warn('No teacher with these employee ids: ' . implode(', ', array_slice($notFound, 0, 20))
                . (count($notFound) > 20 ? ' … and ' . (count($notFound) - 20) . ' more' : ''));
        }

        return self::SUCCESS;
    }

    /**
     * The profile an employee id belongs to.
     *
     * The migration brought some people in twice under one employee id. Where
     * that has not been merged yet, the live profile is the one a teacher sees
     * and edits, so it is preferred over an archived copy.
     */
    protected function teacherFor(string $employeeId): ?Teacher
    {
        return Teacher::where('employee_id', $employeeId)
            ->orderBy('is_archived')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $interests  in the order they were written
     * @param  array<string, int>  $stats
     */
    protected function applyInterests(Teacher $teacher, array $interests, array &$stats, bool $dryRun): void
    {
        $existing = $teacher->researchInterests()
            ->pluck('interest')
            ->map(fn ($interest) => mb_strtolower(trim((string) $interest)))
            ->flip();

        $sortOrder = $teacher->researchInterests()->exists()
            ? (int) $teacher->researchInterests()->max('sort_order') + 1
            : 0;

        foreach ($interests as $item) {
            $interest = trim((string) (is_array($item) ? ($item['interest'] ?? '') : $item));

            if ($interest === '') {
                continue;
            }

            if ($existing->has(mb_strtolower($interest))) {
                $stats['present']++;

                continue;
            }

            $description = is_array($item) ? trim((string) ($item['description'] ?? '')) : '';

            if (! $dryRun) {
                ResearchInterest::create([
                    'teacher_id' => $teacher->id,
                    'interest' => $interest,
                    'description' => $description !== '' ? $description : null,
                    'sort_order' => $sortOrder,
                ]);
            }

            $sortOrder++;
            $existing->put(mb_strtolower($interest), true);
            $stats['added']++;
        }
    }
}
