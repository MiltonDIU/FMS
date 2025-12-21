<?php

namespace App\Observers;

use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\TeacherWelcomeMail;

class TeacherObserver
{
    /**
     * Handle the Teacher "creating" event.
     * Auto-create User account from Teacher data.
     */
    public function creating(Teacher $teacher): void
    {
        // Only create user if user_id is not set
        if (empty($teacher->user_id)) {
            // Build name from teacher fields
            $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
            
            // Get email from session (set by CreateTeacher page)
            $email = session('teacher_creation_email');
            
            // For seeder, fallback to secondary_email or generate
            if (empty($email) && app()->runningInConsole()) {
                $email = $teacher->secondary_email 
                    ?? strtolower(Str::slug($fullName, '.')) . '@diu.edu.bd';
            }
            
            if (empty($email)) {
                throw new \Exception('Email address is required for creating teacher account.');
            }
            
            // Check if user with this email exists
            $existingUser = User::where('email', $email)->first();
            
            if ($existingUser) {
                // Link to existing user
                $teacher->user_id = $existingUser->id;
            } else {
                // Get default password from settings
                $defaultPassword = Setting::get('teacher_default_password', 'pass@123456');
                
                // Create new user
                $user = User::create([
                    'name' => $fullName,
                    'email' => $email,
                    'password' => Hash::make($defaultPassword),
                    'is_active' => true,
                ]);
                
                // Assign teacher role
                $user->assignRole('teacher');
                
                $teacher->user_id = $user->id;
                
                // Send welcome email if enabled (skip during seeding/console)
                $shouldSendEmail = Setting::get('teacher_send_welcome_email', false);
                $isManualEntry = !app()->runningInConsole();
                
                if ($shouldSendEmail && $isManualEntry) {
                    try {
                        Mail::to($email)->queue(new TeacherWelcomeMail($user, $defaultPassword));
                    } catch (\Exception $e) {
                        // Log error but don't fail teacher creation
                        \Log::error('Failed to send teacher welcome email: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Handle the Teacher "updated" event.
     * Sync user name when teacher name changes.
     */
    public function updated(Teacher $teacher): void
    {
        if ($teacher->isDirty(['first_name', 'middle_name', 'last_name']) && $teacher->user) {
            $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
            $teacher->user->update(['name' => $fullName]);
        }
    }
}
