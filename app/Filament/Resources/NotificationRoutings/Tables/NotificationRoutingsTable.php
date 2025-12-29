<?php

namespace App\Filament\Resources\NotificationRoutings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotificationRoutingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
               TextColumn::make('trigger_type')
                    ->label('Trigger Event')
                    ->searchable()
                    ->sortable(),

               TextColumn::make('trigger_sections')
                    ->label('Sections')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'All approval sections';
                        }
                        
                        // Ensure state is array to prevent count() errors
                        if (!is_array($state)) {
                            // If it's a JSON string, decode it
                            if (is_string($state) && str_starts_with(trim($state), '[')) {
                                $state = json_decode($state, true) ?? [$state];
                            } else {
                                $state = (array) $state;
                            }
                        }

                        return \App\Models\ApprovalSetting::whereIn('section_key', $state)
                            ->pluck('section_label')
                            ->implode(', ');
                    })
                    ->wrap()
                    ->searchable(),

                TextColumn::make('recipient_type')
                    ->label('Recipient Type')
                    ->badge()
                    ->colors([
                        'primary' => 'role',
                        'success' => 'user',
                        'warning' => 'department_head',
                    ])
                    ->sortable(),

                TextColumn::make('recipient_identifiers')
                    ->label('Recipients')
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        if (empty($state)) {
                            return 'Auto-detect';
                        }
                        
                        // Ensure state is array
                        if (!is_array($state)) {
                             if (is_string($state) && str_starts_with(trim($state), '[')) {
                                $state = json_decode($state, true) ?? [$state];
                            } else {
                                $state = (array) $state;
                            }
                        }
                        
                        if ($record->recipient_type === 'role') {
                            return implode(', ', $state);
                        }

                        if ($record->recipient_type === 'user') {
                            return \App\Models\User::whereIn('id', $state)
                                ->pluck('name')
                                ->implode(', ');
                        }

                        return 'N/A';
                    })
                    ->wrap()
                    ->limit(50),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
