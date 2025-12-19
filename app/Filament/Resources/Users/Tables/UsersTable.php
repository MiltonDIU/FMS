<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ReplicateBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->separator(','),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->sortable()
                    ->dateTime()
                    ->searchable(),
                ToggleColumn::make('is_active')->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([
                TrashedFilter::make(),
                \Filament\Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->label('Filter by Role'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
                ReplicateAction::make()
                    ->form([
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique('users', 'email')
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255),
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->options(\Spatie\Permission\Models\Role::pluck('name', 'id'))
                            ->default(fn ($record) => $record->roles->pluck('id')->toArray())
                            ->searchable(),
                    ])
                    ->using(function ($record, array $data): \App\Models\User {
                        // Create a replica
                        $replica = $record->replicate();
                        
                        // Set form data
                        $replica->email = $data['email'];
                        $replica->password = bcrypt($data['password']);
                        
                        // Save first
                        $replica->save();
                        
                        // Sync roles
                        if (!empty($data['roles'])) {
                            $replica->roles()->sync($data['roles']);
                        }
                        
                        return $replica;
                    }),
                              
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),

                ]),
            ]);
    }
}
