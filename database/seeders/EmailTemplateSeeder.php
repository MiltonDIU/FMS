<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name'    => 'Profile Verification Request',
                'key'     => 'profile_verification_request',
                'subject' => 'Action Required: Please Review & Confirm Your Profile Data',
                'body'    => "Dear {teacher_name},\n\nOur Faculty Management System is now ready. We request you to review your imported profile information, fill in any missing details, and confirm its accuracy.\n\nYour Current Profile Completion: {profile_score}\nVerification Link: {verification_link}\n\nIf you find any missing or incomplete information, you can complete it directly on your profile page.\n\nThank you for your cooperation!\nBest regards,\nAdministration",
                'variables_json' => [
                    '{teacher_name}'      => 'Full name of the faculty member',
                    '{employee_id}'       => 'Employee ID',
                    '{department}'        => 'Department name',
                    '{designation}'       => 'Designation name',
                    '{profile_score}'     => 'Current profile completion percentage',
                    '{verification_link}' => 'Unique temporary profile verification link',
                ],
                'is_active' => true,
            ],
            [
                'name'    => 'Profile Completion Reminder',
                'key'     => 'profile_completion_reminder',
                'subject' => 'Reminder: Complete Your Profile Information ({profile_score} Completed)',
                'body'    => "Dear {teacher_name},\n\nThis is a friendly reminder to complete your faculty profile on our portal.\n\nYour current completion score is {profile_score}. Completing your profile ensures accurate accreditation and institutional reporting.\n\nPlease log in and update your missing information:\n{verification_link}\n\nThank you for your prompt attention to this matter.\n\nBest regards,\nAcademic Affairs & FMS Team",
                'variables_json' => [
                    '{teacher_name}'      => 'Full name of the faculty member',
                    '{employee_id}'       => 'Employee ID',
                    '{department}'        => 'Department name',
                    '{profile_score}'     => 'Current profile completion percentage',
                    '{verification_link}' => 'Unique temporary profile verification link',
                ],
                'is_active' => true,
            ],
            [
                'name'    => 'General Announcement / Notice',
                'key'     => 'general_announcement',
                'subject' => 'Important Notice for Faculty Members - {department}',
                'body'    => "Dear {teacher_name},\n\nPlease be informed of an important update regarding your department ({department}).\n\n[Write your announcement details here]\n\nThank you,\nAdministration",
                'variables_json' => [
                    '{teacher_name}' => 'Full name of the faculty member',
                    '{employee_id}'  => 'Employee ID',
                    '{department}'   => 'Department name',
                    '{designation}'  => 'Designation name',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}
