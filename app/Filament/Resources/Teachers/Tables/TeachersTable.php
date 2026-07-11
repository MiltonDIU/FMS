<?php

namespace App\Filament\Resources\Teachers\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use App\Models\Teacher;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            //->modifyQueryUsing(fn (Builder $query) => $query->where('is_archived', false))
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
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
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
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
                \Filament\Actions\Action::make('dashboard')
                    ->label('Dashboard')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->url(fn (Teacher $record) => \App\Filament\Pages\TeacherDashboard::getUrl(['teacher' => $record->id]))
                    ->openUrlInNewTab(false),
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
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
