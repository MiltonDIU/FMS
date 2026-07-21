<?php

namespace App\Jobs;

use App\Models\EmailTemplate;
use App\Models\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendCustomTemplatedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Teacher $teacher,
        public string $subject,
        public string $body
    ) {
    }

    public function handle(): void
    {
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
                    // Send using generic notification wrapper or Mailable
                    \Illuminate\Support\Facades\Notification::route('mail', $email)
                        ->notify(new \App\Notifications\GenericTemplatedNotification($finalSubject, $finalBody));

                    Log::info("[SendCustomTemplatedEmailJob] Email successfully dispatched to {$email}");
                } catch (\Throwable $mailError) {
                    Log::warning("[SendCustomTemplatedEmailJob] Mail dispatch attempt failed for {$email}: " . $mailError->getMessage());
                }
            } else {
                Log::warning("[SendCustomTemplatedEmailJob] Teacher ID: {$this->teacher->id} has no valid email address, but email text was logged above.");
            }
        } catch (\Throwable $e) {
            Log::error("[SendCustomTemplatedEmailJob] Failed for teacher ID: {$this->teacher->id}: " . $e->getMessage());
        }
    }
}
