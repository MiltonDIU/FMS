<?php

namespace App\Notifications;

use App\Filament\Resources\Feedback\FeedbackResource;
use App\Models\Feedback;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the team that a user wrote: new feedback, a reply, a confirmation or
 * a reopening. In the panel only — the team is in it every day.
 */
class FeedbackReceived extends Notification
{
    public function __construct(
        public Feedback $feedback,
        public string $title,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title)
            ->body($this->feedback->submitter_name . ' — ' . $this->feedback->reference() . ' '
                . Str::limit($this->feedback->subject, 90))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor($this->feedback->status === Feedback::STATUS_IN_PROGRESS && $this->feedback->reopened_count > 0 ? 'danger' : 'info')
            ->actions([
                Action::make('open')
                    ->label('Open')
                    ->url(FeedbackResource::getUrl('view', ['record' => $this->feedback]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
