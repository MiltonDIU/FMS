<?php

namespace App\Filament\Resources\Feedback\Pages;

use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Resources\Feedback\Support\FeedbackAttachments;
use App\Models\Feedback;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;

/**
 * One item, and the team's answers to it.
 *
 * Reply is the one action that matters: it carries the message, the new status
 * and the internal-note switch together, so the user is told what changed in
 * the same breath as being told why.
 */
class ViewFeedback extends ViewRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) ?? false)
                ->modalHeading('Reply to ' . $this->record->submitter_name)
                ->modalWidth('2xl')
                ->modalSubmitActionLabel('Send')
                ->fillForm(fn (): array => ['status' => $this->record->status])
                ->form([
                    Toggle::make('internal')
                        ->label('Internal note — only the team sees it')
                        ->helperText('The user is not told, and the status does not change.')
                        ->live(),

                    Select::make('status')
                        ->label('Status')
                        ->options(Feedback::STATUSES)
                        ->required()
                        ->native(false)
                        ->hidden(fn (Get $get): bool => (bool) $get('internal'))
                        ->helperText('Choose "Done — please check" when it is fixed or built: the user is asked to check it and confirm.'),

                    Textarea::make('body')
                        ->label('Message')
                        ->rows(6)
                        ->required(fn (Get $get): bool => (bool) $get('internal') || $get('status') === $this->record->status)
                        ->placeholder('What was done, or what you need from them.'),

                    FeedbackAttachments::field(),
                ])
                ->action(function (array $data): void {
                    $this->record->staffReply(
                        auth()->user(),
                        $data['body'] ?? null,
                        $data['status'] ?? null,
                        (bool) ($data['internal'] ?? false),
                        $data['attachments'] ?? [],
                    );

                    Notification::make()
                        ->success()
                        ->title(($data['internal'] ?? false) ? 'Note added' : 'Reply sent')
                        ->body(($data['internal'] ?? false) ? null : $this->record->submitter_name . ' has been notified.')
                        ->send();

                    $this->refreshFormData(['status', 'priority']);
                }),

            Action::make('priority')
                ->label('Priority')
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) ?? false)
                ->fillForm(fn (): array => ['priority' => $this->record->priority])
                ->form([
                    Select::make('priority')
                        ->label('Priority')
                        ->options(Feedback::PRIORITIES)
                        ->required()
                        ->native(false),
                ])
                // The team's own triage: not part of the conversation, and the
                // user is not told.
                ->action(function (array $data): void {
                    $this->record->forceFill(['priority' => $data['priority']])->save();
                    $this->refreshFormData(['priority']);
                }),

            DeleteAction::make(),
        ];
    }
}
