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

class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->selectCurrentPageOnly()
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
                TextColumn::make('user.roles.name')
                    ->label('System Roles')
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\User::select('roles.name')
                                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                                ->whereColumn('users.id', 'teachers.user_id')
                                ->where('model_has_roles.model_type', \App\Models\User::class)
                                ->limit(1),
                            $direction
                        );
                    })
                    ->toggleable(),
                TextColumn::make('user.administrativeRoles.name')
                    ->label('Admin Roles')
                    ->badge()
                    ->color('warning')
                    ->separator(', ')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('None')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\AdministrativeRole::select('administrative_roles.name')
                                ->join('administrative_role_user', 'administrative_roles.id', '=', 'administrative_role_user.administrative_role_id')
                                ->whereColumn('administrative_role_user.user_id', 'teachers.user_id')
                                ->limit(1),
                            $direction
                        );
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
                        // Check if teacher's employment status allows login
                        $status = $record->employmentStatus;
                        if ($status && !$status->allow_login) {
                            return true;
                        }
                        return false;
                    }),
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
                Filter::make('organization_id')
                    ->label('Organization')
                    ->form([
                        Select::make('type')
                            ->label('Organization Type')
                            ->options([
                                'is_educational_institution' => 'Educational Institution',
                                'is_employer' => 'Employer / Company',
                                'is_training_center' => 'Training Center',
                                'is_professional_body' => 'Professional Body / Membership Org',
                                'is_awarding_body' => 'Awarding Body',
                                'is_certifying_authority' => 'Certifying Authority',
                                'is_funding_agency' => 'Funding Agency',
                            ])
                            ->live(),
                        Select::make('value')
                            ->label('Organization')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $type = $get('type');
                                return \App\Models\Organization::query()
                                    ->where('is_active', true)
                                    ->when($type, fn ($q) => $q->where($type, true))
                                    ->where('name', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->pluck('name', 'id');
                            })
                            ->getOptionLabelUsing(fn ($value) => \App\Models\Organization::find($value)?->name),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $orgId = $data['value'] ?? null;
                        $type = $data['type'] ?? null;

                        if ($orgId) {
                            $query->where(function ($q) use ($orgId, $type) {
                                if (!$type || $type === 'is_educational_institution') {
                                    $q->orWhereHas('educations', fn ($sub) => $sub->where('educational_institution_id', $orgId));
                                }
                                if (!$type || $type === 'is_employer') {
                                    $q->orWhereHas('jobExperiences', fn ($sub) => $sub->where('organization_id', $orgId));
                                }
                                if (!$type || $type === 'is_training_center') {
                                    $q->orWhereHas('trainingExperiences', fn ($sub) => $sub->where('organization_id', $orgId));
                                }
                                if (!$type || $type === 'is_professional_body') {
                                    $q->orWhereHas('memberships', fn ($sub) => $sub->where('membership_organization_id', $orgId));
                                }
                                if (!$type || $type === 'is_awarding_body') {
                                    $q->orWhereHas('awards', fn ($sub) => $sub->where('awarding_body_organization_id', $orgId));
                                }
                                if (!$type || $type === 'is_certifying_authority') {
                                    $q->orWhereHas('certifications', fn ($sub) => $sub->where('issuing_authority_organization_id', $orgId));
                                }
                                if (!$type || $type === 'is_funding_agency') {
                                    $q->orWhereHas('researchProjects', fn ($sub) => $sub->where('funding_agency_organization_id', $orgId));
                                }
                            });
                        }
                    }),
                SelectFilter::make('position_id')
                    ->label('Position')
                    ->searchable()
                    ->options(fn () => \App\Models\Position::query()->where('is_active', true)->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('jobExperiences', function ($q) use ($data) {
                                $q->where('position_id', $data['value']);
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
                    ->modalHeading(fn (Teacher $record) => "Send Email to {$record->full_name}")
                    ->modalDescription(fn (Teacher $record) => "Select a saved template or customize the email content for {$record->full_name}.")
                    ->form([
                        Select::make('template_id')
                            ->label('Select Email Template')
                            ->placeholder('Choose a template to load default subject & body...')
                            ->options(fn () => \App\Models\EmailTemplate::query()->where('is_active', true)->pluck('name', 'id')->toArray())
                            ->default(fn () => \App\Models\EmailTemplate::where('key', 'profile_verification_request')->value('id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) {
                                    $template = \App\Models\EmailTemplate::find($state);
                                    if ($template) {
                                        $set('subject', $template->subject);
                                        $set('body', $template->body);
                                    }
                                }
                            }),

                        \Filament\Forms\Components\TextInput::make('subject')
                            ->label('Email Subject Line')
                            ->required()
                            ->maxLength(255)
                            ->default(fn () => \App\Models\EmailTemplate::where('key', 'profile_verification_request')->value('subject') ?? 'Action Required: Please Review & Confirm Your Profile Data'),

                        \Filament\Forms\Components\Textarea::make('body')
                            ->label('Email Body / Message')
                            ->required()
                            ->rows(7)
                            ->default(fn () => \App\Models\EmailTemplate::where('key', 'profile_verification_request')->value('body') ?? '')
                            ->helperText('Available placeholders: {teacher_name}, {employee_id}, {department}, {designation}, {profile_score}, {verification_link}'),
                    ])
                    ->action(function (Teacher $record, array $data) {
                        \App\Jobs\SendCustomTemplatedEmailJob::dispatch($record, $data['subject'], $data['body']);

                        Notification::make()
                            ->title("Email queued for {$record->full_name}!")
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('dashboard')
                    ->label('Dashboard')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->url(fn (Teacher $record) => \App\Filament\Pages\TeacherDashboard::getUrl(['teacher' => $record->id]))
                    ->openUrlInNewTab(false),

                // ── Sync Profile Score Action ─────────────────────────────
                \Filament\Actions\Action::make('syncProfileScore')
                    ->label('Sync Score')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->tooltip('Recalculate and save profile completion score')
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
                \Filament\Actions\Action::make('syncFromOldDb')
                    ->label('Sync Old Data')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
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
                        ->modalHeading('Send Email to Selected Teachers')
                        ->modalDescription('Select a saved email template or write custom content to send to selected teachers.')
                        ->form([
                            Select::make('template_id')
                                ->label('Select Email Template')
                                ->placeholder('Choose a template to load default subject & body...')
                                ->options(fn () => \App\Models\EmailTemplate::query()->where('is_active', true)->pluck('name', 'id')->toArray())
                                ->default(fn () => \App\Models\EmailTemplate::where('key', 'profile_verification_request')->value('id'))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                    if ($state) {
                                        $template = \App\Models\EmailTemplate::find($state);
                                        if ($template) {
                                            $set('subject', $template->subject);
                                            $set('body', $template->body);
                                        }
                                    }
                                }),

                            \Filament\Forms\Components\TextInput::make('subject')
                                ->label('Email Subject Line')
                                ->required()
                                ->maxLength(255)
                                ->default(fn () => \App\Models\EmailTemplate::where('key', 'profile_verification_request')->value('subject') ?? 'Action Required: Please Review & Confirm Your Profile Data'),

                            \Filament\Forms\Components\Textarea::make('body')
                                ->label('Email Body / Message')
                                ->required()
                                ->rows(7)
                                ->default(fn () => \App\Models\EmailTemplate::where('key', 'profile_verification_request')->value('body') ?? '')
                                ->helperText('Available placeholders: {teacher_name}, {employee_id}, {department}, {designation}, {profile_score}, {verification_link}'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $subject = $data['subject'];
                            $body    = $data['body'];
                            $count   = $records->count();

                            foreach ($records as $teacher) {
                                \App\Jobs\SendCustomTemplatedEmailJob::dispatch($teacher, $subject, $body);
                            }

                            Notification::make()
                                ->title("Email queued for {$count} selected teachers!")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('sync_selected_profile_scores')
                        ->label('Sync Selected Scores')
                        ->icon('heroicon-o-calculator')
                        ->color('info')
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
