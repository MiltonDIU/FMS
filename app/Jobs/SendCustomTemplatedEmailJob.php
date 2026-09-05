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
use Illuminate\Support\Str;

/**
 * Sends one teacher one message from any template but the activation one.
 *
 * The recipient row is passed by id rather than as a model, so that a job
 * sitting in the queue for an hour writes to the row as it stands when it runs
 * rather than to a copy of how it looked when it was dispatched.
 */
class SendCustomTemplatedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Teacher $teacher,
        public string $subject,
        public string $body,
        public ?int $recipientId = null,
    ) {
    }

    public function handle(): void
    {
        $recipient = $this->recipient();

        try {
            // Generate verification token if not present
            if (empty($this->teacher->verification_token)) {
                $this->teacher->verification_token = Str::random(40);
                $this->teacher->saveQuietly();
            }

            // Replace dynamic placeholders for this specific teacher
            $finalSubject = EmailTemplate::replacePlaceholders($this->subject, $this->teacher);
            $finalBody    = EmailTemplate::replacePlaceholders($this->body, $this->teacher);

            $email = $this->teacher->email ?? $this->teacher->user?->email;

            // Log details & output to laravel.log
            Log::info("[SendCustomTemplatedEmailJob] Teacher #{$this->teacher->id} ({$this->teacher->full_name}) | Target Email: " . ($email ?? 'None'));
            Log::info("[SendCustomTemplatedEmailJob] Subject: {$finalSubject}");
            Log::info("[SendCustomTemplatedEmailJob] Content:\n{$finalBody}");

            if ($email) {
                try {
                    // Sent here and now, not queued again behind this job, so
                    // that a refusal by the mail server is caught below and
                    // recorded against the recipient instead of disappearing.
                    Notification::route('mail', $email)
                        ->notify(new GenericTemplatedNotification(
                            $finalSubject,
                            $finalBody,
                            $recipient?->track_token,
                        ));

                    $recipient?->markSent();

                    Log::info("[SendCustomTemplatedEmailJob] Email successfully dispatched to {$email}");
                } catch (\Throwable $mailError) {
                    $recipient?->markFailed($mailError->getMessage());

                    Log::warning("[SendCustomTemplatedEmailJob] Mail dispatch attempt failed for {$email}: " . $mailError->getMessage());
                }
            } else {
                $recipient?->markFailed('No email address on the teacher or their user account.');

                Log::warning("[SendCustomTemplatedEmailJob] Teacher ID: {$this->teacher->id} has no valid email address, but email text was logged above.");
            }
        } catch (\Throwable $e) {
            $recipient?->markFailed($e->getMessage());

            Log::error("[SendCustomTemplatedEmailJob] Failed for teacher ID: {$this->teacher->id}: " . $e->getMessage());
        }
    }

    /**
     * Reached when the queue itself gives up on the job — a worker killed
     * mid-send, a timeout — which handle() never sees.
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
