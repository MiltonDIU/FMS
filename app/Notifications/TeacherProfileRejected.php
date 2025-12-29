<?php

namespace App\Notifications;

use App\Models\TeacherVersion;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class TeacherProfileRejected extends Notification
{
    public function __construct(
        public TeacherVersion $version,
        public string $remarks
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Profile Update Rejected')
            ->body("Your update was rejected. Remarks: {$this->remarks}")
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->getDatabaseMessage();
    }
}
