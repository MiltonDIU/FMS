<?php

declare(strict_types=1);

namespace App\Filament\Resources\Teachers\Actions;

use App\Models\Teacher;
use App\Services\TeacherPhotoDownload;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Downloads one teacher's photograph from their row.
 *
 * Held behind its own permission rather than Update:Teacher or View:Teacher. A
 * photograph is the one thing on a profile that leaves this system as a file
 * nothing can recall, and being able to read a directory page is not the same
 * as being trusted to take the picture off it. So the ability is separate and
 * starts out granted to nobody but super_admin.
 *
 * Separate does not mean locked. It is an ordinary policy ability with an
 * ordinary permission behind it, listed in config/filament-shield under
 * custom_permissions — so any role can be handed DownloadPhoto:Teacher from the
 * roles screen later without a line of code changing. That is the whole reason
 * it is a permission and not a `hasRole('super_admin')` test here.
 *
 * @see \App\Policies\TeacherPolicy::downloadPhoto()
 */
class DownloadPhotoAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'download_photo';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Download Photo')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            /*
             * Two conditions, and both have to be here rather than one of them
             * living in the action. Without the permission it should not be
             * offered at all; with the permission but no photograph on file it
             * would be a button whose only possible outcome is an apology.
             */
            ->visible(fn (Teacher $record): bool => (auth()->user()?->can('downloadPhoto', $record) ?? false)
                && app(TeacherPhotoDownload::class)->exists($record))
            ->tooltip(function (Teacher $record): ?string {
                $photo = app(TeacherPhotoDownload::class)->resolve($record);

                return $photo === null
                    ? null
                    : 'Download the original photograph (' . TeacherPhotoDownload::formatBytes($photo['size']) . ')';
            })
            ->action(function (Teacher $record) {
                $photo = app(TeacherPhotoDownload::class)->resolve($record);

                /*
                 * visible() has already checked this, but the row was rendered
                 * some time ago and the file could have gone since. A missing
                 * photograph is a notification, not a stack trace.
                 */
                if ($photo === null) {
                    Notification::make()
                        ->warning()
                        ->title('No photograph on file')
                        ->body("There is no photograph stored for {$record->full_name}.")
                        ->send();

                    return null;
                }

                /*
                 * Recorded against the teacher, because this is the one action
                 * on the row whose result walks out of the building. Who took a
                 * copy of somebody's photograph, and when, is not answerable
                 * from anywhere else — it shows up in that teacher's activity.
                 */
                activity('teacher-photo-download')
                    ->causedBy(auth()->user())
                    ->on($record)
                    ->withProperties([
                        'filename' => $photo['filename'],
                        'bytes' => $photo['size'],
                    ])
                    ->event('download')
                    ->log('downloaded teacher photograph');

                // The stored file is sent as it is — nothing is copied or
                // staged, so there is no temporary left behind to clean up.
                return response()->download($photo['path'], $photo['filename']);
            });
    }
}
