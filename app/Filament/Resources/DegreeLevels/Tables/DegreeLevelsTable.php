<?php

namespace App\Filament\Resources\DegreeLevels\Tables;

use App\Models\DegreeLevel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DegreeLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('teachers_count')
                    ->label('Teachers')
                    ->state(function (DegreeLevel $record): int {
                        return \App\Models\Education::query()
                            ->join('degree_types', 'educations.degree_type_id', '=', 'degree_types.id')
                            ->join('teachers', 'educations.teacher_id', '=', 'teachers.id')
                            ->where('degree_types.degree_level_id', $record->id)
                            ->whereNull('educations.deleted_at')
                            ->where('teachers.is_archived', false)
                            ->distinct('educations.teacher_id')
                            ->count('educations.teacher_id');
                    })
                    ->badge()
                    ->color('info')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->withCount(['degreeTypes as teachers_count' => function ($q) {
                            $q->join('educations', 'degree_types.id', '=', 'educations.degree_type_id')
                              ->join('teachers', 'educations.teacher_id', '=', 'teachers.id')
                              ->whereNull('educations.deleted_at')
                              ->where('teachers.is_archived', false)
                              ->select(DB::raw('count(distinct educations.teacher_id)'));
                        }])->orderBy('teachers_count', $direction);
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_report')
                    ->label('Report')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
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
                ]),
            ]);
    }
}
