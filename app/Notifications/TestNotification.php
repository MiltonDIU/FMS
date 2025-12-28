<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class TestNotification extends Notification
{
    public function __construct(
        public string $title = 'Test Notification',
        public string $body = 'This is a test notification from the database notifications system.'
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-bell')
            ->iconColor('success')
            ->getDatabaseMessage();
    }
}
