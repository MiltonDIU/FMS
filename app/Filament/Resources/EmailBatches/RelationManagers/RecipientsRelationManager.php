<?php

namespace App\Filament\Resources\EmailBatches\RelationManagers;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\EmailBatchRecipient;
use App\Models\EmailSuppression;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everybody in the batch, one row each, and what became of their copy.
 *
 * Nothing here can be created, edited or deleted. These rows are written by the
 * queue, the mail server and the recipients' own mail clients; a row somebody
 * could correct by hand would stop being evidence of anything.
 */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Recipients';

    protected static ?string $recordTitleAttribute = 'teacher_name';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('teacher_name')
            ->defaultSort('teacher_name')
            ->columns([
                TextColumn::make('teacher_name')
                    ->label('Teacher')
                    ->description(fn (EmailBatchRecipient $record): string => ($record->email ?? 'No address')
                        . (EmailSuppression::isSuppressed($record->email) ? ' — blocked' : ''))
                    ->searchable(['teacher_name', 'email'])
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EmailBatchRecipient::STATUSES[$state] ?? (string) $state)
                    ->color(fn (EmailBatchRecipient $record): string => $record->statusColour())
                    ->sortable(),

                TextColumn::make('detail')
                    ->label('Detail')
                    // The two columns that explain an unhappy row, in one place:
                    // a skipped row has a reason, a failed one has what the mail
                    // server said.
                    ->state(fn (EmailBatchRecipient $record): ?string => $record->skip_reason ?? $record->error)
                    ->placeholder('—')
                    ->wrap()
                    ->limit(80)
                    ->color('gray'),

                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('opened_at')
                    ->label('First read')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Not yet')
                    ->description(fn (EmailBatchRecipient $record): ?string => $record->open_count > 1
                        ? $record->open_count . ' opens'
                        : null)
                    ->sortable(),

                TextColumn::make('clicked_at')
                    ->label('Clicked a link')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EmailBatchRecipient::STATUSES),

                TernaryFilter::make('opened')
                    ->label('Read')
                    ->placeholder('Everyone')
                    ->trueLabel('Has read it')
                    ->falseLabel('Has not read it')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('opened_at'),
                        false: fn (Builder $query) => $query->whereNull('opened_at'),
                        blank: fn (Builder $query) => $query,
                    ),

                Filter::make('still_waiting')
                    ->label('Sent, but still unread')
                    ->query(fn (Builder $query): Builder => $query->unopened()),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('open_teacher')
                    ->label('Open profile')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (EmailBatchRecipient $record): ?string => $record->teacher_id
                        ? TeacherResource::getUrl('view', ['record' => $record->teacher_id])
                        : null)
                    ->visible(fn (EmailBatchRecipient $record): bool => $record->teacher_id !== null),

                /*
                 * Bounces come back to the sender's mailbox, not to the system,
                 * so this is where somebody reading them records one. The row
                 * itself is not changed — it stays the record of what the mail
                 * server said at the time — the address goes on the block list.
                 */
                Action::make('mark_bounced')
                    ->label('Mark as bounced')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (EmailBatchRecipient $record): bool => filled($record->email)
                        && (auth()->user()?->can('create', EmailSuppression::class) ?? false)
                        && ! EmailSuppression::isSuppressed($record->email))
                    ->modalHeading(fn (EmailBatchRecipient $record): string => 'Block ' . $record->email . '?')
                    ->modalDescription('No email will be sent to this address again until it is unblocked under Blocked Emails.')
                    ->form(static::blockForm())
                    ->action(function (EmailBatchRecipient $record, array $data): void {
                        EmailSuppression::suppress($record->email, $data['reason'], $data['note'] ?? null, $record);

                        Notification::make()
                            ->success()
                            ->title('Address blocked')
                            ->body($record->email . ' will be skipped from now on.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('mark_bounced_bulk')
                    ->label('Mark selected as bounced')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (): bool => auth()->user()?->can('create', EmailSuppression::class) ?? false)
                    ->modalHeading('Block the selected addresses?')
                    ->modalDescription('No email will be sent to these addresses again until they are unblocked under Blocked Emails.')
                    ->form(static::blockForm())
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data): void {
                        abort_unless(auth()->user()?->can('create', EmailSuppression::class), 403);

                        $added = 0;

                        foreach ($records as $record) {
                            if (blank($record->email)) {
                                continue;
                            }

                            $added += EmailSuppression::suppress($record->email, $data['reason'], $data['note'] ?? null, $record)
                                ->wasRecentlyCreated ? 1 : 0;
                        }

                        Notification::make()
                            ->success()
                            ->title('Addresses blocked')
                            ->body("{$added} address(es) added to the block list.")
                            ->send();
                    }),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected static function blockForm(): array
    {
        return [
            Select::make('reason')
                ->label('Reason')
                ->options(EmailSuppression::REASONS)
                ->default(EmailSuppression::REASON_BOUNCED)
                ->required(),

            Textarea::make('note')
                ->label('Note')
                ->rows(2)
                ->placeholder('e.g. "Mailbox does not exist" from the bounce message'),
        ];
    }
}
