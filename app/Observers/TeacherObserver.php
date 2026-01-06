<?php

namespace App\Observers;

use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherVersionService;
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
     * Handle the Teacher "updating" event.
     * Process approval settings and create versions if needed.
     */
    public function updating(Teacher $teacher): void
    {
        // Check if update is being handled by Service to prevent recursion
        if (TeacherVersionService::$ignoreObserver) {
            return;
        }
        // 1. Check if third-party is updating (Admin etc.)
        if (auth()->check() && $teacher->user_id && auth()->id() !== $teacher->user_id) {
            // If someone else is updating, notify the teacher
            // We use afterCommit to ensure notification is sent only if update succeeds
            // But since Observer doesn't have afterCommit easily here without trait, we'll do it carefully
            
            // Note: If versioning is triggered, the actual update might be reverted.
            // So we should only notify if it's NOT a versioned update OR if it's an auto-update.
            // For simplicity, we'll handle this check inside processUpdate or here.
            
            // Let's defer to service if versioning is on, but if versioning OFF or Skipped, we notify here.
            // Actually, we should check if we should notify.
        }

        // Check if versioning is enabled
        if (!config('app.teacher_versioning_enabled', true)) {
            // If versioning disabled, but third-party updated, notify teacher
            $this->notifyTeacherIfThirdPartyUpdate($teacher);
            return; 
        }

        // ** CASCADING STATUS LOGIC **
        // 1. Employment Status affects is_active (based on check_active field)
        if ($teacher->isDirty('employment_status_id') && $teacher->employment_status_id) {
            $status = $teacher->employmentStatus;
            
            if ($status) {
                // Check specifically for 'retired' slug
                if ($status->slug === 'retired') {
                    $teacher->is_archived = true;
                    $teacher->is_active = false;
                    $teacher->is_public = false;
                } else {
                    // Strictly enforce is_active based on check_active
                    $teacher->is_active = $status->check_active;
                    
                    // If status makes teacher active, ensure they are not archived
                    if ($status->check_active) {
                        $teacher->is_archived = false;
                    }
                }
            }
        }

        // 2. If is_active becomes true, ensure is_archived is false
        if ($teacher->isDirty('is_active') && $teacher->is_active === true) {
            $teacher->is_archived = false;
        }

        // 3. If is_archived becomes true, ensure is_active is false AND employment status is Retired
        if ($teacher->isDirty('is_archived') && $teacher->is_archived === true) {
            $teacher->is_active = false;
            $teacher->is_public = false;
            
            // Auto-set to Retired status if available
            $retiredStatus = \App\Models\EmploymentStatus::where('slug', 'retired')->first();
            if ($retiredStatus) {
                $teacher->employment_status_id = $retiredStatus->id;
            }
        }

        // 4. If is_active becomes false, ensure is_public is false
        if ($teacher->isDirty('is_active') && $teacher->is_active === false) {
            $teacher->is_public = false;
        }

        // Skip versioning for console/seeder operations
        if (app()->runningInConsole()) {
            return;
        }

        // Get dirty fields (excluding relationship updates)
        $dirtyFields = array_keys($teacher->getDirty());
        
        if (empty($dirtyFields)) {
            return;
        }

        // Use service to process the update
        // We pass the updater ID to handle third-party logic inside service if needed
        app(TeacherVersionService::class)->processUpdate($teacher, $dirtyFields);
        
        // Also check third party update here for auto-update parts
        $this->notifyTeacherIfThirdPartyUpdate($teacher);
    }
    
    private function notifyTeacherIfThirdPartyUpdate(Teacher $teacher): void
    {
        if (auth()->check() && $teacher->user && auth()->id() !== $teacher->user_id) {
            // Prevent duplicate notifications if multiple fields update
            // Ideally should be queued or debounced, but for now direct
            try {
                $teacher->user->notify(new \App\Notifications\TeacherProfileUpdatedByAdmin($teacher, auth()->user()));
            } catch (\Exception $e) {
                // Ignore if notification fails
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
