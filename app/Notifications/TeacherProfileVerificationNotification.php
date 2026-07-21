<?php

namespace App\Notifications;

use App\Models\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TeacherProfileVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Teacher $teacher)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // Generate temporary signed URL valid for 14 days
        $verificationUrl = URL::temporarySignedRoute(
            'teacher.profile.verify',
            now()->addDays(14),
            ['teacher' => $this->teacher->id, 'token' => $this->teacher->verification_token]
        );

        return (new MailMessage)
            ->subject('Action Required: Please Review & Confirm Your Profile Data')
            ->greeting('Dear ' . $this->teacher->full_name . ',')
            ->line('Our Faculty Management System is now ready. We request you to review your imported profile information, fill in any missing details, and confirm its accuracy.')
            ->action('Review & Confirm Profile', $verificationUrl)
            ->line('If you find any missing or incomplete information, you can complete it directly on your profile page.')
            ->line('Thank you for your cooperation!');
    }
}
