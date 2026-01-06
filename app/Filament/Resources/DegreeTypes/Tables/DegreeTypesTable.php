<?php

namespace App\Filament\Resources\DegreeTypes\Tables;

use App\Models\DegreeType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DegreeTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc') // Added default sort
            ->columns([
                TextColumn::make('level.name')
                    ->label('Level')
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->label('Teachers')
                    ->state(function (DegreeType $record): int {
                        return \App\Models\Education::query()
                            ->join('teachers', 'educations.teacher_id', '=', 'teachers.id')
                            ->where('educations.degree_type_id', $record->id)
                            ->whereNull('educations.deleted_at')
                            ->where('teachers.is_archived', false)
                            ->distinct('educations.teacher_id')
                            ->count('educations.teacher_id');
                    })
                    ->badge()
                    ->color('info')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->withCount(['educations as teachers_count' => function ($q) {
                            $q->join('teachers', 'educations.teacher_id', '=', 'teachers.id')
                              ->whereNull('educations.deleted_at')
                              ->where('teachers.is_archived', false)
                              ->select(DB::raw('count(distinct educations.teacher_id)'));
                        }])->orderBy('teachers_count', $direction);
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('degree_level_id')
                    ->relationship('level', 'name')
                    ->label('Level'),
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
