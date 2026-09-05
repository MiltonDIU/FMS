<?php

namespace App\Notifications;

use App\Services\EmailTracking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * One composed message, ready to send.
 *
 * Deliberately not queued, although it used to be. The jobs that send it are
 * queued already, so implementing ShouldQueue here put a second job behind the
 * first and moved the actual delivery out of the job that was watching it: a
 * mail server refusing the message threw inside a job nobody was recording, and
 * the batch went on saying "queued" forever. Sending it inline means the job
 * that dispatched it sees the failure and writes it down.
 */
class GenericTemplatedNotification extends Notification
{
    use Queueable;

    /**
     * $trackToken identifies the recipient's row so the pixel and the links can
     * report back. It is optional because a message can be sent without a batch
     * behind it — a test, a one-off from a console command — and such a message
     * is simply not tracked.
     */
    public function __construct(
        public string $customSubject,
        public string $customBody,
        public ?string $trackToken = null,
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

        if ($this->trackToken) {
            // The header is read and removed by InjectEmailTracking, which adds
            // the pixel to the finished HTML. Passing the token this way keeps
            // this class unaware of how tracking is done.
            $token = $this->trackToken;

            $mail->withSymfonyMessage(function (Email $message) use ($token): void {
                $message->getHeaders()->addTextHeader(EmailTracking::HEADER, $token);
            });
        }

        return $mail;
    }
}
