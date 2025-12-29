<?php

namespace App\Notifications;

use App\Models\TeacherVersion;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class TeacherProfileUpdatePending extends Notification
{
    public function __construct(
        public TeacherVersion $version,
        public array $sections
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
            ->title('Teacher Profile Update Pending Approval')
            ->body("{$this->version->teacher->first_name} {$this->version->teacher->last_name} updated: " . implode(', ', $this->sections))
            ->icon('heroicon-o-user-circle')
            ->iconColor('warning')
            ->getDatabaseMessage();
    }
}
