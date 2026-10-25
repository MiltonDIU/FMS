<?php

namespace App\Notifications;

use App\Models\Publication;
use App\Models\Teacher;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells whoever named an author how that author answered. In the panel only.
 */
class PublicationAuthorshipAnswered extends Notification
{
    public function __construct(
        public Publication $publication,
        public Teacher $author,
        public bool $accepted,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = '“' . Str::limit($this->publication->title, 90) . '”';

        return FilamentNotification::make()
            ->title($this->accepted ? 'Authorship confirmed' : 'Authorship declined')
            ->body($this->accepted
                ? "{$this->author->display_name} confirmed being an author of {$title}."
                : "{$this->author->display_name} declined being an author of {$title}, and has been taken off it.")
            ->icon($this->accepted ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->iconColor($this->accepted ? 'success' : 'danger')
            ->getDatabaseMessage();
    }
}
