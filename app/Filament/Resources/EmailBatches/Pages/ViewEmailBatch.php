<?php

namespace App\Filament\Resources\EmailBatches\Pages;

use App\Filament\Resources\EmailBatches\EmailBatchResource;
use App\Filament\Resources\Teachers\Support\TeacherEmailComposer;
use App\Models\EmailBatch;
use App\Models\EmailBatchRecipient;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

/**
 * One batch, its numbers, and the one thing worth doing about them.
 *
 * Sending again makes a new batch rather than reopening this one. Two attempts
 * are two different things — different tokens, different days, possibly a
 * different set of people — and flattening them into one row would lose exactly
 * the history this screen exists to keep.
 */
class ViewEmailBatch extends ViewRecord
{
    protected static string $resource = EmailBatchResource::class;

    /** The groups worth chasing, and how each is found. */
    protected const GROUPS = [
        'unread' => 'Sent, but not read yet',
        'failed' => 'Failed to send',
        'unread_or_failed' => 'Both of the above',
        'all' => 'Everyone in this batch again',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend')
                ->label('Send again')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('bulkSendEmailToTeachers', Teacher::class) ?? false)
                ->modalHeading('Send this message again')
                ->modalDescription('The same subject and body go out to the group you choose, as a new batch. '
                    . 'An activation message mints a fresh link, which stops the earlier one working.')
                ->modalSubmitActionLabel('Send')
                ->form([
                    Radio::make('group')
                        ->label('Who should receive it again')
                        ->options(self::GROUPS)
                        ->default('unread')
                        ->required()
                        ->live()
                        ->descriptions(fn (): array => $this->groupCounts()),

                    \Filament\Forms\Components\Placeholder::make('recipient_count')
                        ->label('This will go to')
                        ->content(fn (Get $get): string => $this->targets((string) $get('group'))->count()
                            . ' teacher(s)'),
                ])
                ->action(function (array $data): void {
                    $this->resend((string) $data['group']);
                }),
        ];
    }

    /**
     * How many people each group holds, shown beside the choice rather than
     * discovered after sending.
     *
     * @return array<string,string>
     */
    protected function groupCounts(): array
    {
        $counts = [];

        foreach (array_keys(self::GROUPS) as $group) {
            $counts[$group] = $this->targets($group)->count() . ' teacher(s)';
        }

        return $counts;
    }

    /**
     * The recipient rows a group refers to.
     *
     * Rows whose teacher has since been deleted are left out: there is nobody
     * left to send to, and the original row stays in the batch as the record
     * that there once was.
     */
    protected function targets(string $group): Builder
    {
        /** @var EmailBatch $batch */
        $batch = $this->record;

        $query = $batch->recipients()->getQuery()->whereNotNull('teacher_id');

        return match ($group) {
            'failed' => $query->where('status', EmailBatchRecipient::STATUS_FAILED),
            'unread_or_failed' => $query->where(fn (Builder $q) => $q
                ->where('status', EmailBatchRecipient::STATUS_FAILED)
                ->orWhere(fn (Builder $unread) => $unread
                    ->where('status', EmailBatchRecipient::STATUS_SENT)
                    ->whereNull('opened_at'))),
            'all' => $query,
            default => $query->unopened(),
        };
    }

    protected function resend(string $group): void
    {
        /** @var EmailBatch $batch */
        $batch = $this->record;

        $teacherIds = $this->targets($group)->pluck('teacher_id')->unique()->all();

        $teachers = Teacher::query()->with('user')->whereIn('id', $teacherIds)->get();

        if ($teachers->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Nobody to send to')
                ->body('There is no one left in that group.')
                ->send();

            return;
        }

        // Straight back through the ordinary send path, so a resent activation
        // message mints its link and skips activated teachers exactly as the
        // first attempt did.
        TeacherEmailComposer::send(
            $teachers,
            [
                'subject' => $batch->subject,
                'body' => $batch->body,
                'template_id' => $batch->email_template_id,
                'link_validity_days' => $batch->link_validity_days,
                'only_pending' => $batch->uses_activation_link,
            ],
            EmailBatch::SOURCE_RESEND,
            ['resend_of' => $batch->id, 'group' => self::GROUPS[$group] ?? $group],
        );
    }
}
