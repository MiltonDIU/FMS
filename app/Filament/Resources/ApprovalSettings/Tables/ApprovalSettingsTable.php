<?php

namespace App\Filament\Resources\ApprovalSettings\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ApprovalSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('section_key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section_label')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('requires_approval')
                    ->label('Requires Approval'),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Is Active'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enable_requires_approval')
                        ->label('Enable Required Approval')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Enable Required Approval')
                        ->modalDescription('Are you sure you want to enable required approval for the selected settings?')
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            foreach ($records as $record) {
                                $record->update(['requires_approval' => true]);
                            }

                            Notification::make()
                                ->title("Required approval enabled for {$count} setting(s)")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('disable_requires_approval')
                        ->label('Disable Required Approval')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Disable Required Approval')
                        ->modalDescription('Are you sure you want to disable required approval for the selected settings?')
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            foreach ($records as $record) {
                                $record->update(['requires_approval' => false]);
                            }

                            Notification::make()
                                ->title("Required approval disabled for {$count} setting(s)")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('activate_selected')
                        ->label('Activate Status')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Activate Selected Settings')
                        ->modalDescription('Are you sure you want to activate the selected settings?')
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            foreach ($records as $record) {
                                $record->update(['is_active' => true]);
                            }

                            Notification::make()
                                ->title("Activated {$count} setting(s)")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('deactivate_selected')
                        ->label('Deactivate Status')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Selected Settings')
                        ->modalDescription('Are you sure you want to deactivate the selected settings?')
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                            }

                            Notification::make()
                                ->title("Deactivated {$count} setting(s)")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

