<?php

namespace App\Filament\Resources\Teachers\Support;

use App\Models\EmailTemplate;
use App\Models\Teacher;
use App\Services\TeacherActivationService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The shared "compose an email to teachers" form and its send handling.
 *
 * The three send dialogs — one teacher, the selected rows, and everyone matching
 * a filter — previously each carried their own copy of these fields and their
 * own dispatch loop. Keeping one copy means a template behaves identically
 * wherever it is sent from, which matters most for the activation template:
 * sending it down the ordinary path would put the literal text
 * {activation_link} in a teacher's inbox.
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
        ];
    }

    /**
     * Queue the composed message for each teacher and report what happened.
     *
     * @param  iterable<Teacher>  $teachers
     * @param  array<string,mixed>  $data
     */
    public static function send(iterable $teachers, array $data): void
    {
        $activation = app(TeacherActivationService::class);

        $subject = (string) $data['subject'];
        $body = (string) $data['body'];
        $days = (int) ($data['link_validity_days'] ?? TeacherActivationService::DEFAULT_VALIDITY_DAYS);

        $counts = ['activation' => 0, 'general' => 0, 'skipped' => 0];
        $reasons = [];

        foreach ($teachers as $teacher) {
            $result = $activation->queueFor($teacher, $subject, $body, $days);

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
                ->send();

            return;
        }

        $body = $counts['activation'] > 0
            ? "{$queued} activation email(s) queued. Each link expires in {$days} day(s) and can be used once."
            : "{$queued} email(s) queued.";

        if ($counts['skipped'] > 0) {
            $body .= ' ' . self::describeSkips($reasons);
        }

        Notification::make()
            ->success()
            ->title('Email queued')
            ->body($body)
            ->send();
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
