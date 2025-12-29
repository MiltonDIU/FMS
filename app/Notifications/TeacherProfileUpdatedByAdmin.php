<?php

namespace App\Notifications;

use App\Models\Teacher;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class TeacherProfileUpdatedByAdmin extends Notification
{
    public function __construct(
        public Teacher $teacher,
        public $updater
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Profile Updated')
            ->body("Your profile was updated by {$this->updater->name}.")
            ->icon('heroicon-o-pencil-square')
            ->iconColor('info')
            ->getDatabaseMessage();
    }
}
