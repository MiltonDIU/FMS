<?php

declare(strict_types=1);

namespace App\Filament\Resources\Teachers\Actions;

use App\Models\Teacher;
use App\Services\TeacherPhotoSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Puts a teacher's photograph back on their profile, from their row.
 *
 * Nothing is downloaded. The picture is fetched from the old faculty site and
 * stored, so it shows on the website, in the CV and on every directory card —
 * which is the thing that was actually missing. A file on somebody's desktop
 * fixes nothing for anybody else.
 *
 * Held behind its own permission rather than Update:Teacher. It writes to a
 * profile and reaches out to another host to do it, which is not the same
 * thing as being able to correct a phone number.
 *
 * The permission is still called DownloadPhoto:Teacher. It was granted on the
 * live server under that name before this became a sync, and renaming it would
 * silently revoke it there — the seeder does not run on deploy, so the new name
 * would have to be granted again by hand. The name is worth changing one day,
 * with the grant migrated in the same step; it is not worth an unexplained loss
 * of access today.
 *
 * @see \App\Policies\TeacherPolicy::downloadPhoto()
 */
class SyncPhotoAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sync_photo';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Sync Photo')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            /*
             * The permission decides this and nothing else. The button is on
             * every row a permitted user can see, whether or not we hold a
             * picture for that teacher — a teacher with no photograph is
             * exactly the one somebody wants to press it for.
             */
            ->visible(fn (Teacher $record): bool => auth()->user()?->can('downloadPhoto', $record) ?? false)
            ->tooltip('Fetch this photograph from the old faculty site and put it on the profile')
            ->requiresConfirmation()
            ->modalHeading(fn (Teacher $record): string => "Sync {$record->full_name}'s photograph")
            ->modalDescription('Looks for the photograph on the old faculty site and saves it to this profile, so it appears on the website. Nothing is downloaded to your computer.')
            ->modalSubmitActionLabel('Sync')
            ->action(function (Teacher $record) {
                $result = app(TeacherPhotoSync::class)->sync($record);

                static::report($record, $result);

                /*
                 * Recorded against the teacher: this changes their profile and
                 * fetches from outside to do it. A run of these says the
                 * pictures on this server have gone missing, which is worth
                 * being able to see afterwards.
                 */
                if ($result['status'] === TeacherPhotoSync::RESTORED) {
                    activity('teacher-photo-sync')
                        ->causedBy(auth()->user())
                        ->on($record)
                        ->withProperties([
                            'bytes' => $result['bytes'] ?? null,
                            'source' => 'legacy-site',
                        ])
                        ->event('sync')
                        ->log('restored teacher photograph');
                }
            });
    }

    /**
     * Says what happened, in the words that point at the fix.
     *
     * The failures are genuinely different problems — nothing on record is for
     * whoever keeps the profiles, a failed fetch is the old faculty site, a
     * refused address is the outbound guard — and one vague "could not sync"
     * for all of them would send everybody to the wrong person.
     *
     * @param  array{status: string, bytes?: int, detail?: string}  $result
     */
    protected static function report(Teacher $record, array $result): void
    {
        $name = $record->full_name;

        $notification = match ($result['status']) {
            TeacherPhotoSync::RESTORED => Notification::make()
                ->success()
                ->title('Photograph restored')
                ->body("{$name}'s photograph was fetched from the old faculty site and saved to their profile ("
                    . TeacherPhotoSync::formatBytes($result['bytes'] ?? 0)
                    . '). It will now show wherever their picture appears.'),

            TeacherPhotoSync::ALREADY_PRESENT => Notification::make()
                ->info()
                ->title('Already on the profile')
                ->body("{$name}'s photograph is already stored here, so there was nothing to sync."),

            TeacherPhotoSync::NOTHING_TO_SYNC => Notification::make()
                ->warning()
                ->title('No photograph for this teacher')
                ->body("There is no photograph on record for {$name} — nothing stored here, and no address for one on the old faculty site.")
                ->persistent(),

            TeacherPhotoSync::REFUSED => Notification::make()
                ->warning()
                ->title('Photograph address refused')
                ->body("The stored address for {$name}'s photograph was refused by the outbound guard, so it was not requested: " . ($result['detail'] ?? ''))
                ->persistent(),

            default => Notification::make()
                ->danger()
                ->title('Could not sync the photograph')
                ->body("{$name}'s photograph could not be brought over — " . ($result['detail'] ?? 'unknown error') . '.')
                ->persistent(),
        };

        $notification->send();
    }
}
