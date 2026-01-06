<?php

namespace App\Filament\Resources\MembershipTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembershipTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->label('Teachers')
                    ->state(function (\App\Models\MembershipType $record): int {
                        return \App\Models\Membership::query()
                            ->join('teachers', 'memberships.teacher_id', '=', 'teachers.id')
                            ->where('memberships.membership_type_id', $record->id)
                            ->whereNull('memberships.deleted_at')
                            ->where('teachers.is_archived', false)
                            ->distinct('memberships.teacher_id')
                            ->count('memberships.teacher_id');
                    })
                    ->badge()
                    ->color('info')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->withCount(['memberships as teachers_count' => function ($q) {
                            $q->join('teachers', 'memberships.teacher_id', '=', 'teachers.id')
                              ->whereNull('memberships.deleted_at')
                              ->where('teachers.is_archived', false)
                              ->select(\Illuminate\Support\Facades\DB::raw('count(distinct memberships.teacher_id)'));
                        }])->orderBy('teachers_count', $direction);
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('active')
                    ->label('Active Only')
                    ->query(fn ($query) => $query->where('is_active', true)),
                \Filament\Tables\Filters\Filter::make('inactive')
                    ->label('Inactive Only')
                    ->query(fn ($query) => $query->where('is_active', false)),
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
