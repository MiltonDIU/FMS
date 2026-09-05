<?php

namespace App\Filament\Resources\Teachers\Support;

use App\Filament\Resources\EmailBatches\EmailBatchResource;
use App\Models\EmailBatch;
use App\Models\EmailTemplate;
use App\Models\Teacher;
use App\Services\TeacherActivationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;

/**
 * The shared "compose an email to teachers" form and its send handling.
 *
 * The three send dialogs — one teacher, the selected rows, and everyone matching
 * a filter — previously each carried their own copy of these fields and their
 * own dispatch loop. Keeping one copy means a template behaves identically
 * wherever it is sent from, which matters most for the activation template:
 * sending it down the ordinary path would put the literal text
 * {activation_link} in a teacher's inbox.
 *
 * Every send also writes a batch: the message, who sent it, and a row per
 * recipient carrying what became of their copy. Before that existed, a send left
 * nothing behind but a count in a notification, and the questions that get asked
 * afterwards — who never received it, who has read it, who is still to be
 * chased — had no answer anywhere in the system.
 */
class TeacherEmailComposer
{
    /**
     * Template chosen by default when a dialog opens.
     */
    protected const DEFAULT_TEMPLATE_KEY = 'profile_verification_request';

    /**
     * @return array<int,mixed>
     */
    public static function schema(): array
    {
        return [
            Select::make('template_id')
                ->label('Select Email Template')
                ->placeholder('Choose a template to load default subject & body...')
                ->options(fn () => EmailTemplate::query()
                    ->where('is_active', true)
                    ->pluck('name', 'id')
                    ->toArray())
                ->default(fn () => EmailTemplate::where('key', self::DEFAULT_TEMPLATE_KEY)->value('id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if (! $state) {
                        return;
                    }

                    $template = EmailTemplate::find($state);

                    if ($template) {
                        $set('subject', $template->subject);
                        $set('body', $template->body);
                    }
                })
                ->helperText('Templates are edited under Email Templates in the admin panel.'),

            TextInput::make('subject')
                ->label('Email Subject Line')
                ->required()
                ->maxLength(255)
                ->default(fn () => EmailTemplate::where('key', self::DEFAULT_TEMPLATE_KEY)->value('subject')
                    ?? 'Action Required: Please Review & Confirm Your Profile Data'),

            Textarea::make('body')
                ->label('Email Body / Message')
                ->required()
                ->rows(9)
                // Live so the validity field below can appear as soon as an
                // activation template is chosen or the placeholder is typed in.
                ->live(onBlur: true)
                ->default(fn () => EmailTemplate::where('key', self::DEFAULT_TEMPLATE_KEY)->value('body') ?? '')
                ->helperText('Placeholders: {teacher_name}, {employee_id}, {department}, {designation}, '
                    . '{profile_score}, {verification_link}, {activation_link}, {link_validity_days}'),

            TextInput::make('link_validity_days')
                ->label('Activation Link Valid For (days)')
                ->numeric()
                ->minValue(1)
                ->maxValue(90)
                ->default(TeacherActivationService::DEFAULT_VALIDITY_DAYS)
                ->required(fn (Get $get): bool => self::usesActivationLink($get))
                ->visible(fn (Get $get): bool => self::usesActivationLink($get))
                ->helperText('This message signs the teacher in, so the link expires and works only once. '
                    . 'Teachers who already set a password and verified their email are skipped.'),

            /*
             * The same rule the activation email applies on its own, offered to
             * every other template as a choice.
             *
             * Hidden for an activation message rather than shown switched on,
             * because there it is not a choice: a link that signs somebody in
             * must not reach an account that already has a password, and the
             * field above already says so. Offering a switch that cannot be
             * turned off would only invite the attempt.
             *
             * For an ordinary message it is the useful setting: it is how a
             * reminder reaches only the teachers who have still not signed in.
             */
            Toggle::make('only_pending')
                ->label('Skip teachers who have already activated their account')
                ->default(false)
                ->hidden(fn (Get $get): bool => self::usesActivationLink($get))
                ->helperText('Leave off to email everyone. Turn it on for a reminder meant only for '
                    . 'teachers who have not signed in yet. Skipped teachers still appear in the '
                    . 'delivery report, with the reason.'),
        ];
    }

    /**
     * Queue the composed message for each teacher and report what happened.
     *
     * @param  iterable<Teacher>  $teachers
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $filters  What the filtered dialog was asked for, recorded with the batch
     */
    public static function send(
        iterable $teachers,
        array $data,
        string $source = EmailBatch::SOURCE_SELECTED,
        array $filters = [],
    ): void {
        $activation = app(TeacherActivationService::class);

        $subject = (string) $data['subject'];
        $body = (string) $data['body'];
        $days = (int) ($data['link_validity_days'] ?? TeacherActivationService::DEFAULT_VALIDITY_DAYS);

        $teachers = $teachers instanceof Collection ? $teachers : collect($teachers);

        if ($teachers->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Nothing was sent')
                ->body('No teachers were selected.')
                ->send();

            return;
        }

        $usesActivationLink = $activation->needsActivationLink($subject, $body);

        $batch = EmailBatch::create([
            'subject' => $subject,
            'body' => $body,
            'email_template_id' => $data['template_id'] ?? null,
            'template_name' => EmailTemplate::find($data['template_id'] ?? null)?->name,
            'sent_by' => auth()->id(),
            'source' => $source,
            'filters' => $filters ?: null,
            'uses_activation_link' => $usesActivationLink,
            'link_validity_days' => $usesActivationLink ? $days : null,
            'total_recipients' => $teachers->count(),
        ]);

        $counts = ['activation' => 0, 'general' => 0, 'skipped' => 0];
        $reasons = [];

        foreach ($teachers as $teacher) {
            $result = $activation->queueFor(
                $teacher,
                $subject,
                $body,
                $days,
                $batch->addRecipient($teacher),
                $usesActivationLink || (bool) ($data['only_pending'] ?? false),
            );

            if (str_starts_with($result, 'skipped')) {
                $counts['skipped']++;
                $reasons[$result] = ($reasons[$result] ?? 0) + 1;

                continue;
            }

            $counts[$result]++;
        }

        $queued = $counts['activation'] + $counts['general'];

        if ($queued === 0 && $counts['skipped'] > 0) {
            Notification::make()
                ->warning()
                ->title('Nothing was sent')
                ->body(self::describeSkips($reasons))
                ->actions([self::reportAction($batch)])
                ->send();

            return;
        }

        $summary = $counts['activation'] > 0
            ? "{$queued} activation email(s) queued. Each link expires in {$days} day(s) and can be used once."
            : "{$queued} email(s) queued.";

        if ($counts['skipped'] > 0) {
            $summary .= ' ' . self::describeSkips($reasons);
        }

        Notification::make()
            ->success()
            ->title('Email queued')
            ->body($summary)
            ->actions([self::reportAction($batch)])
            ->send();
    }

    /**
     * Takes the sender straight to the report, while they still remember what
     * they sent and to whom.
     */
    protected static function reportAction(EmailBatch $batch): Action
    {
        return Action::make('view_batch')
            ->label('View delivery report')
            ->url(EmailBatchResource::getUrl('view', ['record' => $batch]))
            ->button();
    }

    protected static function usesActivationLink(Get $get): bool
    {
        return app(TeacherActivationService::class)->needsActivationLink(
            (string) $get('body'),
            (string) $get('subject'),
        );
    }

    /**
     * @param  array<string,int>  $reasons
     */
    protected static function describeSkips(array $reasons): string
    {
        $parts = [];

        foreach ($reasons as $reason => $count) {
            $parts[] = $count . ' ' . str_replace('skipped: ', '', $reason);
        }

        return 'Skipped: ' . implode(', ', $parts) . '.';
    }
}
