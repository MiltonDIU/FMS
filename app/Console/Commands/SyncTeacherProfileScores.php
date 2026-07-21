<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Services\ProfileGapEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncTeacherProfileScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --chunk=100   : How many teachers to process per batch (default 100)
     * --teacher=ID  : Sync only a specific teacher by ID
     * --force       : Re-sync all teachers, even recently synced ones
     */
    protected $signature = 'teachers:sync-profile-scores
                            {--chunk=100 : Number of teachers to process per batch}
                            {--teacher= : Sync a specific teacher ID only}
                            {--force    : Re-sync even recently synced teachers}';

    protected $description = 'Calculate and cache profile completion scores for all teachers';

    public function handle(): int
    {
        $this->info('🔄 Starting Teacher Profile Score Sync...');

        $chunkSize  = (int) $this->option('chunk');
        $teacherId  = $this->option('teacher');
        $force      = (bool) $this->option('force');

        $evaluator = new ProfileGapEvaluator();

        // ── Single teacher mode ──────────────────────────────────────────────
        if ($teacherId) {
            $teacher = Teacher::with($this->requiredRelations())->find($teacherId);

            if (! $teacher) {
                $this->error("Teacher ID {$teacherId} not found.");
                return self::FAILURE;
            }

            $score = $evaluator->evaluate($teacher)['completion_percentage'];
            $teacher->updateQuietly([
                'profile_score'           => $score,
                'profile_score_synced_at' => Carbon::now(),
            ]);

            $this->info("✅ Teacher [{$teacher->full_name}] → Score: {$score}%");
            return self::SUCCESS;
        }

        // ── Batch mode ───────────────────────────────────────────────────────
        // NOTE: No ->select() restriction here — ProfileGapEvaluator reads
        // scalar fields like employee_id, phone, bio, gender_id, research_interest
        // etc. directly from the teacher model. Restricting columns would make
        // them null and produce incorrect (lower) scores.
        $query = Teacher::query()
            ->where('is_archived', false);

        // Unless --force, skip teachers synced within the last 6 hours
        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('profile_score_synced_at')
                  ->orWhere('profile_score_synced_at', '<', Carbon::now()->subHours(6));
            });
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('✅ All teachers are already up-to-date. Use --force to re-sync all.');
            return self::SUCCESS;
        }

        $this->info("📊 Found {$total} teachers to sync (chunk size: {$chunkSize})");

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $bar->start();

        $processed = 0;
        $failed    = 0;

        $query->with($this->requiredRelations())
              ->chunkById($chunkSize, function ($teachers) use ($evaluator, $bar, &$processed, &$failed) {

            $updates = [];

            foreach ($teachers as $teacher) {
                try {
                    $report = $evaluator->evaluate($teacher);
                    $updates[] = [
                        'id'                      => $teacher->id,
                        'profile_score'           => $report['completion_percentage'],
                        'profile_score_synced_at' => Carbon::now()->toDateTimeString(),
                    ];
                    $bar->setMessage("Syncing: {$teacher->full_name}");
                    $processed++;
                } catch (\Throwable $e) {
                    \Log::warning("ProfileScore sync failed for teacher #{$teacher->id}: " . $e->getMessage());
                    $failed++;
                }
                $bar->advance();
            }

            // Bulk UPDATE — only update the two score columns, never INSERT
            if (! empty($updates)) {
                $ids = array_column($updates, 'id');
                $scoreCase  = 'CASE id ';
                $syncedCase = 'CASE id ';

                foreach ($updates as $u) {
                    $score  = (int) $u['profile_score'];
                    $synced = $u['profile_score_synced_at'];
                    $scoreCase  .= "WHEN {$u['id']} THEN {$score} ";
                    $syncedCase .= "WHEN {$u['id']} THEN '{$synced}' ";
                }

                $scoreCase  .= 'END';
                $syncedCase .= 'END';
                $idList = implode(',', $ids);

                DB::statement("
                    UPDATE teachers
                    SET profile_score = {$scoreCase},
                        profile_score_synced_at = {$syncedCase}
                    WHERE id IN ({$idList})
                ");
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Synced',  $processed],
                ['❌ Failed',  $failed],
                ['📊 Total',   $processed + $failed],
            ]
        );

        if ($failed > 0) {
            $this->warn("⚠️  {$failed} teachers failed to sync. Check logs for details.");
        }

        $this->info('🎉 Profile score sync completed!');

        return self::SUCCESS;
    }

    /**
     * Eager-load relations required by ProfileGapEvaluator.
     * These match exactly what evaluate() uses to avoid N+1 queries.
     */
    private function requiredRelations(): array
    {
        return [
            'educations.degreeType.level',
            'educations.educationalInstitution',
            'publications',
            'jobExperiences',
            'trainingExperiences',
            'awards',
            'skills',
            'teachingAreas',
            'memberships',
            'socialLinks',
        ];
    }
}
