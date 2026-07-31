<?php

namespace App\Filament\Resources\ApprovalSettings\Tables;

use App\Models\ApprovalSetting;
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
                    ->label('Requires Approval')
                    // Turning this off makes teacher edits apply straight to the
                    // live profile with no version, no reviewer and no
                    // notification, so it must not be reachable with read access
                    // alone. Inline columns ignore policies; only disabled() is
                    // consulted before saving.
                    ->disabled(fn (ApprovalSetting $record): bool => ! auth()->user()?->can('update', $record)),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Is Active')
                    // Deactivating a section takes it out of the approval check
                    // just as surely as clearing requires_approval.
                    ->disabled(fn (ApprovalSetting $record): bool => ! auth()->user()?->can('update', $record)),
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
                        // Same reach as the row toggles, applied to every
                        // selected section at once, so it needs the same
                        // permission. visible() is enforced server-side:
                        // Filament checks it again when the action is called.
                        ->visible(fn (): bool => auth()->user()?->can('Update:ApprovalSetting') ?? false)
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
                        // Same reach as the row toggles, applied to every
                        // selected section at once, so it needs the same
                        // permission. visible() is enforced server-side:
                        // Filament checks it again when the action is called.
                        ->visible(fn (): bool => auth()->user()?->can('Update:ApprovalSetting') ?? false)
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
                        // Same reach as the row toggles, applied to every
                        // selected section at once, so it needs the same
                        // permission. visible() is enforced server-side:
                        // Filament checks it again when the action is called.
                        ->visible(fn (): bool => auth()->user()?->can('Update:ApprovalSetting') ?? false)
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
                        // Same reach as the row toggles, applied to every
                        // selected section at once, so it needs the same
                        // permission. visible() is enforced server-side:
                        // Filament checks it again when the action is called.
                        ->visible(fn (): bool => auth()->user()?->can('Update:ApprovalSetting') ?? false)
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

