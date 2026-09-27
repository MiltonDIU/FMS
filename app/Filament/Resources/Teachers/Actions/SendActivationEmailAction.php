<?php

namespace App\Filament\Resources\Teachers\Actions;

use App\Filament\Resources\Teachers\Support\TeacherEmailComposer;
use App\Models\EmailBatch;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\Teacher;
use App\Services\TeacherActivationService;
use Database\Seeders\AccountActivationTemplateSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * One click to send a teacher their activation email.
 *
 * The same thing could already be done through "Send Email" by picking the
 * Account Activation template, but that is three steps and a template list to
 * know about for the one email every migrated teacher needs. This sends the
 * stored template as it is, through the same TeacherEmailComposer path, so it
 * is recorded as a batch and obeys the same skips (already activated, no
 * address, blocked address).
 *
 * Shown only to teachers who have not finished activating. The label says
 * "Resend" once a link has gone out, and the dialog says what happened to the
 * last one, so nobody resends blind to a link that is still valid.
 */
class SendActivationEmailAction
{
    public static function make(): Action
    {
        return Action::make('send_activation_email')
            ->label(fn (Teacher $record): string => $record->activation_email_sent_at
                ? 'Resend Activation Email'
                : 'Send Activation Email')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->visible(fn (Teacher $record): bool => (auth()->user()?->can('sendTeacherEmail', $record) ?? false)
                && filled($record->user?->email)
                && ! app(TeacherActivationService::class)->isAlreadyActivated($record))
            ->modalHeading(fn (Teacher $record): string => 'Activation email for ' . $record->full_name)
            ->modalDescription(fn (Teacher $record): string => static::describe($record))
            ->modalSubmitActionLabel('Send')
            ->form([
                TextInput::make('link_validity_days')
                    ->label('Link valid for (days)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(90)
                    ->default(TeacherActivationService::DEFAULT_VALIDITY_DAYS)
                    ->required()
                    ->helperText('The link signs the teacher in once and then asks for a new password. Sending again cancels any earlier link.'),
            ])
            ->action(function (Teacher $record, array $data): void {
                abort_unless(auth()->user()?->can('sendTeacherEmail', $record), 403);

                $template = EmailTemplate::query()->where('key', AccountActivationTemplateSeeder::KEY)->first();

                if (! $template) {
                    Notification::make()
                        ->danger()
                        ->title('Activation template missing')
                        ->body('Run: php artisan db:seed --class=AccountActivationTemplateSeeder')
                        ->send();

                    return;
                }

                TeacherEmailComposer::send(
                    [$record],
                    [
                        'template_id' => $template->id,
                        'subject' => $template->subject,
                        'body' => $template->body,
                        'link_validity_days' => (int) $data['link_validity_days'],
                    ],
                    EmailBatch::SOURCE_INDIVIDUAL,
                );
            });
    }

    /**
     * Where this teacher stands, so a resend is a decision rather than a reflex.
     */
    protected static function describe(Teacher $teacher): string
    {
        $email = $teacher->user?->email;
        $lines = ['Sends the Account Activation email to ' . $email . '.'];

        if ($teacher->activation_email_sent_at) {
            $last = 'Last sent ' . $teacher->activation_email_sent_at->format('d M Y, H:i')
                . ' (' . $teacher->activation_email_sent_at->diffForHumans() . ')';

            if ($teacher->verification_token_used_at) {
                $last .= ' — link was opened, but no password has been set yet.';
            } elseif ($teacher->verification_token_expires_at?->isFuture()) {
                $last .= ' — that link is still valid until ' . $teacher->verification_token_expires_at->format('d M Y') . '.';
            } else {
                $last .= ' — that link has expired.';
            }

            $lines[] = $last;
        } else {
            $lines[] = 'No activation email has been sent to this teacher yet.';
        }

        if ($reason = EmailSuppression::skipReasonFor($email)) {
            $lines[] = 'Warning: this address is on Blocked Emails (' . $reason . '), so it will be skipped. Unblock it first if it has been corrected.';
        }

        return implode(' ', $lines);
    }
}
