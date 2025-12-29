<?php

namespace App\Notifications;

use App\Models\TeacherVersion;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class TeacherProfileApproved extends Notification
{
    public function __construct(
        public TeacherVersion $version
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Profile Update Approved')
            ->body("Your profile changes have been approved and are now live.")
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->getDatabaseMessage();
    }
}
