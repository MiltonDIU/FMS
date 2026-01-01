<?php

namespace App\Notifications;

use App\Filament\Resources\TeacherVersions\TeacherVersionResource;
use App\Models\TeacherVersion;
use Filament\Actions\Action;
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
        $url = TeacherVersionResource::getUrl('edit', ['record' => $this->version->id]);
        
        $actions = [];

        if (method_exists($notifiable, 'can') && $notifiable->can('update', $this->version)) {
            $actions[] = Action::make('view')
                ->label('Review Changes')
                ->url($url)
                ->markAsRead();
        }

        return FilamentNotification::make()
            ->title('Teacher Profile Update Pending Approval')
            ->body("{$this->version->teacher->first_name} {$this->version->teacher->last_name} updated: " . implode(', ', $this->sections))
            ->icon('heroicon-o-user-circle')
            ->iconColor('warning')
            ->actions($actions)
            ->getDatabaseMessage();
    }
}


