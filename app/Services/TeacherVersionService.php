<?php

namespace App\Services;

use App\Models\ApprovalSetting;
use App\Models\Teacher;
use App\Models\TeacherVersion;
use App\Models\NotificationRouting;
use App\Notifications\TeacherProfileUpdatePending;
use Illuminate\Support\Facades\DB;

class TeacherVersionService
{
    /**
     * Map of teacher fields to their sections
     */
    private const FIELD_SECTION_MAP = [
        'personal_info' => ['first_name', 'last_name', 'middle_name', 'phone', 'personal_phone', 'secondary_email', 'present_address', 'permanent_address', 'date_of_birth', 'gender_id', 'blood_group_id', 'country_id', 'religion_id'],
        'academic_info' => ['designation_id', 'department_id', 'joining_date', 'work_location', 'office_room', 'extension_no'],
        'research_info' => ['research_interest', 'bio', 'google_scholar', 'research_gate', 'orcid'],
        'social_links' => ['personal_website'],
    ];

    /**
     * Process teacher update and determine what needs approval
     */
    public function processUpdate(Teacher $teacher, array $dirtyFields): void
    {
        // Categorize dirty fields by section
        $changedSections = $this->categorizeDirtyFields($dirtyFields);
        
        // Separate into auto-update vs approval-required
        $approvalSections = [];
        $autoUpdateSections = [];
        
        foreach ($changedSections as $section => $fields) {
            if (ApprovalSetting::requiresApproval($section)) {
                $approvalSections[$section] = $fields;
            } else {
                $autoUpdateSections[$section] = $fields;
            }
        }
        
        // If nothing requires approval, allow the update
        if (empty($approvalSections)) {
            return; // Normal update proceeds
        }
        
        // Create version for approval-required fields
        $this->createVersion($teacher, $approvalSections);
        
        // Revert approval-required fields to original values
        foreach ($approvalSections as $fields) {
            foreach ($fields as $field) {
                $teacher->$field = $teacher->getOriginal($field);
            }
        }
    }

    /**
     * Categorize dirty fields into sections
     */
    private function categorizeDirtyFields(array $fields): array
    {
        $sections = [];
        
        foreach ($fields as $field) {
            foreach (self::FIELD_SECTION_MAP as $section => $sectionFields) {
                if (in_array($field, $sectionFields)) {
                    $sections[$section][] = $field;
                    break;
                }
            }
        }
        
        return $sections;
    }

    /**
     * Create a new version for approval
     */
    private function createVersion(Teacher $teacher, array $approvalSections): TeacherVersion
    {
        $latestVersion = $teacher->versions()->latest('version_number')->first();
        $newVersionNumber = ($latestVersion?->version_number ?? 0) + 1;
        
        // Collect changed data
        $versionData = [];
        foreach ($approvalSections as $fields) {
            foreach ($fields as $field) {
                $versionData[$field] = $teacher->$field;
            }
        }
        
        // Create version
        $version = TeacherVersion::create([
            'teacher_id' => $teacher->id,
            'version_number' => $newVersionNumber,
            'data' => $versionData,
            'change_summary' => 'Updated: ' . implode(', ', array_keys($approvalSections)),
            'status' => 'pending',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);
        
        // Send notifications
        $this->sendNotifications($version, array_keys($approvalSections));
        
        return $version;
    }

    /**
     * Send notifications based on routing configuration
     */
    private function sendNotifications(TeacherVersion $version, array $sections): void
    {
        foreach ($sections as $section) {
            $recipients = NotificationRouting::getRecipientsFor('teacher_profile_update', $section);
            
            foreach ($recipients as $recipient) {
                $recipient->notify(new TeacherProfileUpdatePending($version, $sections));
            }
        }
    }

    /**
     * Approve a version and update teacher profile
     */
    public function approveVersion(TeacherVersion $version): void
    {
        DB::transaction(function () use ($version) {
            // Deactivate current active version
            TeacherVersion::where('teacher_id', $version->teacher_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            
            // Activate this version
            $version->update([
                'status' => 'approved',
                'is_active' => true,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            
            // Update Teacher model with version data
            // CRITICAL: Use withoutEvents to prevent observer from creating a new version
            Teacher::withoutEvents(function () use ($version) {
                $version->teacher->update($version->data);
                
                // Re-sync user name if needed (since observer is bypassed)
                if (isset($version->data['first_name']) || isset($version->data['last_name'])) {
                    $teacher = $version->teacher;
                    if ($teacher->user) {
                        $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
                        $teacher->user->update(['name' => $fullName]);
                    }
                }
            });
            
            // Notify the teacher
            if ($version->teacher->user) {
                $version->teacher->user->notify(new \App\Notifications\TeacherProfileApproved($version));
            }
        });
    }

    /**
     * Reject a version
     */
    public function rejectVersion(TeacherVersion $version, string $remarks): void
    {
        $version->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);
        
        // Notify the teacher
        if ($version->teacher->user) {
            $version->teacher->user->notify(new \App\Notifications\TeacherProfileRejected($version, $remarks));
        }
    }
}
