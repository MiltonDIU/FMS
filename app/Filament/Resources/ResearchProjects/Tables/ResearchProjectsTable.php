<?php

namespace App\Filament\Resources\ResearchProjects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ResearchProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                    
                TextColumn::make('teacher.full_name') // Using accessor
                    ->label('Teacher')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('role')
                    ->badge()
                    ->color('info'),
                    
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'submitted' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                    
                TextColumn::make('budget')
                    ->money('BDT')
                    ->sortable(),
                    
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                    
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'submitted' => 'Submitted',
                        'rejected' => 'Rejected',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
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
