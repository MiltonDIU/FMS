<?php

namespace App\Filament\Resources\Teachers\Tables;

use App\Models\Teacher;
use App\Services\ProfileGapEvaluator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Forms\Components\Select;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->selectCurrentPageOnly()
            ->defaultSort(function (Builder $query, string $direction) {
                return $query
                    ->orderBy(
                        DB::table('designations')
                            ->whereColumn('designations.id', 'teachers.designation_id')
                            ->select('designations.sort_order')
                            ->limit(1),
                        $direction
                    )
                    ->orderBy('teachers.sort_order', $direction);
            }, 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'department',
                'departments',
                'designation',
                'employmentStatus',
                'jobType',
                'user.roles',
                'user.administrativeRoles',
            ]))
            ->columns([
                TextColumn::make('employee_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('webpage')
                    ->label('Profile Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('admin_roles')
                    ->label('Admin Roles')
                    ->badge()
                    ->color('warning')
                    ->placeholder('None')
                    ->state(function ($record) {
                        $user = $record->user;
                        if (!$user) return null;

                        $loggedInUser = auth()->user();
                        if ($loggedInUser) {
                            $loggedInAdminRole = $loggedInUser->administrativeRoles()
                                ->wherePivot('is_active', true)
                                ->whereNull('administrative_role_user.end_date')
                                ->first();

                            if ($loggedInAdminRole && $loggedInAdminRole->pivot) {
                                if ($loggedInAdminRole->pivot->department_id) {
                                    $scopedDeptId = $loggedInAdminRole->pivot->department_id;
                                    $roles = $user->administrativeRoles()
                                        ->wherePivot('is_active', true)
                                        ->wherePivot('department_id', $scopedDeptId)
                                        ->whereNull('administrative_role_user.end_date')
                                        ->get();

                                    return $roles->pluck('name')->toArray();
                                } elseif ($loggedInAdminRole->pivot->faculty_id) {
                                    $scopedFacId = $loggedInAdminRole->pivot->faculty_id;
                                    $scopedDeptIds = \App\Models\Department::where('faculty_id', $scopedFacId)->pluck('id')->toArray();

                                    $roles = $user->administrativeRoles()
                                        ->wherePivot('is_active', true)
                                        ->whereNull('administrative_role_user.end_date')
                                        ->where(function ($q) use ($scopedFacId, $scopedDeptIds) {
                                            $q->where('administrative_role_user.faculty_id', $scopedFacId)
                                              ->orWhereIn('administrative_role_user.department_id', $scopedDeptIds);
                                        })
                                        ->get();

                                    return $roles->pluck('name')->toArray();
                                }
                            }
                        }

                        $allRoles = $user->administrativeRoles()
                            ->wherePivot('is_active', true)
                            ->whereNull('administrative_role_user.end_date')
                            ->get();

                        if ($allRoles->isEmpty()) {
                            return null;
                        }

                        return $allRoles->map(function ($ar) {
                            $scopeStr = '';
                            if ($ar->pivot->department_id) {
                                $deptName = \App\Models\Department::find($ar->pivot->department_id)?->name;
                                $scopeStr = $deptName ? " ({$deptName})" : '';
                            } elseif ($ar->pivot->faculty_id) {
                                $facName = \App\Models\Faculty::find($ar->pivot->faculty_id)?->name;
                                $scopeStr = $facName ? " ({$facName})" : '';
                            }
                            return $ar->name . $scopeStr;
                        })->toArray();
                    })
                    ->toggleable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('departments_count')
                    ->counts('departments')
                    ->label('Assign Dept.')
                    ->badge()
                    ->color('success'),
                TextColumn::make('publications_count')
                    ->counts('publications')
                    ->label('Total Publications')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                /*
                 * Awards and certifications are counted here so the System
                 * Overview dashboard's top performer cards have a column to sort
                 * by when they hand the reader over to this list.
                 *
                 * Not toggled off by default, deliberately. Filament only adds a
                 * counts() sub-select for a column that is on screen, while the
                 * sort clause is added either way — so hiding these produced
                 * "Unknown column 'awards_count' in order clause" the moment the
                 * dashboard link was followed.
                 */
                TextColumn::make('awards_count')
                    ->counts('awards')
                    ->label('Total Awards')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('certifications_count')
                    ->counts('certifications')
                    ->label('Total Certifications')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('departments.short_name')
                    ->label('Department List')
                    ->badge()
                    ->separator(', ')
                    ->limitList(6)
                    ->expandableLimitedList()
                    ->wrap(),
                TextColumn::make('designation.name')
                    ->label('Designation')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('employmentStatus.name')
                    ->badge()
                    ->color(fn ($record) => $record->employmentStatus?->color ?? 'gray')
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('jobType.name')
                    ->badge()
                    ->color('info')
                    ->label('Job Type')
                    ->sortable(),
                TextColumn::make('profile_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'draft' => 'gray',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('profile_score')
                    ->label('Profile Score')
                    ->sortable()
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        is_null($state)  => 'gray',
                        $state >= 80     => 'success',
                        $state >= 50     => 'info',
                        default          => 'danger',
                    })
                    ->formatStateUsing(fn (?int $state): string => is_null($state) ? '—' : $state . '%')
                    ->tooltip(fn (Teacher $record): string =>
                        $record->profile_score_synced_at
                            ? 'Last synced: ' . $record->profile_score_synced_at->diffForHumans()
                            : 'Not yet synced'
                    )
                    ->placeholder('Not Synced'),
                TextColumn::make('profile_score_synced_at')
                    ->label('Score Synced')
                    ->since()
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verification_status')
                    ->label('Verification')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'verified' => 'success',
                        'pending_verification' => 'warning',
                        'correction_requested' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'verified' => 'Verified',
                        'pending_verification' => 'Pending',
                        'correction_requested' => 'Needs Correction',
                        default => 'Unverified',
                    }),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                \Filament\Tables\Columns\ToggleColumn::make('login_allowed')
                    ->label('Login Allowed')
                    ->disabled(function (Teacher $record) {
                        $user = auth()->user();
                        if ($user && !$user->can('toggleLoginAllowed', $record)) {
                            return true;
                        }
                        $status = $record->employmentStatus;
                        if ($status && !$status->allow_login) {
                            return true;
                        }
                        return false;
                    }),
                /*
                 * In the research directory, and so carried by the API the
                 * research site reads.
                 *
                 * import:researcher-profiles turns this on for everyone it finds
                 * in the Directorate of Research's file — that is where the
                 * biography, the expertise areas and the scholarly links on
                 * those profiles come from. This is the row that lets somebody
                 * the file missed be added, and somebody who does not belong be
                 * taken out, without editing the file and re-importing.
                 */
                \Filament\Tables\Columns\ToggleColumn::make('is_researcher')
                    ->label('Researcher')
                    ->tooltip('Include this profile in the research directory API')
                    ->disabled(fn (Teacher $record): bool => ! auth()->user()?->can('toggleResearcher', $record)),
                TextColumn::make('joining_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('is_researcher')
                    ->label('In research directory')
                    ->placeholder('Everyone')
                    ->trueLabel('Researchers only')
                    ->falseLabel('Not in the directory'),
                SelectFilter::make('major_id')
                    ->label('Major')
                    ->searchable()
                    ->options(fn () => \App\Models\Major::query()->where('is_active', true)->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('educations', function ($q) use ($data) {
                                $q->where('major_id', $data['value']);
                            });
                        }
                    }),
                SelectFilter::make('educational_institution_id')
                    ->hidden()
                    ->options(fn () => \App\Models\Organization::query()->where('is_educational_institution', true)->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('educations', function ($q) use ($data) {
                                $q->where('educational_institution_id', $data['value']);
                            });
                        }
                    }),
                SelectFilter::make('designation_id')
                    ->relationship('designation', 'name')
                    ->label('Designation')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('employment_status_id')
                    ->relationship('employmentStatus', 'name')
                    ->label('Employment Status')
                    ->preload(),
                SelectFilter::make('job_type_id')
                    ->relationship('jobType', 'name')
                    ->label('Job Type')
                    ->preload(),

                /*
                 * Faculty, department, gender and the joining date range are here
                 * so the System Overview dashboard can hand its filters over
                 * intact when the reader follows "View All". Without a matching
                 * filter the list would quietly widen the selection and show more
                 * teachers than the dashboard was counting.
                 */
                SelectFilter::make('faculty_id')
                    ->label('Faculty')
                    ->searchable()
                    ->options(fn () => \App\Models\Faculty::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['value'])) {
                            $query->whereHas('department', function ($q) use ($data) {
                                $q->where('faculty_id', $data['value']);
                            });
                        }
                    }),
                SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Department')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('gender_id')
                    ->relationship('gender', 'name')
                    ->label('Gender')
                    ->preload(),
                Filter::make('joining_date')
                    ->label('Joining Date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('Joined From'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Joined To'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('joining_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('joining_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Joined from ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Joined until ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),

                /*
                 * The dashboard's top performer cards only count teachers who have
                 * at least one record, so following them through to this list has
                 * to be able to say the same thing. Sorting alone would leave the
                 * teachers with none sitting at the far end of the same list.
                 */
                TernaryFilter::make('has_publications')
                    ->label('Has Publications')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('publications'),
                        false: fn (Builder $query): Builder => $query->doesntHave('publications'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_awards')
                    ->label('Has Awards')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('awards'),
                        false: fn (Builder $query): Builder => $query->doesntHave('awards'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('profile_status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('verification_status')
                    ->label('Verification Status')
                    ->options([
                        'unverified'           => 'Unverified',
                        'pending_verification' => 'Pending Verification',
                        'verified'             => 'Verified',
                        'correction_requested' => 'Correction Requested',
                    ]),
                TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->placeholder('Active Teachers')
                    ->trueLabel('Archived Only')
                    ->falseLabel('Active Only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_archived', true),
                        false: fn (Builder $query) => $query->where('is_archived', false),
                        blank: fn (Builder $query) => $query, // Show all
                    ),
                TrashedFilter::make(),
            ],layout: FiltersLayout::Modal)
            ->filtersTriggerAction(function ($action) {
                return $action->slideOver();
            })
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
                Action::make('send_individual_email')
                    ->label('Send Email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->visible(fn (Teacher $record): bool => auth()->user()?->can('sendTeacherEmail', $record) ?? false)
                    ->modalHeading(fn (Teacher $record) => "Send Email to {$record->full_name}")
                    ->modalDescription(fn (Teacher $record) => "Select a saved template or customize the email content for {$record->full_name}.")
                    ->form(\App\Filament\Resources\Teachers\Support\TeacherEmailComposer::schema())
                    ->action(function (Teacher $record, array $data) {
                        \App\Filament\Resources\Teachers\Support\TeacherEmailComposer::send([$record], $data);
                    }),
                \Filament\Actions\Action::make('dashboard')
                    ->label('Dashboard')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->visible(fn (Teacher $record): bool => auth()->user()?->can('viewDashboard', $record) ?? false)
                    ->url(fn (Teacher $record) => \App\Filament\Pages\TeacherDashboard::getUrl(['teacher' => $record->id]))
                    ->openUrlInNewTab(false),

                // ── Sync Profile Score Action ─────────────────────────────
                \Filament\Actions\Action::make('syncProfileScore')
                    ->label('Sync Score')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->tooltip('Recalculate and save profile completion score')
                    ->visible(fn (Teacher $record): bool => auth()->user()?->can('syncProfileScore', $record) ?? false)
                    ->requiresConfirmation(false)
                    ->action(function (Teacher $record) {
                        try {
                            // Load all relations needed by ProfileGapEvaluator
                            $record->load([
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
                            ]);

                            $evaluator = new ProfileGapEvaluator();
                            $report    = $evaluator->evaluate($record);
                            $score     = $report['completion_percentage'];

                            $record->updateQuietly([
                                'profile_score'           => $score,
                                'profile_score_synced_at' => Carbon::now(),
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Score synced!')
                                ->body("{$record->full_name}: {$score}% profile completion")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                // ─────────────────────────────────────────────────────────
                /*
                 * The same ERP fill as the header action, for one teacher.
                 * Queued rather than run inline for the same reason: it is an
                 * outbound call to somebody else's server, and a slow ERP would
                 * otherwise hold the request open until it timed out.
                 */
                \Filament\Actions\Action::make('syncErpProfile')
                    ->label('Fill from ERP')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('warning')
                    ->visible(fn (Teacher $record): bool => auth()->user()?->can('syncErpProfile', $record) ?? false)
                    ->modalHeading(fn (Teacher $record): string => 'Fill Fields from the ERP — ' . $record->full_name)
                    ->modalDescription('Calls the ERP profile API for this teacher and fills only the fields chosen below.')
                    ->modalSubmitActionLabel('Start in background')
                    ->form([
                        \Filament\Forms\Components\CheckboxList::make('fields')
                            ->label('Fields to fill')
                            ->options(\App\Support\ErpProfileFields::all())
                            ->descriptions(\App\Support\ErpProfileFields::descriptions())
                            ->default(\App\Support\ErpProfileFields::defaultSelection())
                            ->columns(2)
                            ->bulkToggleable()
                            ->required()
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Radio::make('mode')
                            ->label('When we already hold a value')
                            ->options([
                                \App\Services\ErpProfileFieldSync::MODE_FILL_EMPTY => 'Only fill what is empty',
                                \App\Services\ErpProfileFieldSync::MODE_OVERWRITE => 'Overwrite with the ERP value',
                            ])
                            ->default(\App\Services\ErpProfileFieldSync::MODE_FILL_EMPTY)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(function (Teacher $record, array $data) {
                        $fields = \App\Support\ErpProfileFields::only($data['fields'] ?? []);

                        if ($fields === []) {
                            Notification::make()
                                ->warning()
                                ->title('No field selected')
                                ->body('Choose at least one field to fill.')
                                ->send();

                            return;
                        }

                        \App\Jobs\SyncErpTeacherProfilesJob::dispatch(
                            [$record->id],
                            $fields,
                            (string) ($data['mode'] ?? \App\Services\ErpProfileFieldSync::MODE_FILL_EMPTY),
                            auth()->id(),
                        );

                        Notification::make()
                            ->success()
                            ->title('Queued')
                            ->body($record->full_name . ' has been queued for an ERP profile refresh. You will be notified when it finishes.')
                            ->send();
                    }),

                \Filament\Actions\Action::make('syncFromOldDb')
                    ->label('Sync Old Data')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Teacher $record): bool => auth()->user()?->can('importErp', $record) ?? false)
                    ->modalHeading('Sync Teacher Data from Old DB')
                    ->modalDescription(fn (Teacher $record) => "Are you sure you want to sync data for {$record->full_name} (ID: {$record->employee_id}) from the old database?")
                    ->form(function (Teacher $record) {
                        $hasData = $record->educations()->exists() ||
                            $record->publications()->exists() ||
                            $record->awards()->exists() ||
                            $record->teachingAreas()->exists() ||
                            $record->jobExperiences()->exists() ||
                            $record->trainingExperiences()->exists() ||
                            $record->memberships()->exists();

                        if (!$hasData) {
                            return [];
                        }

                        return [
                            \Filament\Forms\Components\Radio::make('sync_mode')
                                ->label('Existing Data Action')
                                ->helperText('We found existing records (education, publications, experiences, etc.) for this teacher in the new database.')
                                ->options([
                                    'skip' => 'Skip Existing (Only import new/missing items)',
                                    'overwrite' => 'Overwrite All (Delete existing records and re-import everything)',
                                ])
                                ->default('skip')
                                ->required(),
                        ];
                    })
                    ->action(function (Teacher $record, array $data) {
                        $mode = $data['sync_mode'] ?? 'skip';
                        $syncService = resolve(\App\Services\SingleTeacherSyncService::class);
                        $result = $syncService->sync($record, $mode);

                        if ($result['success'] ?? false) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Sync Successful')
                                ->body($result['message'])
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Sync Failed')
                                ->body($result['message'] ?? 'An unknown error occurred.')
                                ->send();
                        }
                    })
            ])
            ->recordUrl(null)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('send_email_to_selected')
                        ->label('Send Email to Selected')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (): bool => auth()->user()?->can('bulkSendEmailToTeachers', Teacher::class) ?? false)
                        ->modalHeading('Send Email to Selected Teachers')
                        ->modalDescription('Select a saved email template or write custom content to send to selected teachers.')
                        ->form(\App\Filament\Resources\Teachers\Support\TeacherEmailComposer::schema())
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            \App\Filament\Resources\Teachers\Support\TeacherEmailComposer::send($records, $data);
                        }),
                    BulkAction::make('sync_selected_profile_scores')
                        ->label('Sync Selected Scores')
                        ->icon('heroicon-o-calculator')
                        ->color('info')
                        ->visible(fn (): bool => auth()->user()?->can('syncProfileScore', Teacher::class) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Sync Profile Scores for Selected Teachers')
                        ->modalDescription('Recalculate and save profile completion scores for the selected teachers.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $evaluator = new ProfileGapEvaluator();
                            $processed = 0;

                            $records->load([
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
                            ]);

                            $now = Carbon::now()->toDateTimeString();
                            $updates = [];

                            foreach ($records as $teacher) {
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

                            Notification::make()
                                ->success()
                                ->title('Profile Scores Updated!')
                                ->body("Recalculated profile scores for {$processed} selected teachers.")
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
