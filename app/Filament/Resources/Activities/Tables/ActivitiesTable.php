<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Listeners\LogAuthenticationActivity;
use App\Models\Activity;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    /**
     * Colours for the events this system actually records. Anything unexpected
     * falls through to grey rather than being given a meaning it has not earned.
     */
    protected const EVENT_COLOURS = [
        'created' => 'success',
        'updated' => 'info',
        'deleted' => 'danger',
        'restored' => 'warning',
        'login' => 'success',
        'logout' => 'gray',
        'failed' => 'warning',
        'locked' => 'danger',
        'password_reset' => 'warning',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y, H:i:s')
                    ->description(fn (Activity $record): string => $record->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label('Area')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $state): string => self::EVENT_COLOURS[$state] ?? 'gray')
                    ->sortable(),

                TextColumn::make('causer')
                    ->label('Who')
                    /*
                     * A failed sign-in against an account that does not exist has
                     * no causer, and the address that was tried is the only thing
                     * identifying it — better than an empty cell, and it is what
                     * makes a run of attempts visible.
                     */
                    ->state(fn (Activity $record): string => $record->causer?->name
                        ?? ($record->properties['attempted'] ?? '—'))
                    ->description(fn (Activity $record): ?string => $record->causer?->email)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHasMorph('causer', [User::class], fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))),

                TextColumn::make('subject')
                    ->label('Record')
                    ->state(fn (Activity $record): string => $record->subject_type
                        ? class_basename($record->subject_type) . ' #' . $record->subject_id
                        : '—')
                    ->color('gray'),

                TextColumn::make('changed')
                    ->label('Changed')
                    ->state(fn (Activity $record): string => self::changedFields($record))
                    ->wrap()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('properties.ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Area')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->orderBy('log_name')
                        ->pluck('log_name', 'log_name')
                        ->all()),

                SelectFilter::make('event')
                    ->label('Event')
                    ->multiple()
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('event')
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->all()),

                SelectFilter::make('causer_id')
                    ->label('Who')
                    ->searchable()
                    ->options(fn (): array => User::query()
                        ->whereIn('id', Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),

                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'From ' . \Carbon\Carbon::parse($data['from'])->format('d M Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until ' . \Carbon\Carbon::parse($data['until'])->format('d M Y');
                        }

                        return $indicators;
                    }),

                Filter::make('sign_in_activity')
                    ->label('Sign-in activity only')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('log_name', LogAuthenticationActivity::LOG_NAME))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            /*
             * No bulk actions. Nothing here may be edited or deleted by a person;
             * old entries go when the scheduled activitylog:clean reaches the
             * retention period.
             */
            ->toolbarActions([])
            ->emptyStateHeading('Nothing recorded yet')
            ->emptyStateDescription('Account changes and sign-in activity appear here as they happen.');
    }

    /** The field names an entry touched, for a glance down the column. */
    protected static function changedFields(Activity $record): string
    {
        $changes = $record->properties['attributes']
            ?? $record->properties['old']
            ?? [];

        if (! is_array($changes) || $changes === []) {
            return '—';
        }

        $keys = array_keys($changes);

        return count($keys) > 4
            ? implode(', ', array_slice($keys, 0, 4)) . ' +' . (count($keys) - 4) . ' more'
            : implode(', ', $keys);
    }
}
