<?php

namespace App\Filament\Resources\Feedback\Tables;

use App\Models\Feedback;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything users have sent, most recently active first.
 */
class FeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('teacher.department')->withCount('messages'))
            ->defaultSort('last_activity_at', 'desc')
            ->recordUrl(fn (Feedback $record): string => \App\Filament\Resources\Feedback\FeedbackResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->formatStateUsing(fn (Feedback $record): string => $record->reference())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->wrap()
                    ->limit(70)
                    ->description(fn (Feedback $record): string => Feedback::TYPES[$record->type] ?? $record->type)
                    ->searchable(['subject', 'message']),

                TextColumn::make('submitter_name')
                    ->label('From')
                    ->description(fn (Feedback $record): ?string => $record->teacher?->department?->name)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Feedback::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => Feedback::statusColour($state))
                    ->description(fn (Feedback $record): ?string => $record->reopened_count > 0
                        ? 'Reopened ' . $record->reopened_count . '×'
                        : null)
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Feedback::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state): string => Feedback::priorityColour($state))
                    ->sortable(),

                TextColumn::make('waiting_on')
                    ->label('Whose turn')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Feedback::WAITING_ON_STAFF => 'Ours',
                        Feedback::WAITING_ON_SUBMITTER => 'The user\'s',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => $state === Feedback::WAITING_ON_STAFF ? 'warning' : 'gray')
                    ->placeholder('—'),

                TextColumn::make('messages_count')
                    ->label('Replies')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_activity_at')
                    ->label('Last activity')
                    ->since()
                    ->dateTimeTooltip('d M Y, h:i A')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(Feedback::STATUSES),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options(Feedback::TYPES),

                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(Feedback::PRIORITIES),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No feedback here')
            ->emptyStateDescription('What users send from the Feedback menu appears here.');
    }
}
