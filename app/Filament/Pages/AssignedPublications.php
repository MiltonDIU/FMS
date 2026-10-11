<?php

namespace App\Filament\Pages;

use App\Models\PublicationAuthor;
use App\Models\Teacher;
use App\Support\PublicationAuthorship;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Every paper somebody else put this teacher on: who, when, and what they answered.
 *
 * The dashboard only shows what is still waiting, and an answered request
 * leaves it. Months later a teacher would have no way to see how a paper came
 * to be on their profile, or that they had been put on one and declined it.
 * This keeps the whole record — pending, confirmed and declined.
 *
 * Only rows with a known adder who is not the teacher themselves. Imported
 * authorships carry no adder, and a paper the teacher entered themselves is
 * not something they were assigned.
 *
 * Gated on having a teacher record, not on a Shield permission: deploys run no
 * seeders, so a new permission would not exist on the live server, and the
 * page shows nothing that is not the viewer's own.
 */
class AssignedPublications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static UnitEnum|string|null $navigationGroup = 'Publications';

    protected static ?string $navigationLabel = 'Assigned to Me';

    protected static ?string $title = 'Publications Assigned to Me';

    protected static ?string $slug = 'assigned-publications';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.assigned-publications';

    public static function canAccess(): bool
    {
        return Auth::user()?->teacher !== null;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::assignedToMe()->where('confirmation_status', PublicationAuthorship::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getSubheading(): ?string
    {
        return 'Publications other people added you to as an author, who added you and when. Pending ones are not on your profile until you confirm them.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::assignedToMe()->with(['publication', 'addedBy.teacher']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('publication.title')
                    ->label('Publication')
                    ->wrap()
                    ->searchable()
                    ->description(fn (PublicationAuthor $record) => collect([
                        $record->publication?->journal_name,
                        $record->publication?->publication_year,
                    ])->filter()->implode(' · ')),
                TextColumn::make('added_by')
                    ->label('Added by')
                    ->formatStateUsing(fn ($state, PublicationAuthor $record) => $record->addedBy?->teacher?->full_name ?? $record->addedBy?->name ?? '—'),
                TextColumn::make('author_role')
                    ->label('As')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'first' => 'First author',
                        'corresponding' => 'Corresponding author',
                        default => 'Co-author',
                    }),
                TextColumn::make('created_at')
                    ->label('Added on')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                TextColumn::make('confirmation_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        PublicationAuthorship::CONFIRMED => 'success',
                        PublicationAuthorship::DECLINED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('answered_at')
                    ->label('Answered on')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('confirmation_status')
                    ->label('Status')
                    ->options([
                        PublicationAuthorship::PENDING => 'Pending',
                        PublicationAuthorship::CONFIRMED => 'Confirmed',
                        PublicationAuthorship::DECLINED => 'Declined',
                    ]),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (PublicationAuthor $record) => $record->confirmation_status === PublicationAuthorship::PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Confirm your authorship')
                    ->modalDescription('The publication will appear on your profile and be counted as yours.')
                    ->action(fn (PublicationAuthor $record) => $this->answer($record, true)),
                Action::make('decline')
                    ->label('Decline')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PublicationAuthor $record) => $record->confirmation_status === PublicationAuthorship::PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Decline this authorship')
                    ->modalDescription('You will be taken off this publication, and the colleague who added you will be told. It cannot be added to your profile again by them.')
                    ->action(fn (PublicationAuthor $record) => $this->answer($record, false)),
            ])
            ->emptyStateHeading('Nobody has added you to a publication yet')
            ->emptyStateDescription('When a colleague adds you as an author, it is listed here with who added you and when.');
    }

    protected function answer(PublicationAuthor $record, bool $accept): void
    {
        $teacher = Auth::user()?->teacher;

        if (! $teacher || ! $record->publication
            || PublicationAuthorship::answer($record->publication, $teacher, $accept) === 0) {
            return;
        }

        Notification::make()
            ->success()
            ->title($accept ? 'Authorship confirmed' : 'Authorship declined')
            ->body($accept ? 'The publication is now on your profile.' : 'You have been taken off the publication.')
            ->send();
    }

    /** The signed-in teacher's rows that somebody else added. */
    protected static function assignedToMe(): Builder
    {
        $user = Auth::user();

        return PublicationAuthor::query()
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $user?->teacher?->id ?? 0)
            ->whereNotNull('added_by')
            ->where('added_by', '!=', $user?->id ?? 0);
    }
}
