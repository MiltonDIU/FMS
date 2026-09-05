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
                ->modalDescription('Narrow the recipients by faculty, department or employment status, then write the email. The count updates as you choose.')
                /*
                 * Every option list and the count below them are closures, so
                 * they are read when the modal opens rather than when the page
                 * loads. The teachers list is the busiest screen in the panel
                 * and this is four aggregate queries; nobody who is not sending
                 * an email should pay for them.
                 */
                ->form([
                    \Filament\Forms\Components\Select::make('faculty_ids')
                        ->label('Target Faculties (Multi-Select)')
                        ->placeholder('All faculties')
                        ->options(fn (\Filament\Schemas\Components\Utilities\Get $get): array => static::emailFilterOptions('faculty', $get))
                        ->multiple()
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Leave empty for every faculty. Each number is how many of the current recipients that option would leave you with.'),

                    \Filament\Forms\Components\Select::make('department_ids')
                        ->label('Target Departments (Multi-Select)')
                        ->placeholder('All departments in the chosen faculties')
                        ->options(fn (\Filament\Schemas\Components\Utilities\Get $get): array => static::emailFilterOptions('department', $get))
                        ->multiple()
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Choosing a faculty above narrows this list. A department chosen here decides the recipients on its own — the faculty is then only a way of finding it. Somebody assigned to several of the departments you pick still gets one email.'),

                    \Filament\Forms\Components\Select::make('employment_status_ids')
                        ->label('Target Employment Statuses (Multi-Select)')
                        ->placeholder('All Employment Statuses (Full-time, Part-time, Suspended, etc.)')
                        ->options(fn (\Filament\Schemas\Components\Utilities\Get $get): array => static::emailFilterOptions('employment_status', $get))
                        ->multiple()
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Leave empty for every status. Combined with the others, not instead of them.'),

                    \Filament\Forms\Components\Select::make('job_type_ids')
                        ->label('Target Job Types (Multi-Select)')
                        ->placeholder('All job types (Regular, Adjunct, Visiting, Contractual, etc.)')
                        ->options(fn (\Filament\Schemas\Components\Utilities\Get $get): array => static::emailFilterOptions('job_type', $get))
                        ->multiple()
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Leave empty for every type. Use it to leave out the ones a message is not meant for — visiting and adjunct staff, for instance, when the email is about internal process.'),

                    /*
                     * How many people this actually reaches, before it is sent.
                     *
                     * The action used to say nothing until afterwards, so the
                     * only way to find out whether a filter meant twelve people
                     * or twelve hundred was to send to them.
                     */
                    \Filament\Forms\Components\Placeholder::make('recipient_count')
                        ->label('This email will go to')
                        ->columnSpanFull()
                        ->content(function (\Filament\Schemas\Components\Utilities\Get $get): \Illuminate\Support\HtmlString {
                            return new \Illuminate\Support\HtmlString(static::recipientReport(
                                $get('faculty_ids') ?? [],
                                $get('department_ids') ?? [],
                                $get('employment_status_ids') ?? [],
                                $get('job_type_ids') ?? [],
                            ));
                        }),

                    ...\App\Filament\Resources\Teachers\Support\TeacherEmailComposer::schema(),
                ])
                ->action(function (array $data) {
                    $teachers = static::targetedEmailQuery(
                        $data['faculty_ids'] ?? [],
                        $data['department_ids'] ?? [],
                        $data['employment_status_ids'] ?? [],
                        $data['job_type_ids'] ?? [],
                    )->with('user')->get();

                    if ($teachers->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('No matching teachers found')
                            ->body('Nothing matched that combination of faculty, department and employment status.')
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
    /**
     * The teachers a targeted email would reach.
     *
     * Shared by the count in the modal and by the send itself, so the number
     * somebody reads before pressing the button is the number that gets an
     * email.
     *
     * A teacher belongs to a department twice over: department_id is the one
     * they sit in, and department_teacher holds the others they are attached
     * to. Both count. Somebody teaching in a department should hear from it
     * whether or not it is the department their record calls home — and it is
     * the second kind that the old migration kept losing.
     *
     * One person, one email, however many of the selected departments they
     * belong to. This is a message about their profile, and a profile shows up
     * under every department it is assigned to — 39 teachers are attached to
     * more than one, and Professor Rafiqul Islam is in both Business
     * Administration and Development Studies. Selecting both must reach him
     * once.
     *
     * That is why the pivot is reached through whereHas and never a join. A
     * join returns one row per matching assignment and would send him two.
     * whereHas compiles to EXISTS, which asks whether any assignment matches
     * and returns the teacher once either way. Selecting all 32 departments
     * returns 1,189 — exactly the number of non-archived teachers, not one
     * more. Keep it that way.
     *
     * Department beats faculty when both are given. Picking a faculty and then
     * a department inside it should mean that department, not everyone in the
     * faculty; the faculty is how you found it.
     *
     * Job type is read from the teacher's own record and not from their
     * department assignments, which is the one place this parts company with
     * the rule above. It is there to leave people out — visiting and adjunct
     * staff, when a message is about internal process — and a filter meant for
     * excluding has to be exact. Matching any assignment would pull in somebody
     * employed on other terms who happens to hold one adjunct posting.
     *
     * @param  array<int|string>  $facultyIds  Empty means every faculty.
     * @param  array<int|string>  $departmentIds  Empty means every department.
     * @param  array<int|string>  $employmentStatusIds  Empty means every status.
     * @param  array<int|string>  $jobTypeIds  Empty means every job type.
     */
    protected static function targetedEmailQuery(array $facultyIds, array $departmentIds, array $employmentStatusIds, array $jobTypeIds = []): Builder
    {
        $query = Teacher::query()->where('is_archived', false);

        if (! empty($departmentIds)) {
            $query->where(fn (Builder $q): Builder => $q
                ->whereIn('department_id', $departmentIds)
                ->orWhereHas('departments', fn ($d) => $d->whereIn('departments.id', $departmentIds)));
        } elseif (! empty($facultyIds)) {
            $query->where(fn (Builder $q): Builder => $q
                ->whereHas('department', fn ($d) => $d->whereIn('faculty_id', $facultyIds))
                ->orWhereHas('departments', fn ($d) => $d->whereIn('departments.faculty_id', $facultyIds)));
        }

        if (! empty($employmentStatusIds)) {
            $query->whereIn('employment_status_id', $employmentStatusIds);
        }

        if (! empty($jobTypeIds)) {
            $query->whereIn('job_type_id', $jobTypeIds);
        }

        return $query;
    }

    /**
     * The count, and what is standing between it and the filters.
     *
     * @param  array<int|string>  $facultyIds
     * @param  array<int|string>  $departmentIds
     * @param  array<int|string>  $employmentStatusIds
     * @param  array<int|string>  $jobTypeIds
     */
    protected static function recipientReport(array $facultyIds, array $departmentIds, array $employmentStatusIds, array $jobTypeIds = []): string
    {
        $query = static::targetedEmailQuery($facultyIds, $departmentIds, $employmentStatusIds, $jobTypeIds);

        $total = (clone $query)->count();
        $reachable = (clone $query)->whereHas('user', fn ($u) => $u->whereNotNull('email')->where('email', '!=', ''))->count();

        $lines = ['<strong>' . number_format($total) . ' teacher(s)</strong> — one email each, counted once however many of the chosen departments they belong to.'];

        if ($total === 0) {
            $lines[] = 'Nothing matches that combination — widen one of the filters above.';

            return implode('<br>', $lines);
        }

        if ($reachable < $total) {
            $lines[] = number_format($total - $reachable) . ' of them have no email address on file and will be skipped.';
        }

        $applied = array_filter([
            $facultyIds ? count($facultyIds) . ' faculty(ies)' : null,
            $departmentIds ? count($departmentIds) . ' department(s)' : null,
            $employmentStatusIds ? count($employmentStatusIds) . ' employment status(es)' : null,
            $jobTypeIds ? count($jobTypeIds) . ' job type(s)' : null,
        ]);

        $lines[] = $applied === []
            ? 'No filter applied — this is every non-archived teacher.'
            : 'Filtered by ' . implode(', ', $applied) . '.';

        if ($departmentIds && $facultyIds) {
            $lines[] = 'The department choice decides the recipients; the faculty above only narrowed the list.';
        }

        $archived = Teacher::query()->where('is_archived', true)->count();

        if ($archived > 0) {
            $lines[] = number_format($archived) . ' archived teacher(s) are never included.';
        }

        return implode('<br>', $lines);
    }

    /**
     * The options for one of the four filters, each carrying the number of
     * people it would actually leave you with.
     *
     * Every count is taken against the other three filters as they stand, and
     * never against its own — so a dropdown always describes what choosing an
     * option would do from here, and the numbers in one list can be compared
     * with the numbers in another.
     *
     * They could not be, before. Employment status counted every teacher in the
     * table, archived included, so it offered "Archived (923)" alongside
     * "Active (935)" on a form that never sends to archived teachers. Job type
     * counted only the non-archived. The two lists were describing different
     * populations, and neither noticed the other: picking Active (935) still
     * showed Regular (976), a bigger number than the set it was supposedly
     * inside.
     *
     * Counts are grouped rather than looped — one query per dimension instead
     * of one per option — because this runs again on every keystroke that
     * changes a filter.
     *
     * @param  'faculty'|'department'|'employment_status'|'job_type'  $dimension
     */
    protected static function emailFilterOptions(string $dimension, \Filament\Schemas\Components\Utilities\Get $get): array
    {
        $faculties = $get('faculty_ids') ?? [];
        $departments = $get('department_ids') ?? [];
        $statuses = $get('employment_status_ids') ?? [];
        $jobTypes = $get('job_type_ids') ?? [];

        /*
         * The other filters only. A dimension counting itself would just echo
         * the current selection back — every unpicked option would read zero,
         * which is the one number that tells you nothing about picking it.
         *
         * Faculty and department drop together: they are two ways of naming the
         * same thing, and department wins over faculty in the query, so leaving
         * faculty in while counting departments would hide every department
         * outside it behind a zero.
         */
        $base = match ($dimension) {
            'faculty', 'department' => static::targetedEmailQuery([], [], $statuses, $jobTypes),
            'employment_status' => static::targetedEmailQuery($faculties, $departments, [], $jobTypes),
            'job_type' => static::targetedEmailQuery($faculties, $departments, $statuses, []),
        };

        if ($dimension === 'employment_status' || $dimension === 'job_type') {
            $column = $dimension === 'job_type' ? 'job_type_id' : 'employment_status_id';

            $counts = (clone $base)
                ->whereNotNull($column)
                ->selectRaw("{$column} as k, COUNT(*) as total")
                ->groupBy($column)
                ->pluck('total', 'k');

            $model = $dimension === 'job_type' ? \App\Models\JobType::class : \App\Models\EmploymentStatus::class;

            return $model::query()
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->mapWithKeys(fn ($row): array => [
                    $row->id => $row->name . ' (' . number_format((int) ($counts[$row->id] ?? 0)) . ')',
                ])
                ->all();
        }

        return static::placementOptions($dimension, $base, $faculties);
    }

    /**
     * Faculty and department counts, which have to be gathered rather than
     * grouped.
     *
     * A teacher reaches a department two ways — the one their record sits in
     * and every one they are assigned to — and the same person can arrive by
     * both. Summing two grouped queries would count them twice, so the teacher
     * ids are collected per department and the set is measured. Faculty is the
     * same sets, rolled up.
     *
     * @param  array<int|string>  $facultyIds
     */
    protected static function placementOptions(string $dimension, Builder $base, array $facultyIds): array
    {
        $teacherIds = (clone $base)->pluck('id');

        $byDepartment = [];

        foreach (Teacher::query()->whereIn('id', $teacherIds)->whereNotNull('department_id')->get(['id', 'department_id']) as $t) {
            $byDepartment[$t->department_id][$t->id] = true;
        }

        foreach (
            \Illuminate\Support\Facades\DB::table('department_teacher')
                ->whereIn('teacher_id', $teacherIds)
                ->whereNull('deleted_at')
                ->get(['teacher_id', 'department_id']) as $row
        ) {
            $byDepartment[$row->department_id][$row->teacher_id] = true;
        }

        if ($dimension === 'department') {
            return \App\Models\Department::query()
                ->when(! empty($facultyIds), fn ($q) => $q->whereIn('faculty_id', $facultyIds))
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->mapWithKeys(fn ($d): array => [
                    $d->id => $d->name . ' (' . number_format(count($byDepartment[$d->id] ?? [])) . ')',
                ])
                ->all();
        }

        $facultyOf = \App\Models\Department::query()->pluck('faculty_id', 'id');
        $byFaculty = [];

        foreach ($byDepartment as $departmentId => $ids) {
            $facultyId = $facultyOf[$departmentId] ?? null;

            if ($facultyId === null) {
                continue;
            }

            foreach ($ids as $id => $_) {
                $byFaculty[$facultyId][$id] = true;
            }
        }

        return \App\Models\Faculty::query()
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($f): array => [
                $f->id => $f->name . ' (' . number_format(count($byFaculty[$f->id] ?? [])) . ')',
            ])
            ->all();
    }

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
