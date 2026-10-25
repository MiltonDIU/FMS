<?php

namespace App\Filament\Resources\Feedback\Schemas;

use App\Models\Feedback;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One item as the team sees it: who sent it and where it stands, then the
 * whole conversation including the team's internal notes.
 */
class FeedbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(fn (Feedback $record): string => $record->reference() . ' — ' . $record->subject)
                ->columns(4)
                ->schema([
                    TextEntry::make('submitter_name')
                        ->label('From')
                        ->helperText(fn (Feedback $record): ?string => collect([
                            $record->teacher?->department?->name,
                            $record->user?->email,
                        ])->filter()->implode(' · ') ?: null),

                    TextEntry::make('type')
                        ->label('Type')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (string $state): string => Feedback::TYPES[$state] ?? $state),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => Feedback::STATUSES[$state] ?? $state)
                        ->color(fn (string $state): string => Feedback::statusColour($state))
                        ->helperText(fn (Feedback $record): ?string => $record->reopened_count > 0
                            ? 'Reopened ' . $record->reopened_count . ' time(s) by the user'
                            : null),

                    TextEntry::make('priority')
                        ->label('Priority')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => Feedback::PRIORITIES[$state] ?? $state)
                        ->color(fn (string $state): string => Feedback::priorityColour($state)),
                ]),

            Section::make('Conversation')
                ->schema([
                    ViewEntry::make('thread')
                        ->hiddenLabel()
                        ->view('filament.feedback.thread', ['staffView' => true]),
                ]),
        ]);
    }
}
