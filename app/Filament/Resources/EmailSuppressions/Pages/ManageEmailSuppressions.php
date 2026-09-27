<?php

namespace App\Filament\Resources\EmailSuppressions\Pages;

use App\Filament\Resources\EmailSuppressions\EmailSuppressionResource;
use App\Models\EmailSuppression;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailSuppressions extends ManageRecords
{
    protected static string $resource = EmailSuppressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Block an address'),

            /*
             * Bounces tend to come back in a pile after a batch goes out, so
             * they can be pasted in together — straight from the bounce
             * messages, one per line or separated by commas.
             */
            Action::make('bulk_add')
                ->label('Block several')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('create', EmailSuppression::class) ?? false)
                ->modalHeading('Block several addresses')
                ->form([
                    Textarea::make('emails')
                        ->label('Email addresses')
                        ->required()
                        ->rows(8)
                        ->helperText('One per line, or separated by commas or spaces. Anything that is not an email address is ignored and reported.'),

                    Select::make('reason')
                        ->label('Reason')
                        ->options(EmailSuppression::REASONS)
                        ->default(EmailSuppression::REASON_BOUNCED)
                        ->required(),

                    Textarea::make('note')
                        ->label('Note')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can('create', EmailSuppression::class), 403);

                    $tokens = preg_split('/[\s,;]+/', (string) $data['emails'], -1, PREG_SPLIT_NO_EMPTY);

                    $added = $existing = 0;
                    $invalid = [];

                    foreach (array_unique(array_map([EmailSuppression::class, 'normalize'], $tokens)) as $email) {
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $invalid[] = $email;

                            continue;
                        }

                        $entry = EmailSuppression::suppress($email, $data['reason'], $data['note'] ?? null);

                        $entry->wasRecentlyCreated ? $added++ : $existing++;
                    }

                    $body = "{$added} address(es) blocked.";

                    if ($existing > 0) {
                        $body .= " {$existing} were already on the list.";
                    }

                    if ($invalid !== []) {
                        $body .= ' Ignored, not an email address: ' . implode(', ', array_slice($invalid, 0, 10))
                            . (count($invalid) > 10 ? ' …' : '');
                    }

                    Notification::make()
                        ->title('Block list updated')
                        ->body($body)
                        ->status($invalid === [] ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }
}
