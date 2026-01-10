<?php

namespace App\Filament\Resources\IncentiveLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IncentiveLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Log ID')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'info',
                        'updated' => 'warning',
                        'approved' => 'success',
                        'paid' => 'success',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                        'pending' => 'Set to Pending',
                        default => ucfirst($state),
                    }),
                TextColumn::make('changedByUser.name')
                    ->label('Changed By')
                    ->searchable(),
                TextColumn::make('publicationIncentive.total_amount')
                    ->label('Total Amount')
                    ->money('BDT'),
                TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(30)
                    ->placeholder('—'),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '—';
                        
                        $changes = [];
                        if (isset($state['old']) && isset($state['new'])) {
                            foreach ($state['new'] as $key => $newValue) {
                                $oldValue = $state['old'][$key] ?? 'null';
                                if ($oldValue !== $newValue) {
                                    $changes[] = "{$key}: {$oldValue} → {$newValue}";
                                }
                            }
                        }
                        
                        return implode(', ', $changes) ?: '—';
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Date/Time')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                \Filament\Tables\Grouping\Group::make('publicationIncentive.publication.title')
                    ->label('Publication')
                    ->collapsible(),
            ])
            ->defaultGroup('publicationIncentive.publication.title')
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                        'pending' => 'Set to Pending',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
