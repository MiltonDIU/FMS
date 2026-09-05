<?php

namespace App\Jobs;

use App\Models\EmailBatchRecipient;
use App\Models\EmailTemplate;
use App\Models\Teacher;
use App\Notifications\GenericTemplatedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers one activation email.
 *
 * The link is handed in already minted rather than generated here, so the token
 * and the record of having mailed it are written once, before anything is
 * queued. A job that never runs then leaves no live link that nobody was told
 * about.
 *
 * Unlike SendCustomTemplatedEmailJob this does not log the body: it contains a
 * link that signs the recipient in, and laravel.log is not the place for that.
 */
class SendTeacherActivationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Subject and body are passed in rather than read from the template, so the
     * wording an administrator edited in the send dialog is what actually goes
     * out. The console command passes the stored template unchanged.
     */
    public function __construct(
        public Teacher $teacher,
        public string $subject,
        public string $body,
        public string $activationLink,
        public int $validityDays,
        public ?int $recipientId = null,
    ) {
    }

    public function handle(): void
    {
        $recipient = $this->recipient();

        $email = $this->teacher->user?->email ?? $this->teacher->email;

        if (! $email) {
            $recipient?->markFailed('No email address on the teacher or their user account.');

            Log::warning('[activation] Teacher #' . $this->teacher->id . ' has no email address; skipped.');

            return;
        }

        $extra = [
            '{activation_link}' => $this->activationLink,
            '{link_validity_days}' => (string) $this->validityDays,
        ];

        $subject = EmailTemplate::replacePlaceholders($this->subject, $this->teacher, $extra);
        $body = EmailTemplate::replacePlaceholders($this->body, $this->teacher, $extra);

        try {
            Notification::route('mail', $email)
                ->notify(new GenericTemplatedNotification($subject, $body, $recipient?->track_token));

            $recipient?->markSent();

            Log::info('[activation] Sent to teacher #' . $this->teacher->id . ' at ' . $email
                . ' (valid ' . $this->validityDays . ' day(s))');
        } catch (\Throwable $e) {
            $recipient?->markFailed($e->getMessage());

            Log::error('[activation] Failed for teacher #' . $this->teacher->id . ': ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Reached when the queue gives up on the job rather than the send failing,
     * which handle() never sees.
     */
    public function failed(\Throwable $exception): void
    {
        $this->recipient()?->markFailed($exception->getMessage());
    }

    protected function recipient(): ?EmailBatchRecipient
    {
        return $this->recipientId ? EmailBatchRecipient::find($this->recipientId) : null;
    }
}
