<?php

namespace App\Filament\Resources\MembershipOrganizations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MembershipOrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Active'),
                TextColumn::make('createdBy.full_name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('activated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('activatedBy.full_name')
                    ->label('Activated By')
                    ->toggleable(),
                TextColumn::make('memberships_count')
                    ->counts('memberships')
                    ->label('Usage')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                \Filament\Tables\Filters\Filter::make('active')
                    ->label('Active Only')
                    ->query(fn ($query) => $query->where('is_active', true)),
                \Filament\Tables\Filters\Filter::make('inactive')
                    ->label('Inactive Only')
                    ->query(fn ($query) => $query->where('is_active', false)),
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update([
                                'is_active' => true,
                                'activated_at' => now(),
                                'activated_by' => auth()->user()?->teacher?->id,
                            ]);
                        }),
                    \Filament\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
