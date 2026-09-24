<?php

namespace App\Filament\Resources\Faculties\Tables;

use App\Support\AdminScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacultiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),
                TextColumn::make('name')
                    ->label('Faculty Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('short_name')
                    ->label('Short')
                    ->searchable()
                    ->badge(),
                TextColumn::make('code')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                // A Head sees their faculty's row, but the numbers on it are
                // their own department's: the rest of the faculty is not theirs.
                TextColumn::make('departments_count')
                    ->label('Departments')
                    ->counts(['departments' => fn (Builder $query) => $query
                        ->when(AdminScope::departmentId(), fn (Builder $q, int $id) => $q->where('departments.id', $id))])
                    ->badge()
                    ->color('info'),
                TextColumn::make('teachers_count')
                    ->label('Teachers')
                    ->counts(['teachers' => fn (Builder $query) => AdminScope::teachers($query)])
                    ->badge()
                    ->color('success'),
                TextColumn::make('publications_count')
                    ->label('Publications')
                    ->counts(['publications' => fn (Builder $query) => $query
                        ->when(AdminScope::departmentId(), fn (Builder $q, int $id) => $q->where('publications.department_id', $id))])
                    ->badge()
                    ->color('warning'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('erp_id')
                    ->label('ERP ID')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
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
