<?php

namespace App\Notifications;

use App\Filament\Resources\TeacherVersions\TeacherVersionResource;
use App\Models\Teacher;
use App\Models\TeacherVersion;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TeacherProfileUpdatedByAdmin extends Notification
{
    /**
     * $version is the record of the change, when there is one. With it the
     * notification names the sections that changed and links to the page
     * showing each one before and after, so the teacher does not have to
     * look for it in their profile history.
     */
    public function __construct(
        public Teacher $teacher,
        public $updater,
        public ?TeacherVersion $version = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $sections = collect($this->version?->changed_sections ?? [])
            ->map(fn (string $section): string => Str::headline($section))
            ->implode(', ');

        $body = "Your profile was updated by {$this->updater->name}"
            . ($sections !== '' ? ": {$sections}." : '.');

        $notification = FilamentNotification::make()
            ->title('Profile Updated')
            ->body($body)
            ->icon('heroicon-o-pencil-square')
            ->iconColor('info');

        if ($this->version) {
            $notification->actions([
                Action::make('view_changes')
                    ->label('View changes')
                    ->url(TeacherVersionResource::getUrl('view', ['record' => $this->version], panel: 'admin'))
                    ->markAsRead(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
