<?php

namespace App\Filament\Resources\TeacherVersions\Tables;

use App\Services\TeacherVersionService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
//use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
class TeacherVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('teacher.first_name')
                    ->label('Teacher')
                    ->formatStateUsing(fn ($record) => $record->teacher?->first_name . ' ' . $record->teacher?->last_name)
                    ->searchable(),
                TextColumn::make('version_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('change_summary')
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('submittedBy.name')
                    ->label('Submitted By')
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewedBy.name')
                    ->label('Reviewed By')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),

                // Approve Action - for pending versions
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Version')
                    ->modalDescription('Are you sure you want to approve this version? The teacher profile will be updated.')
                    ->action(function ($record) {
                        app(TeacherVersionService::class)->approveVersion($record);
                        Notification::make()
                            ->success()
                            ->title('Version Approved')
                            ->body('Teacher profile has been updated.')
                            ->send();
                    }),

                // Reject Action - for pending versions
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Version')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('remarks')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(TeacherVersionService::class)->rejectVersion($record, $data['remarks']);
                        Notification::make()
                            ->success()
                            ->title('Version Rejected')
                            ->body('The teacher has been notified.')
                            ->send();
                    }),

                // Activate Action - for approved versions (rollback feature)
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'approved' && !$record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Activate Version (Rollback)')
                    ->modalDescription('This will restore the teacher profile to this version\'s state. Are you sure?')
                    ->action(function ($record) {
                        app(TeacherVersionService::class)->activateVersion($record);
                        Notification::make()
                            ->success()
                            ->title('Version Activated')
                            ->body('Teacher profile has been restored to this version.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
