<?php

namespace App\Notifications;

use App\Filament\Resources\MyFeedback\MyFeedbackResource;
use App\Models\EmailSuppression;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the person who sent feedback that the team answered or moved it.
 *
 * In the panel always, and by email too while the System Settings switch is on
 * — most teachers do not sign in often enough to see a bell. Queued, so a slow
 * mail server cannot hold up the person pressing Reply.
 */
class FeedbackUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Feedback $feedback,
        public FeedbackMessage $message,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (Feedback::emailsUpdates()
            && filled($notifiable->email ?? null)
            && ! EmailSuppression::isSuppressed($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->headline())
            ->body($this->feedback->reference() . ' ' . Str::limit($this->feedback->subject, 90))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor($this->feedback->status === Feedback::STATUS_RESOLVED ? 'success' : 'info')
            ->actions([
                Action::make('open')
                    ->label($this->feedback->status === Feedback::STATUS_RESOLVED ? 'Check it' : 'Open')
                    ->url($this->url())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your feedback ' . $this->feedback->reference() . ': ' . $this->headline())
            ->greeting('Dear ' . $this->feedback->submitter_name . ',')
            ->line('**' . $this->feedback->subject . '**')
            ->line('Status: ' . (Feedback::STATUSES[$this->feedback->status] ?? $this->feedback->status));

        if (filled($this->message->body)) {
            $mail->line($this->message->author_name . ' wrote:')
                ->line($this->message->body);
        }

        if ($this->feedback->status === Feedback::STATUS_RESOLVED) {
            $mail->line('Please check that it now works for you, and let us know either way.');
        }

        return $mail
            ->action($this->feedback->status === Feedback::STATUS_RESOLVED ? 'Check and confirm' : 'View the conversation', $this->url())
            ->line('Thank you for helping us improve the system.');
    }

    protected function headline(): string
    {
        if ($this->message->status_to === Feedback::STATUS_RESOLVED) {
            return 'Done — please check';
        }

        if ($this->message->status_to !== null) {
            return 'Status changed to ' . (Feedback::STATUSES[$this->message->status_to] ?? $this->message->status_to);
        }

        return 'New reply from the team';
    }

    protected function url(): string
    {
        return MyFeedbackResource::getUrl('view', ['record' => $this->feedback]);
    }
}
