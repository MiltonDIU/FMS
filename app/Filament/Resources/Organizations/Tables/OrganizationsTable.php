<?php

namespace App\Filament\Resources\Organizations\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent Org')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->limit(30),
                TextColumn::make('types')
                    ->label('Type')
                    ->badge()
                    ->state(function (Organization $record): array {
                        $types = [];
                        if ($record->is_educational_institution) $types[] = 'Education';
                        if ($record->is_employer) $types[] = 'Employer';
                        if ($record->is_training_center) $types[] = 'Training';
                        if ($record->is_professional_body) $types[] = 'Professional Body';
                        if ($record->is_awarding_body) $types[] = 'Awarding';
                        if ($record->is_certifying_authority) $types[] = 'Certifying';
                        if ($record->is_funding_agency) $types[] = 'Funding';
                        return $types;
                    }),
                TextColumn::make('teachers_count')
                    ->label('Total Teachers')
                    ->sortable()
                    ->color('primary')
                    ->weight('bold')
                    ->url(fn ($record) => \App\Filament\Resources\Teachers\TeacherResource::getUrl('index', [
                        'filters' => [
                            'organization_id' => [
                                'value' => $record->id,
                            ],
                        ],
                    ])),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('creator.full_name')
                    ->label('Created By')
                    ->placeholder('System')
                    ->sortable(),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('System')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('type')
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
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->where($data['value'], true);
                        }
                    }),
                \Filament\Tables\Filters\SelectFilter::make('hierarchy')
                    ->label('Hierarchy')
                    ->options([
                        'standalone' => 'Standalone (no parent)',
                        'sub_org'    => 'Sub-organization (has parent)',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'standalone') {
                            $query->whereNull('parent_id');
                        } elseif ($data['value'] === 'sub_org') {
                            $query->whereNotNull('parent_id');
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('mergeSelected')
                        ->label('Merge Selected')
                        ->icon('heroicon-o-arrows-pointing-in')
                        ->color('warning')
                        ->form(fn (\Illuminate\Support\Collection $records) => [
                            \Filament\Forms\Components\Select::make('target_id')
                                ->label('Select Primary / Target Organization')
                                ->options($records->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data) {
                            $targetId = $data['target_id'];
                            $targetRecord = $records->firstWhere('id', $targetId);

                            if (!$targetRecord) {
                                return;
                            }

                            $sourceIds = $records->pluck('id')->reject($targetId)->toArray();

                            if (empty($sourceIds)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Please select more than one record to merge.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            \Illuminate\Support\Facades\DB::transaction(function () use ($targetId, $targetRecord, $sourceIds) {
                                // 1. Update related educations
                                \Illuminate\Support\Facades\DB::table('educations')
                                    ->whereIn('educational_institution_id', $sourceIds)
                                    ->update([
                                        'educational_institution_id' => $targetId,
                                        'institution' => $targetRecord->name,
                                    ]);

                                // 2. Update related job experiences
                                \Illuminate\Support\Facades\DB::table('job_experiences')
                                    ->whereIn('organization_id', $sourceIds)
                                    ->update([
                                        'organization_id' => $targetId,
                                        'organization' => $targetRecord->name,
                                    ]);

                                // 3. Update related training experiences
                                \Illuminate\Support\Facades\DB::table('training_experiences')
                                    ->whereIn('organization_id', $sourceIds)
                                    ->update([
                                        'organization_id' => $targetId,
                                        'organization' => $targetRecord->name,
                                    ]);

                                // 4. Update related memberships
                                \Illuminate\Support\Facades\DB::table('memberships')
                                    ->whereIn('membership_organization_id', $sourceIds)
                                    ->update([
                                        'membership_organization_id' => $targetId,
                                    ]);

                                // 5. Update related awards
                                \Illuminate\Support\Facades\DB::table('awards')
                                    ->whereIn('awarding_body_organization_id', $sourceIds)
                                    ->update([
                                        'awarding_body_organization_id' => $targetId,
                                        'awarding_body' => $targetRecord->name,
                                    ]);

                                // 6. Update related certifications
                                \Illuminate\Support\Facades\DB::table('certifications')
                                    ->whereIn('issuing_authority_organization_id', $sourceIds)
                                    ->update([
                                        'issuing_authority_organization_id' => $targetId,
                                        'issuing_authority' => $targetRecord->name,
                                    ]);

                                // 7. Update related research projects
                                \Illuminate\Support\Facades\DB::table('research_projects')
                                    ->whereIn('funding_agency_organization_id', $sourceIds)
                                    ->update([
                                        'funding_agency_organization_id' => $targetId,
                                        'funding_agency' => $targetRecord->name,
                                    ]);

                                // 8. Merge type boolean flags
                                $sources = \App\Models\Organization::whereIn('id', $sourceIds)->get();
                                $updateFlags = [];
                                foreach (['is_educational_institution', 'is_employer', 'is_training_center', 'is_professional_body', 'is_awarding_body', 'is_certifying_authority', 'is_funding_agency'] as $flag) {
                                    if (!$targetRecord->$flag && $sources->contains($flag, true)) {
                                        $updateFlags[$flag] = true;
                                    }
                                }
                                if (!empty($updateFlags)) {
                                    $targetRecord->update($updateFlags);
                                }

                                // 9. Delete source records
                                \App\Models\Organization::whereIn('id', $sourceIds)->delete();
                            });

                            // Update cached file dynamically
                            app(\App\Services\DuplicateFinderService::class)->removeGroupFromCache('organization', $targetId, $sourceIds);

                            \Filament\Notifications\Notification::make()
                                ->title('Merged successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
