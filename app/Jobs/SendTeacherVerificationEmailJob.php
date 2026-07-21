<?php

namespace App\Jobs;

use App\Models\Teacher;
use App\Notifications\TeacherProfileVerificationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTeacherVerificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Teacher $teacher)
    {
    }

    public function handle(): void
    {
        try {
            // Generate verification token if not present
            if (empty($this->teacher->verification_token)) {
                $this->teacher->verification_token = Str::random(40);
            }

            $this->teacher->verification_status = 'pending_verification';
            $this->teacher->save();

            // Generate verification URL safely
            if (\Illuminate\Support\Facades\Route::has('teacher.profile.verify')) {
                $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'teacher.profile.verify',
                    now()->addDays(14),
                    ['teacher' => $this->teacher->id, 'token' => $this->teacher->verification_token]
                );
            } else {
                $verificationUrl = url("/admin/my-profile?token={$this->teacher->verification_token}");
            }

            $email = $this->teacher->email ?? $this->teacher->user?->email;

            // Log verification link and details to Laravel log
            Log::info("[SendTeacherVerificationEmailJob] Teacher #{$this->teacher->id} ({$this->teacher->full_name}) | Email: " . ($email ?? 'None') . " | Verification Link: {$verificationUrl}");

            if ($email) {
                try {
                    $this->teacher->notify(new TeacherProfileVerificationNotification($this->teacher));
                    Log::info("[SendTeacherVerificationEmailJob] Verification email successfully dispatched to {$email}");
                } catch (\Throwable $mailError) {
                    Log::warning("[SendTeacherVerificationEmailJob] Mail dispatch attempt failed for {$email} (Link is logged above): " . $mailError->getMessage());
                }
            } else {
                Log::warning("[SendTeacherVerificationEmailJob] Teacher ID: {$this->teacher->id} has no valid email address, but verification link was generated above.");
            }
        } catch (\Throwable $e) {
            Log::error("[SendTeacherVerificationEmailJob] Failed for teacher ID: {$this->teacher->id}: " . $e->getMessage());
        }
    }
}
