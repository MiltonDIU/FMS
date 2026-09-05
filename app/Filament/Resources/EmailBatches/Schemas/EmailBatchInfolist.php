<?php

namespace App\Filament\Resources\EmailBatches\Schemas;

use App\Models\Department;
use App\Models\EmailBatch;
use App\Models\EmailBatchRecipient;
use App\Models\EmploymentStatus;
use App\Models\Faculty;
use App\Models\JobType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One batch at the top of its own page: what was sent, and how it went.
 *
 * The numbers are laid out so that they add up in front of the reader —
 * recipients accounted for by sent, still queued, failed and skipped — because
 * the question this page exists to answer is "who did not get it", and a total
 * that does not reconcile is worse than no total at all.
 */
class EmailBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What was sent')
                ->columns(3)
                ->schema([
                    TextEntry::make('subject')
                        ->label('Subject')
                        ->columnSpan(2),

                    TextEntry::make('template_name')
                        ->label('Template')
                        ->badge()
                        ->color('gray')
                        ->placeholder('Written in the send dialog'),

                    TextEntry::make('created_at')
                        ->label('Sent')
                        ->dateTime('d M Y, H:i')
                        ->helperText(fn (EmailBatch $record): string => $record->created_at->diffForHumans()),

                    TextEntry::make('sender.name')
                        ->label('Sent by')
                        ->placeholder('Console command'),

                    TextEntry::make('source')
                        ->label('Sent from')
                        ->state(fn (EmailBatch $record): string => $record->sourceLabel()),

                    TextEntry::make('link_validity_days')
                        ->label('Activation link valid for')
                        ->visible(fn (EmailBatch $record): bool => $record->uses_activation_link)
                        ->state(fn (EmailBatch $record): string => $record->link_validity_days . ' day(s)')
                        ->helperText('Each link works once. A later batch replaces the link in an earlier one.'),

                    TextEntry::make('filters')
                        ->label('Chosen recipients')
                        ->columnSpanFull()
                        ->visible(fn (EmailBatch $record): bool => filled($record->filters))
                        ->state(fn (EmailBatch $record): string => self::describeFilters($record)),
                ]),

            Section::make('How it went')
                ->columns(3)
                ->schema([
                    TextEntry::make('total_recipients')
                        ->label('Addressed')
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('sent')
                        ->label('Reached the mail server')
                        ->badge()
                        ->color('success')
                        ->state(fn (EmailBatch $record): int => self::count($record, EmailBatchRecipient::STATUS_SENT)),

                    TextEntry::make('read')
                        ->label('Read')
                        ->badge()
                        ->color('success')
                        ->state(fn (EmailBatch $record): int => $record->recipients()->whereNotNull('opened_at')->count())
                        ->helperText('Counted when the message is opened with images on, or a link in it is clicked.'),

                    TextEntry::make('unread')
                        ->label('Sent but not read yet')
                        ->badge()
                        ->color('warning')
                        ->state(fn (EmailBatch $record): int => $record->unopenedCount())
                        ->helperText('The people a follow-up is for.'),

                    TextEntry::make('queued')
                        ->label('Still in the queue')
                        ->badge()
                        ->color('warning')
                        ->state(fn (EmailBatch $record): int => self::count($record, EmailBatchRecipient::STATUS_QUEUED))
                        ->helperText('Waiting on the queue worker. Should fall to zero on its own.'),

                    TextEntry::make('failed')
                        ->label('Failed')
                        ->badge()
                        ->color('danger')
                        ->state(fn (EmailBatch $record): int => self::count($record, EmailBatchRecipient::STATUS_FAILED))
                        ->helperText('The mail server refused it, or there was no address to send to.'),

                    TextEntry::make('skipped')
                        ->label('Skipped')
                        ->badge()
                        ->color('gray')
                        ->state(fn (EmailBatch $record): int => self::count($record, EmailBatchRecipient::STATUS_SKIPPED))
                        ->helperText('Never sent — the reason is on each row below.'),
                ]),

            Section::make('The message')
                ->collapsed()
                ->schema([
                    TextEntry::make('body')
                        ->label('')
                        ->columnSpanFull()
                        ->helperText('As it was composed. Each recipient received it with their own name and links filled in.'),
                ]),
        ]);
    }

    protected static function count(EmailBatch $record, string $status): int
    {
        return $record->recipients()->where('status', $status)->count();
    }

    /**
     * What the filter dialog was asked for, in words.
     *
     * The ids are stored, but they are of no use to a reader a month later —
     * and looking the names up now rather than storing them means a department
     * that has since been renamed shows under the name it has today, which is
     * the one the reader knows it by.
     */
    protected static function describeFilters(EmailBatch $record): string
    {
        $lookups = [
            'faculty_ids' => ['Faculties', Faculty::class],
            'department_ids' => ['Departments', Department::class],
            'employment_status_ids' => ['Employment statuses', EmploymentStatus::class],
            'job_type_ids' => ['Job types', JobType::class],
        ];

        $parts = [];

        foreach ((array) $record->filters as $key => $value) {
            if (blank($value)) {
                continue;
            }

            if ($key === 'resend_of') {
                $parts[] = 'Follow-up to batch #' . $value;

                continue;
            }

            if (! isset($lookups[$key])) {
                $parts[] = ucfirst(str_replace('_', ' ', (string) $key)) . ': '
                    . implode(', ', (array) $value);

                continue;
            }

            [$label, $model] = $lookups[$key];

            $names = $model::query()->whereIn('id', (array) $value)->pluck('name')->all();

            $parts[] = $label . ': ' . ($names === [] ? 'none found' : implode(', ', $names));
        }

        return $parts === [] ? 'Everyone the dialog matched' : implode(' · ', $parts);
    }
}
