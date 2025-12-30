<?php

namespace App\Notifications;

use App\Filament\Resources\TeacherVersions\TeacherVersionResource;
use App\Models\TeacherVersion;
use Filament\Actions\Action;
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
        $url = TeacherVersionResource::getUrl('edit', ['record' => $this->version->id]);
        
        return FilamentNotification::make()
            ->title('Profile Update Rejected')
            ->body("Your update was rejected. Remarks: {$this->remarks}")
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}


