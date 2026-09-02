<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Teachers\Widgets\TeacherVerificationStatsWidget;
use App\Jobs\SyncErpTeacherProfilesJob;
use App\Models\Teacher;
use App\Services\ErpProfileFieldSync;
use App\Support\ErpProfileFields;
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

                    \Filament\Forms\Components\Toggle::make('include_archived')
                        ->label('Include archived teachers')
                        ->helperText('Off by default. The Archived and Retired statuses are made entirely of archived teachers, so leave this on when running for either of them.')
                        ->default(false)
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
                            $includeArchived = (bool) $get('include_archived');

                            $total = static::batchScoreQuery($selected, (bool) $get('force_resync'), $includeArchived)->count();

                            $lines = [number_format($total) . ' teacher(s)'];

                            if (empty($selected)) {
                                $unassigned = static::batchScoreQuery([], true, $includeArchived)
                                    ->whereNull('employment_status_id')
                                    ->count();

                                if ($unassigned > 0) {
                                    $lines[] = 'includes ' . number_format($unassigned) . ' with no employment status set — selecting any status above leaves them out';
                                }
                            }

                            if (! $get('force_resync')) {
                                $lines[] = 'limited to teachers never scored, or last scored over 6 hours ago';
                            }

                            $lines[] = static::archivedNote($selected, $includeArchived);

                            return new \Illuminate\Support\HtmlString(implode('<br>', array_filter($lines)));
                        }),
                ])
                ->action(function (array $data) {
                    $query = static::batchScoreQuery(
                        $data['employment_status_ids'] ?? [],
                        (bool) ($data['force_resync'] ?? false),
                        (bool) ($data['include_archived'] ?? false),
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

            Action::make('sync_erp_profiles')
                ->label('Fill Fields from ERP')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('bulkSyncErpProfiles', Teacher::class) ?? false)
                ->modalHeading('Fill Teacher Fields from the ERP')
                ->modalDescription('Calls the ERP profile API for each matching teacher and fills only the fields you choose below. The run happens in the background; you will be notified when it finishes.')
                ->modalSubmitActionLabel('Start in background')
                ->form([
                    \Filament\Forms\Components\CheckboxList::make('fields')
                        ->label('Fields to fill')
                        ->options(ErpProfileFields::all())
                        ->descriptions(ErpProfileFields::descriptions())
                        ->default(ErpProfileFields::defaultSelection())
                        ->columns(2)
                        ->bulkToggleable()
                        ->required()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Only these fields can be written. Nothing else on the profile is touched, whatever else the ERP returns.'),

                    \Filament\Forms\Components\Select::make('employment_status_ids')
                        ->label('Target Employment Statuses (Multi-Select)')
                        ->placeholder('All Employment Statuses (Full-time, Part-time, Suspended, etc.)')
                        ->options(fn (): array => static::employmentStatusOptionsWithCounts())
                        ->multiple()
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Leave empty to run for every status. The number beside each is how many non-archived teachers it holds.'),

                    \Filament\Forms\Components\Radio::make('mode')
                        ->label('When we already hold a value')
                        ->options([
                            ErpProfileFieldSync::MODE_FILL_EMPTY => 'Only fill what is empty (leave existing values alone)',
                            ErpProfileFieldSync::MODE_OVERWRITE => 'Overwrite with the ERP value',
                        ])
                        ->default(ErpProfileFieldSync::MODE_FILL_EMPTY)
                        ->required()
                        ->live()
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Toggle::make('include_archived')
                        ->label('Include archived teachers')
                        ->helperText('Off by default — an outbound call for somebody who has left is usually waste. The Archived and Retired statuses are made entirely of archived teachers, so switch this on when running for either of them.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    /*
                     * One outbound call per teacher, so the size of the run is
                     * the thing worth knowing before starting it rather than
                     * halfway through.
                     */
                    \Filament\Forms\Components\Placeholder::make('erp_run_size')
                        ->label('This run will call the ERP for')
                        ->columnSpanFull()
                        ->content(function (\Filament\Schemas\Components\Utilities\Get $get): \Illuminate\Support\HtmlString {
                            $selected = $get('employment_status_ids') ?? [];
                            $includeArchived = (bool) $get('include_archived');

                            $total = static::erpSyncQuery($selected, $includeArchived)->count();

                            $lines = [number_format($total) . ' teacher(s), one API call each'];

                            $fields = ErpProfileFields::only($get('fields') ?? []);
                            $lines[] = count($fields) . ' field(s) selected: ' . (
                                $fields === [] ? 'none' : implode(', ', ErpProfileFields::labels($fields))
                            );

                            if ($get('mode') === ErpProfileFieldSync::MODE_OVERWRITE) {
                                $lines[] = '<strong>Existing values will be replaced.</strong>';
                            }

                            $lines[] = static::archivedNote($selected, $includeArchived);

                            return new \Illuminate\Support\HtmlString(implode('<br>', array_filter($lines)));
                        }),
                ])
                ->action(function (array $data) {
                    $teacherIds = static::erpSyncQuery(
                        $data['employment_status_ids'] ?? [],
                        (bool) ($data['include_archived'] ?? false),
                    )
                        ->pluck('id')
                        ->all();

                    $fields = ErpProfileFields::only($data['fields'] ?? []);

                    if ($teacherIds === [] || $fields === []) {
                        Notification::make()
                            ->warning()
                            ->title('Nothing to do')
                            ->body($teacherIds === []
                                ? 'No teacher matched the selected employment statuses.'
                                : 'No syncable field was selected.')
                            ->send();

                        return;
                    }

                    SyncErpTeacherProfilesJob::dispatch(
                        $teacherIds,
                        $fields,
                        (string) ($data['mode'] ?? ErpProfileFieldSync::MODE_FILL_EMPTY),
                        auth()->id(),
                    );

                    Notification::make()
                        ->success()
                        ->title('Queued')
                        ->body(number_format(count($teacherIds)) . ' teacher(s) queued for an ERP profile refresh. You will get a notification here when the run finishes.')
                        ->send();
                }),

            CreateAction::make(),
        ];
    }

    /**
     * Says how many teachers the archived switch is holding back, or nothing
     * when it is holding back none.
     *
     * Without this a run aimed at the Archived status reads "0 teacher(s)" with
     * no reason given, which is the failure this whole change came out of — the
     * dropdown said 881 and the run found nobody, and the only thing standing
     * between them was a flag nothing on screen mentioned.
     *
     * @param  array<int, int|string>  $employmentStatusIds
     */
    protected static function archivedNote(array $employmentStatusIds, bool $includeArchived): ?string
    {
        if ($includeArchived) {
            return null;
        }

        $held = Teacher::query()
            ->where('is_archived', true)
            ->when(
                ! empty($employmentStatusIds),
                fn (Builder $query): Builder => $query->whereIn('employment_status_id', $employmentStatusIds),
            )
            ->count();

        if ($held === 0) {
            return null;
        }

        return '<strong>' . number_format($held) . ' archived teacher(s) excluded</strong> — switch on "Include archived teachers" to reach them.';
    }

    /**
     * The teachers an ERP run would call the API for.
     *
     * Shared by the preview and the run itself so the number shown is the
     * number fetched.
     *
     * Archived teachers are left out unless asked for. Spending an outbound call
     * on somebody who has left is usually waste — but only usually, which is why
     * it is a switch on the form and not a rule buried here. The Archived status
     * alone holds 881 teachers, and a run aimed squarely at them would otherwise
     * silently do nothing.
     *
     * @param  array<int, int|string>  $employmentStatusIds  Empty means every status.
     */
    protected static function erpSyncQuery(array $employmentStatusIds, bool $includeArchived = false): Builder
    {
        return Teacher::query()
            ->when(
                ! $includeArchived,
                fn (Builder $query): Builder => $query->where('is_archived', false),
            )
            ->when(
                ! empty($employmentStatusIds),
                fn (Builder $query): Builder => $query->whereIn('employment_status_id', $employmentStatusIds),
            );
    }

    /**
     * Employment statuses labelled with how many teachers each one holds, so the
     * size of a batch is visible while choosing it instead of only afterwards.
     *
     * The count is of every teacher on the status, archived included.
     *
     * It used to carry `is_archived = false`, which made the label lie about the
     * two statuses that matter most for it: Archived holds 881 teachers and
     * Retired one, and every one of them is archived by definition — the
     * observer sets the flag when either status is applied. So the filter
     * removed exactly the rows it was counting, and both statuses read (0) while
     * a third of the faculty sat in them. The list page itself does not hide
     * archived teachers either, so the filter was out of step with the screen it
     * belongs to.
     *
     * How many of these a run actually touches is a separate question, answered
     * by the archived toggle on each form and shown in its preview.
     *
     * Inactive statuses stay listed: a status can be switched off while teachers
     * are still parked on it, and those teachers still need reaching.
     *
     * @return array<int, string>
     */
    protected static function employmentStatusOptionsWithCounts(): array
    {
        $counts = Teacher::query()
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
     * Archived teachers are out unless asked for — scoring the profile of
     * somebody who has left is rarely the point, but the Archived and Retired
     * statuses are made of archived teachers, so excluding them by rule would
     * make those two statuses impossible to pick.
     *
     * @param  array<int|string>  $employmentStatusIds  Empty means every status.
     */
    protected static function batchScoreQuery(array $employmentStatusIds, bool $forceResync, bool $includeArchived = false): Builder
    {
        $query = Teacher::query()->when(
            ! $includeArchived,
            fn (Builder $q): Builder => $q->where('is_archived', false),
        );

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
