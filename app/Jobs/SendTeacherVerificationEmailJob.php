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

            // Send notification
            $email = $this->teacher->email ?? $this->teacher->user?->email;
            if ($email) {
                $this->teacher->notify(new TeacherProfileVerificationNotification($this->teacher));
                Log::info("[SendTeacherVerificationEmailJob] Verification email dispatched to teacher ID: {$this->teacher->id} ({$email})");
            } else {
                Log::warning("[SendTeacherVerificationEmailJob] Teacher ID: {$this->teacher->id} has no valid email address.");
            }
        } catch (\Exception $e) {
            Log::error("[SendTeacherVerificationEmailJob] Failed for teacher ID: {$this->teacher->id}: " . $e->getMessage());
        }
    }
}
