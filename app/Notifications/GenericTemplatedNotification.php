<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenericTemplatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $customSubject,
        public string $customBody
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->customSubject);

        // Split body into lines and format cleanly
        $lines = explode("\n", $this->customBody);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                $mail->line($trimmed);
            }
        }

        return $mail;
    }
}
