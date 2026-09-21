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
             * The permission decides this and nothing else. The button is on
             * every row a permitted user can see, whether or not we hold a
             * picture for that teacher.
             *
             * It used to also ask whether the photograph was on the disk. On a
             * server whose storage had not been populated that hid the button
             * on every row at once — which reads exactly like a permission that
             * was never granted, and sent the search for the cause to entirely
             * the wrong place. Whether there is a picture is now answered when
             * somebody asks for it, in words, rather than by the control
             * silently not being there.
             */
            ->visible(fn (Teacher $record): bool => auth()->user()?->can('downloadPhoto', $record) ?? false)
            ->tooltip('Download the original photograph')
            ->action(function (Teacher $record) {
                $photo = app(TeacherPhotoDownload::class)->obtain($record);

                if ($photo['ok'] !== true) {
                    static::explain($record, $photo);

                    return null;
                }

                /*
                 * Recorded against the teacher, because this is the one action
                 * on the row whose result walks out of the building. Who took a
                 * copy of somebody's photograph, and when, is not answerable
                 * from anywhere else — it shows up in that teacher's activity.
                 *
                 * Where it came from is recorded too: a run of downloads served
                 * from the old faculty site rather than from our own storage
                 * says the pictures on this server have gone missing, and that
                 * is worth being able to see afterwards.
                 */
                activity('teacher-photo-download')
                    ->causedBy(auth()->user())
                    ->on($record)
                    ->withProperties([
                        'filename' => $photo['filename'],
                        'bytes' => $photo['size'],
                        'source' => $photo['source'],
                    ])
                    ->event('download')
                    ->log('downloaded teacher photograph');

                $response = response()->download($photo['path'], $photo['filename']);

                // A picture fetched from the old site is a copy made for this
                // one download; the stored one is the record itself and stays.
                return $photo['temporary'] ? $response->deleteFileAfterSend(true) : $response;
            });
    }

    /**
     * Says why there is no photograph, in the words that point at the fix.
     *
     * The three cases are genuinely different problems. Nothing on record is
     * for whoever keeps the profiles; a fetch that failed is the old faculty
     * site, which is being retired; a refused address is the outbound guard.
     * One vague "not available" for all three would send everybody to the
     * wrong person.
     *
     * @param  array{ok: false, reason: string, detail: ?string}  $photo
     */
    protected static function explain(Teacher $record, array $photo): void
    {
        $name = $record->full_name;

        [$title, $body] = match ($photo['reason']) {
            TeacherPhotoDownload::REASON_NONE => [
                'No photograph for this teacher',
                "There is no photograph on record for {$name} — nothing stored here, and nothing on the old faculty site either.",
            ],
            TeacherPhotoDownload::REASON_REFUSED => [
                'Photograph address refused',
                "The stored address for {$name}'s photograph was refused by the outbound guard, so it was not requested: " . $photo['detail'],
            ],
            default => [
                'Could not fetch the photograph',
                "We do not hold {$name}'s photograph and could not get it from the old faculty site — " . $photo['detail'] . '.',
            ],
        };

        Notification::make()
            ->warning()
            ->title($title)
            ->body($body)
            ->persistent()
            ->send();
    }
}
