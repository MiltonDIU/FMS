<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    /** Keys that describe the request rather than the change itself. */
    protected const CONTEXT_KEYS = [Activity::IP_KEY, Activity::AGENT_KEY];

    /** Keys holding the change itself, shown as before and after instead. */
    protected const CHANGE_KEYS = ['attributes', 'old'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What happened')
                ->columns(3)
                ->schema([
                    TextEntry::make('event')->label('Event')->badge(),
                    TextEntry::make('log_name')->label('Area')->badge()->color('gray'),
                    TextEntry::make('created_at')
                        ->label('When')
                        ->dateTime('d M Y, H:i:s')
                        ->helperText(fn (Activity $record): string => $record->created_at->diffForHumans()),

                    TextEntry::make('causer')
                        ->label('Who')
                        ->state(fn (Activity $record): string => $record->causer?->name
                            ?? ($record->properties['attempted'] ?? 'Not signed in'))
                        ->helperText(fn (Activity $record): ?string => $record->causer?->email),

                    TextEntry::make('subject')
                        ->label('Record')
                        ->state(fn (Activity $record): string => $record->subject_type
                            ? class_basename($record->subject_type) . ' #' . $record->subject_id
                            : '—'),

                    TextEntry::make('description')->label('Description'),
                ]),

            /*
             * Before and after side by side. The package stores a change as two
             * maps — 'old' and 'attributes' — and reading a raw JSON blob to work
             * out what moved is the part that makes an audit trail unusable, so
             * they are laid out as a pair.
             */
            Section::make('Change')
                ->columns(2)
                ->visible(fn (Activity $record): bool => self::hasChange($record))
                ->schema([
                    KeyValueEntry::make('properties.old')
                        ->label('Before')
                        ->keyLabel('Field')
                        ->valueLabel('Previous value')
                        ->placeholder('Nothing — this record was created'),

                    KeyValueEntry::make('properties.attributes')
                        ->label('After')
                        ->keyLabel('Field')
                        ->valueLabel('New value')
                        ->placeholder('Nothing — this record was deleted'),
                ]),

            Section::make('Where it came from')
                ->columns(2)
                ->schema([
                    TextEntry::make('properties.ip_address')
                        ->label('IP address')
                        ->placeholder('No client — written by a console command')
                        ->fontFamily('mono')
                        ->copyable(),

                    TextEntry::make('properties.user_agent')
                        ->label('Browser or command')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            /*
             * Anything an entry carried that is neither the change nor the
             * request context — the guard a sign-in used, whether the account
             * existed. Hidden when there is none rather than showing an empty
             * panel.
             */
            Section::make('Other details')
                ->visible(fn (Activity $record): bool => self::extra($record) !== [])
                ->schema([
                    KeyValueEntry::make('extra')
                        ->label('')
                        ->state(fn (Activity $record): array => self::extra($record))
                        ->keyLabel('Detail')
                        ->valueLabel('Value'),
                ]),
        ]);
    }

    protected static function hasChange(Activity $record): bool
    {
        foreach (self::CHANGE_KEYS as $key) {
            if (filled($record->properties[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    protected static function extra(Activity $record): array
    {
        return collect($record->properties ?? [])
            ->except(array_merge(self::CONTEXT_KEYS, self::CHANGE_KEYS))
            ->map(fn ($value) => match (true) {
                is_bool($value) => $value ? 'Yes' : 'No',
                is_array($value) => json_encode($value),
                default => (string) $value,
            })
            ->all();
    }
}
