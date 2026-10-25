<?php

namespace App\Filament\Resources\MyFeedback\Pages;

use App\Filament\Resources\Feedback\Support\FeedbackAttachments;
use App\Filament\Resources\MyFeedback\MyFeedbackResource;
use App\Models\Feedback;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/**
 * The user's own item, with the answers and what they can do next.
 *
 * Once the team marks it done, the two buttons that matter are "it works" and
 * "it still does not" — the first closes it, the second sends it straight back
 * to the team.
 */
class ViewMyFeedback extends ViewRecord
{
    protected static string $resource = MyFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Yes, it works')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === Feedback::STATUS_RESOLVED)
                ->modalHeading('Confirm it is fixed')
                ->modalDescription('This closes the item. Thank you for checking.')
                ->modalSubmitActionLabel('Confirm and close')
                ->form([
                    Textarea::make('body')
                        ->label('Anything to add? (optional)')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $this->record->confirmResolved(Auth::user(), $data['body'] ?? null);

                    Notification::make()->success()->title('Thank you — closed')->send();
                }),

            Action::make('reopen')
                ->label('No, still not working')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === Feedback::STATUS_RESOLVED)
                ->modalHeading('Tell the team what is still wrong')
                ->modalSubmitActionLabel('Send back to the team')
                ->form([
                    Textarea::make('body')
                        ->label('What happens now?')
                        ->required()
                        ->rows(5),

                    FeedbackAttachments::field(),
                ])
                ->action(function (array $data): void {
                    $this->record->reopen(Auth::user(), $data['body'], $data['attachments'] ?? []);

                    Notification::make()->success()->title('Sent back to the team')->send();
                }),

            Action::make('comment')
                ->label('Add a reply')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('primary')
                ->visible(fn (): bool => ! $this->record->isFinished() && $this->record->status !== Feedback::STATUS_RESOLVED)
                ->modalSubmitActionLabel('Send')
                ->form([
                    Textarea::make('body')
                        ->label('Message')
                        ->required()
                        ->rows(5),

                    FeedbackAttachments::field(),
                ])
                ->action(function (array $data): void {
                    $this->record->submitterReply(Auth::user(), $data['body'], $data['attachments'] ?? []);

                    Notification::make()->success()->title('Reply sent')->send();
                }),
        ];
    }
}
