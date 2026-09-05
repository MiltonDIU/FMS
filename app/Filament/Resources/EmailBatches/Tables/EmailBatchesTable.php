<?php

namespace App\Filament\Resources\EmailBatches\Tables;

use App\Models\EmailBatch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every send, newest first, with the five numbers that matter across it.
 *
 * The counts are worked out by withStats() in one query rather than a count per
 * cell, which is the difference between this page loading and not.
 */
class EmailBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withStats())
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('d M Y, H:i')
                    ->description(fn (EmailBatch $record): string => $record->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->wrap()
                    ->limit(60)
                    ->description(fn (EmailBatch $record): ?string => $record->template_name)
                    ->searchable(),

                TextColumn::make('sender.name')
                    ->label('Sent by')
                    // No sender means the console command, which runs with
                    // nobody signed in rather than as an anonymous person.
                    ->placeholder('Console')
                    ->description(fn (EmailBatch $record): string => $record->sourceLabel())
                    ->toggleable(),

                TextColumn::make('total_recipients')
                    ->label('Recipients')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('sent_count')
                    ->label('Sent')
                    ->alignRight()
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('opened_count')
                    ->label('Read')
                    ->alignRight()
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('queued_count')
                    ->label('In queue')
                    ->alignRight()
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->alignRight()
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('skipped_count')
                    ->label('Skipped')
                    ->alignRight()
                    ->badge()
                    ->color('gray')
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Sent from')
                    ->options([
                        EmailBatch::SOURCE_INDIVIDUAL => 'One teacher',
                        EmailBatch::SOURCE_SELECTED => 'Selected rows',
                        EmailBatch::SOURCE_FILTERED => 'Faculty / department filter',
                        EmailBatch::SOURCE_CONSOLE => 'Console command',
                        EmailBatch::SOURCE_RESEND => 'Follow-up',
                    ]),

                SelectFilter::make('email_template_id')
                    ->label('Template')
                    ->relationship('template', 'name')
                    ->searchable(),

                Filter::make('has_failures')
                    ->label('Only batches with failures')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereHas('recipients', fn (Builder $q) => $q->where('status', 'failed'))),

                Filter::make('has_unread')
                    ->label('Only batches nobody has read')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDoesntHave('recipients', fn (Builder $q) => $q->whereNotNull('opened_at'))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
