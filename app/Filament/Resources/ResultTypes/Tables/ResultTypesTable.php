<?php

namespace App\Filament\Resources\ResultTypes\Tables;

use App\Models\ResultType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ResultTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type_name')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('type_name')
                    ->label('Type Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->label('Teachers')
                    ->state(function (ResultType $record): int {
                        return \App\Models\Education::query()
                            ->join('teachers', 'educations.teacher_id', '=', 'teachers.id')
                            ->where('educations.result_type_id', $record->id)
                            ->whereNull('educations.deleted_at')
                            ->where('teachers.is_archived', false)
                            ->distinct('educations.teacher_id')
                            ->count('educations.teacher_id');
                    })
                    ->badge()
                    ->color('info')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->withCount(['education as teachers_count' => function ($q) {
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
