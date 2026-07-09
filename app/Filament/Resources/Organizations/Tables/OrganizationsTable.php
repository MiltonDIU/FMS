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

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
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
                //
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
                                // 1. Update related job experiences
                                \Illuminate\Support\Facades\DB::table('job_experiences')
                                    ->whereIn('organization_id', $sourceIds)
                                    ->update([
                                        'organization_id' => $targetId,
                                        'organization' => $targetRecord->name,
                                    ]);

                                // 2. Delete source records
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
