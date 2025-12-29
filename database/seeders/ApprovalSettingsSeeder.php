<?php

namespace Database\Seeders;

use App\Models\ApprovalSetting;
use Illuminate\Database\Seeder;

class ApprovalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'section_key' => 'personal_info',
                'section_label' => 'Personal Information',
                'requires_approval' => false, // Auto-update
                'description' => 'Name, phone, email, address, date of birth, etc.',
                'fields' => ['first_name', 'last_name', 'middle_name', 'phone', 'personal_phone', 'secondary_email', 'present_address', 'permanent_address', 'date_of_birth'],
                'sort_order' => 1,
            ],
            [
                'section_key' => 'academic_info',
                'section_label' => 'Academic Information',
                'requires_approval' => true, // Needs approval
                'description' => 'Designation, department, joining date, work location',
                'fields' => ['designation_id', 'department_id', 'joining_date', 'work_location', 'office_room'],
                'sort_order' => 2,
            ],
            [
                'section_key' => 'education',
                'section_label' => 'Education Records',
                'requires_approval' => true,
                'description' => 'Degree, institution, year, CGPA',
                'fields' => [], // Entire education relation
                'sort_order' => 3,
            ],
            [
                'section_key' => 'publications',
                'section_label' => 'Publications',
                'requires_approval' => true,
                'description' => 'Research publications, papers, books',
                'fields' => [],
                'sort_order' => 4,
            ],
            [
                'section_key' => 'research_projects',
                'section_label' => 'Research Projects',
                'requires_approval' => true,
                'description' => 'Research projects and grants',
                'fields' => [],
                'sort_order' => 5,
            ],
            [
                'section_key' => 'awards',
                'section_label' => 'Awards & Honors',
                'requires_approval' => false, // Auto-update
                'description' => 'Awards, honors, and recognitions',
                'fields' => [],
                'sort_order' => 6,
            ],
            [
                'section_key' => 'memberships',
                'section_label' => 'Professional Memberships',
                'requires_approval' => false, // Auto-update
                'description' => 'Professional organization memberships',
                'fields' => [],
                'sort_order' => 7,
            ],
            [
                'section_key' => 'certifications',
                'section_label' => 'Certifications',
                'requires_approval' => false, // Auto-update
                'description' => 'Professional certifications and training',
                'fields' => [],
                'sort_order' => 8,
            ],
            [
                'section_key' => 'research_info',
                'section_label' => 'Research Information',
                'requires_approval' => true,
                'description' => 'Research interests, bio, Google Scholar, ResearchGate',
                'fields' => ['research_interest', 'bio', 'google_scholar', 'research_gate', 'orcid'],
                'sort_order' => 9,
            ],
            [
                'section_key' => 'social_links',
                'section_label' => 'Social Links',
                'requires_approval' => false, // Auto-update
                'description' => 'Social media profiles and personal website',
                'fields' => ['personal_website'],
                'sort_order' => 10,
            ],
        ];

        foreach ($sections as $section) {
            ApprovalSetting::firstOrCreate(
                ['section_key' => $section['section_key']],
                $section
            );
        }
    }
}
