<?php

namespace Database\Seeders;

use App\Models\ApprovalSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // ===== DELETE ALL EXISTING SETTINGS FIRST =====
        DB::table('approval_settings')->truncate();

        // ===== DEFINE SECTIONS BASED ON TEACHERFORM.PHP TABS =====
        $sections = [
            // Tab 1: Basic Info - Contains administrative fields, requires approval
            [
                'section_key' => 'basic_info',
                'section_label' => 'Basic Information',
                'requires_approval' => true,
                'description' => 'Photo, department, designation, employee ID, profile URL, joining date, work location, names, bio',
                'fields' => ['photo', 'department_id', 'designation_id', 'employee_id', 'webpage', 'joining_date', 'work_location', 'first_name', 'middle_name', 'last_name', 'bio'],
                'sort_order' => 1,
                'is_active' => true,
            ],
            
            // Tab 2: Contact Info
            [
                'section_key' => 'contact_info',
                'section_label' => 'Contact Information',
                'requires_approval' => true,
                'description' => 'Phone, extension, office room, email, addresses',
                'fields' => ['phone', 'personal_phone', 'extension_no', 'office_room', 'secondary_email', 'present_address', 'permanent_address'],
                'sort_order' => 2,
                'is_active' => true,
            ],
            
            // Tab 3: Personal Details
            [
                'section_key' => 'personal_details',
                'section_label' => 'Personal Details',
                'requires_approval' => true,
                'description' => 'Date of birth, gender, blood group, country, religion',
                'fields' => ['date_of_birth', 'gender_id', 'blood_group_id', 'country_id', 'religion_id'],
                'sort_order' => 3,
                'is_active' => true,
            ],
            
            // Tab 4: Academic Info
            [
                'section_key' => 'academic_info',
                'section_label' => 'Academic Information',
                'requires_approval' => true,
                'description' => 'Research interests and academic focus areas',
                'fields' => ['research_interest'],
                'sort_order' => 4,
                'is_active' => true,
            ],
            
            // Tab 5: Educations
            [
                'section_key' => 'educations',
                'section_label' => 'Education Records',
                'requires_approval' => true,
                'description' => 'Degree, institution, year, CGPA - requires verification',
                'fields' => ['educations'],
                'sort_order' => 5,
                'is_active' => true,
            ],
            
            // Tab 6: Publications
            [
                'section_key' => 'publications',
                'section_label' => 'Publications',
                'requires_approval' => true,
                'description' => 'Research publications, papers, books - requires verification',
                'fields' => ['publications'],
                'sort_order' => 6,
                'is_active' => true,
            ],
            
            // Tab 7: Job Experience
            [
                'section_key' => 'job_experiences',
                'section_label' => 'Job Experience',
                'requires_approval' => true,
                'description' => 'Previous employment history and work experience',
                'fields' => ['jobExperiences'],
                'sort_order' => 7,
                'is_active' => true,
            ],
            
            // Tab 8: Training Experience
            [
                'section_key' => 'training_experiences',
                'section_label' => 'Training Experience',
                'requires_approval' => true,
                'description' => 'Training and workshops attended',
                'fields' => ['trainingExperiences'],
                'sort_order' => 8,
                'is_active' => true,
            ],
            
            // Tab 9: Awards
            [
                'section_key' => 'awards',
                'section_label' => 'Awards & Honors',
                'requires_approval' => true,
                'description' => 'Awards, honors, and recognitions',
                'fields' => ['awards'],
                'sort_order' => 9,
                'is_active' => true,
            ],
            
            // Tab 10: Skills
            [
                'section_key' => 'skills',
                'section_label' => 'Skills',
                'requires_approval' => true,
                'description' => 'Technical and professional skills',
                'fields' => ['skills'],
                'sort_order' => 10,
                'is_active' => true,
            ],
            
            // Tab 11: Teaching Areas
            [
                'section_key' => 'teaching_areas',
                'section_label' => 'Teaching Areas',
                'requires_approval' => true,
                'description' => 'Subjects and areas of teaching',
                'fields' => ['teachingAreas'],
                'sort_order' => 11,
                'is_active' => true,
            ],
            
            // Tab 12: Memberships
            [
                'section_key' => 'memberships',
                'section_label' => 'Professional Memberships',
                'requires_approval' => true,
                'description' => 'Professional organization memberships',
                'fields' => ['memberships'],
                'sort_order' => 12,
                'is_active' => true,
            ],
            
            // Tab 13: Social Links
            [
                'section_key' => 'social_links',
                'section_label' => 'Social Links',
                'requires_approval' => true,
                'description' => 'Social media profiles and personal website',
                'fields' => ['socialLinks', 'personal_website'],
                'sort_order' => 13,
                'is_active' => true,
            ],
            
            // Tab 14: Documents
            [
                'section_key' => 'documents',
                'section_label' => 'Documents',
                'requires_approval' => true,
                'description' => 'Documents and certificates uploads',
                'fields' => ['documents'],
                'sort_order' => 14,
                'is_active' => true,
            ],
            
            // Tab 15: Settings
            [
                'section_key' => 'settings',
                'section_label' => 'Profile Settings',
                'requires_approval' => true,
                'description' => 'Profile status, employment status, visibility settings',
                'fields' => ['profile_status', 'employment_status', 'is_public', 'is_active', 'is_archived', 'sort_order'],
                'sort_order' => 15,
                'is_active' => true,
            ],
        ];

        // Insert all sections
        foreach ($sections as $section) {
            ApprovalSetting::create($section);
        }
    }
}
