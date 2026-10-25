<?php

namespace App\Notifications;

use App\Filament\Pages\AssignedPublications;
use App\Models\Publication;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A teacher has been named as an author by a colleague and is asked to agree.
 *
 * In the panel only, by decision: no email. The answer is given from the
 * dashboard or the "Assigned to Me" page, which keeps the whole record.
 */
class PublicationAuthorshipRequested extends Notification
{
    public function __construct(
        public Publication $publication,
        public ?User $askedBy = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $who = $this->askedBy?->teacher?->display_name ?? $this->askedBy?->name ?? 'A colleague';

        return FilamentNotification::make()
            ->title('Confirm your authorship')
            ->body("{$who} added you as an author of “" . Str::limit($this->publication->title, 90) . '”. It will not appear on your profile until you confirm it.')
            ->icon('heroicon-o-user-plus')
            ->iconColor('warning')
            ->actions([
                Action::make('review')
                    ->label('Review')
                    ->url(AssignedPublications::getUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
