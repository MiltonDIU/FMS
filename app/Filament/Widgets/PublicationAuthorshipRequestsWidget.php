<?php

namespace App\Filament\Widgets;

use App\Models\Publication;
use App\Models\Teacher;
use App\Models\User;
use App\Support\PublicationAuthorship;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Papers a colleague has named this teacher on, waiting for their answer.
 *
 * Confirm puts the paper on their profile and in their counts; decline takes
 * them off it for good (see PublicationAuthorship). Answered here, on the
 * dashboard, by decision — there is no email.
 *
 * Shown to whoever has a teacher record and something waiting, with no
 * View: permission of its own. Deploys run no seeders, so a permission added
 * for this widget would not exist on the live server and nobody would ever
 * see the requests; and there is nothing here that is not the viewer's own.
 */
class PublicationAuthorshipRequestsWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $teacherId = static::teacherId();

        return $teacherId !== null && static::pending($teacherId)->exists();
    }

    public function table(Table $table): Table
    {
        $teacherId = static::teacherId();

        return $table
            ->heading('Publications waiting for your confirmation')
            ->description('A colleague has added you as an author. These do not appear on your profile until you confirm them.')
            ->query(
                Publication::query()
                    ->whereIn('publications.id', static::pending($teacherId ?? 0)->select('publication_id'))
                    ->addSelect([
                        'requested_by' => DB::table('publication_authors')
                            ->select('added_by')
                            ->whereColumn('publication_id', 'publications.id')
                            ->where('authorable_type', Teacher::class)
                            ->where('authorable_id', $teacherId ?? 0)
                            ->where('confirmation_status', PublicationAuthorship::PENDING)
                            ->limit(1),
                    ])
            )
            ->columns([
                TextColumn::make('title')
                    ->wrap()
                    ->description(fn (Publication $record) => collect([$record->journal_name, $record->publication_year])->filter()->implode(' · ')),
                TextColumn::make('requested_by')
                    ->label('Added by')
                    ->formatStateUsing(function ($state) {
                        $user = $state ? User::with('teacher')->find($state) : null;

                        return $user?->teacher?->full_name ?? $user?->name ?? '—';
                    }),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm your authorship')
                    ->modalDescription('The publication will appear on your profile and be counted as yours.')
                    ->action(fn (Publication $record) => $this->answer($record, true)),
                Action::make('decline')
                    ->label('Decline')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Decline this authorship')
                    ->modalDescription('You will be taken off this publication, and the colleague who added you will be told. It cannot be added to your profile again by them.')
                    ->action(fn (Publication $record) => $this->answer($record, false)),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }

    protected function answer(Publication $publication, bool $accept): void
    {
        $teacher = Auth::user()?->teacher;

        if (! $teacher || PublicationAuthorship::answer($publication, $teacher, $accept) === 0) {
            return;
        }

        Notification::make()
            ->success()
            ->title($accept ? 'Authorship confirmed' : 'Authorship declined')
            ->body($accept ? 'The publication is now on your profile.' : 'You have been taken off the publication.')
            ->send();
    }

    protected static function teacherId(): ?int
    {
        return Auth::user()?->teacher?->id;
    }

    /** The viewer's rows still waiting for an answer. */
    protected static function pending(int $teacherId): \Illuminate\Database\Query\Builder
    {
        return DB::table('publication_authors')
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $teacherId)
            ->where('confirmation_status', PublicationAuthorship::PENDING);
    }
}
