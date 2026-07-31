<?php

namespace App\Filament\Resources\EmploymentStatuses\Tables;

use App\Models\EmploymentStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmploymentStatusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('color')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                IconColumn::make('check_active')
                    ->label('Keeps Active')
                    ->boolean()
                    ->sortable(),
                \Filament\Tables\Columns\ToggleColumn::make('allow_login')
                    ->label('Allow Login')
                    ->sortable()
                    // canAccessPanel() consults this per teacher, so one switch
                    // grants or revokes panel access for every teacher holding
                    // the status. Inline columns skip policies; only disabled()
                    // is checked before saving.
                    ->disabled(fn (EmploymentStatus $record): bool => ! auth()->user()?->can('update', $record)),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->counts('teachers')
                    ->label('Teachers')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('active')
                    ->label('Active Only')
                    ->query(fn ($query) => $query->where('is_active', true)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
