<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Teachers\Widgets\TeacherVerificationStatsWidget;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TeacherVerificationStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_batch_profile_scores')
                ->label('Batch Calculate Profile Scores')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->visible(fn (): bool => auth()->user()?->can('batchCalculateProfileScores', Teacher::class) ?? false)
                ->modalHeading('Batch Recalculate Teacher Profile Scores')
                ->modalDescription('Calculate and save updated profile completion scores for selected teachers based on current criteria.')
                ->form([
                    \Filament\Forms\Components\Select::make('employment_status_ids')
                        ->label('Target Employment Statuses (Multi-Select)')
                        ->placeholder('All Employment Statuses (Full-time, Part-time, Suspended, etc.)')
                        ->options(fn (): array => static::employmentStatusOptionsWithCounts())
                        ->multiple()
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Leave empty to score every status. The number beside each status is how many non-archived teachers currently hold it.'),

                    \Filament\Forms\Components\Toggle::make('force_resync')
                        ->label('Force Re-sync All')
                        ->helperText('If enabled, recalculates scores for all matching teachers even if synced recently.')
                        ->default(true)
                        ->live(),

                    /*
                     * Scoring walks every relationship of every matched teacher,
                     * so the operator should see the size of the run before
                     * starting it rather than after. Built from the same query the
                     * action runs, so the two can never disagree.
                     */
                    \Filament\Forms\Components\Placeholder::make('matching_total')
                        ->label('This run will process')
                        ->columnSpanFull()
                        ->content(function (\Filament\Schemas\Components\Utilities\Get $get): \Illuminate\Support\HtmlString {
                            $selected = $get('employment_status_ids') ?? [];

                            $total = static::batchScoreQuery($selected, (bool) $get('force_resync'))->count();

                            $lines = [number_format($total) . ' teacher(s)'];

                            if (empty($selected)) {
                                $unassigned = Teacher::query()
                                    ->where('is_archived', false)
                                    ->whereNull('employment_status_id')
                                    ->count();

                                if ($unassigned > 0) {
                                    $lines[] = 'includes ' . number_format($unassigned) . ' with no employment status set — selecting any status above leaves them out';
                                }
                            }

                            if (! $get('force_resync')) {
                                $lines[] = 'limited to teachers never scored, or last scored over 6 hours ago';
                            }

                            return new \Illuminate\Support\HtmlString(implode('<br>', $lines));
                        }),
                ])
                ->action(function (array $data) {
                    $query = static::batchScoreQuery(
                        $data['employment_status_ids'] ?? [],
                        (bool) ($data['force_resync'] ?? false),
                    );

                    $total = $query->count();

                    if ($total === 0) {
                        Notification::make()
                            ->warning()
                            ->title('No matching teachers found')
                            ->body('No teachers matched the selected criteria for scoring.')
                            ->send();
                        return;
                    }

                    @set_time_limit(300);

                    $evaluator = new \App\Services\ProfileGapEvaluator();
                    $processed = 0;

                    $query->with([
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
                    ])->chunkById(100, function ($teachers) use ($evaluator, &$processed) {
                        $updates = [];
                        $now = \Illuminate\Support\Carbon::now()->toDateTimeString();

                        foreach ($teachers as $teacher) {
                            $report = $evaluator->evaluate($teacher);
                            $score  = $report['completion_percentage'];

                            $updates[] = [
                                'id'                      => $teacher->id,
                                'profile_score'           => $score,
                                'profile_score_synced_at' => $now,
                            ];
                            $processed++;
                        }

                        if (!empty($updates)) {
                            $ids = array_column($updates, 'id');
                            $scoreCase  = 'CASE id ';
                            $syncedCase = 'CASE id ';

                            foreach ($updates as $u) {
                                $scoreCase  .= "WHEN {$u['id']} THEN {$u['profile_score']} ";
                                $syncedCase .= "WHEN {$u['id']} THEN '{$u['profile_score_synced_at']}' ";
                            }

                            $scoreCase  .= 'END';
                            $syncedCase .= 'END';
                            $idList = implode(',', $ids);

                            \Illuminate\Support\Facades\DB::statement("
                                UPDATE teachers
                                SET profile_score = {$scoreCase},
                                    profile_score_synced_at = {$syncedCase}
                                WHERE id IN ({$idList})
                            ");
                        }
                    });

                    Notification::make()
                        ->success()
                        ->title('Profile Scoring Completed!')
                        ->body("Successfully recalculated and updated profile scores for {$processed} teachers.")
                        ->send();
                }),

            Action::make('send_targeted_email')
                ->label('Send Email to Teachers')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->can('bulkSendEmailToTeachers', Teacher::class) ?? false)
                ->modalHeading('Send Targeted Email to Teachers')
                ->modalDescription('Filter teachers by employment status, select a saved email template or write custom content, and send.')
                ->form([
                    \Filament\Forms\Components\Select::make('employment_status_ids')
                        ->label('Target Employment Statuses (Multi-Select)')
                        ->placeholder('All Employment Statuses (Full-time, Part-time, Suspended, etc.)')
                        ->options(fn () => \App\Models\EmploymentStatus::query()->pluck('name', 'id')->toArray())
                        ->multiple()
                        ->searchable()
                        ->columnSpanFull()
                        ->helperText('Leave empty to send email to all active teachers.'),

                    ...\App\Filament\Resources\Teachers\Support\TeacherEmailComposer::schema(),
                ])
                ->action(function (array $data) {
                    $query = Teacher::query()->where('is_archived', false);

                    if (! empty($data['employment_status_ids']) && is_array($data['employment_status_ids'])) {
                        $query->whereIn('employment_status_id', $data['employment_status_ids']);
                    }

                    $teachers = $query->with('user')->get();

                    if ($teachers->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('No matching teachers found')
                            ->body('No teachers matched the selected employment statuses.')
                            ->send();

                        return;
                    }

                    \App\Filament\Resources\Teachers\Support\TeacherEmailComposer::send($teachers, $data);
                }),

            CreateAction::make(),
        ];
    }

    /**
     * Employment statuses labelled with how many teachers each one holds, so the
     * size of a batch is visible while choosing it instead of only afterwards.
     *
     * The counts carry the same is_archived filter the batch itself uses —
     * counting archived teachers here would promise more than the run touches.
     * Inactive statuses stay listed: a status can be retired while teachers are
     * still parked on it, and those teachers still need scoring.
     *
     * @return array<int, string>
     */
    protected static function employmentStatusOptionsWithCounts(): array
    {
        $counts = Teacher::query()
            ->where('is_archived', false)
            ->whereNotNull('employment_status_id')
            ->selectRaw('employment_status_id, COUNT(*) as total')
            ->groupBy('employment_status_id')
            ->pluck('total', 'employment_status_id');

        return \App\Models\EmploymentStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($status): array => [
                $status->id => $status->name . ' (' . number_format((int) ($counts[$status->id] ?? 0)) . ')',
            ])
            ->all();
    }

    /**
     * The teachers a batch scoring run would touch. Shared by the preview
     * placeholder and the run itself, so the number shown is the number scored.
     *
     * @param  array<int|string>  $employmentStatusIds  Empty means every status.
     */
    protected static function batchScoreQuery(array $employmentStatusIds, bool $forceResync): Builder
    {
        $query = Teacher::query()->where('is_archived', false);

        if (! empty($employmentStatusIds)) {
            $query->whereIn('employment_status_id', $employmentStatusIds);
        }

        if (! $forceResync) {
            $query->where(function ($q) {
                $q->whereNull('profile_score_synced_at')
                  ->orWhere('profile_score_synced_at', '<', \Illuminate\Support\Carbon::now()->subHours(6));
            });
        }

        return $query;
    }
}
