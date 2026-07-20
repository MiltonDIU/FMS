<?php

namespace Database\Seeders;

use App\Models\IntegrationMapping;
use Illuminate\Database\Seeder;

class IntegrationMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mappingConfig = [
            // Identifiers & Core Teacher/User fields
            ['source_field' => 'employee_id', 'target_model' => 'Teacher', 'target_field' => 'employee_id', 'is_identifier' => true],
            ['source_field' => 'employeeId', 'target_model' => 'Teacher', 'target_field' => 'employee_id', 'is_identifier' => true],
            ['source_field' => 'webpage', 'target_model' => 'Teacher', 'target_field' => 'webpage', 'is_identifier' => false],
            ['source_field' => 'first_name', 'target_model' => 'Teacher', 'target_field' => 'first_name', 'is_identifier' => false],
            ['source_field' => 'firstName', 'target_model' => 'Teacher', 'target_field' => 'first_name', 'is_identifier' => false],
            ['source_field' => 'middle_name', 'target_model' => 'Teacher', 'target_field' => 'middle_name', 'is_identifier' => false],
            ['source_field' => 'middleName', 'target_model' => 'Teacher', 'target_field' => 'middle_name', 'is_identifier' => false],
            ['source_field' => 'last_name', 'target_model' => 'Teacher', 'target_field' => 'last_name', 'is_identifier' => false],
            ['source_field' => 'lastName', 'target_model' => 'Teacher', 'target_field' => 'last_name', 'is_identifier' => false],
            ['source_field' => 'email', 'target_model' => 'User', 'target_field' => 'email', 'is_identifier' => false],
            ['source_field' => 'secondary_email', 'target_model' => 'Teacher', 'target_field' => 'secondary_email', 'is_identifier' => false],
            ['source_field' => 'phone', 'target_model' => 'Teacher', 'target_field' => 'phone', 'is_identifier' => false],
            ['source_field' => 'workPhone', 'target_model' => 'Teacher', 'target_field' => 'phone', 'is_identifier' => false],
            ['source_field' => 'personal_phone', 'target_model' => 'Teacher', 'target_field' => 'personal_phone', 'is_identifier' => false],
            ['source_field' => 'personalPhone', 'target_model' => 'Teacher', 'target_field' => 'personal_phone', 'is_identifier' => false],
            ['source_field' => 'extension_no', 'target_model' => 'Teacher', 'target_field' => 'extension_no', 'is_identifier' => false],
            ['source_field' => 'date_of_birth', 'target_model' => 'Teacher', 'target_field' => 'date_of_birth', 'is_identifier' => false],
            ['source_field' => 'dateOfBirth', 'target_model' => 'Teacher', 'target_field' => 'date_of_birth', 'is_identifier' => false],
            ['source_field' => 'present_address', 'target_model' => 'Teacher', 'target_field' => 'present_address', 'is_identifier' => false],
            ['source_field' => 'permanent_address', 'target_model' => 'Teacher', 'target_field' => 'permanent_address', 'is_identifier' => false],
            ['source_field' => 'joining_date', 'target_model' => 'Teacher', 'target_field' => 'joining_date', 'is_identifier' => false],
            ['source_field' => 'joinDate', 'target_model' => 'Teacher', 'target_field' => 'joining_date', 'is_identifier' => false],
            ['source_field' => 'work_location', 'target_model' => 'Teacher', 'target_field' => 'work_location', 'is_identifier' => false],
            ['source_field' => 'workLocation', 'target_model' => 'Teacher', 'target_field' => 'work_location', 'is_identifier' => false],
            ['source_field' => 'office_room', 'target_model' => 'Teacher', 'target_field' => 'office_room', 'is_identifier' => false],
            ['source_field' => 'photo', 'target_model' => 'Teacher', 'target_field' => 'photo', 'is_identifier' => false],
            ['source_field' => 'bio', 'target_model' => 'Teacher', 'target_field' => 'bio', 'is_identifier' => false],
            ['source_field' => 'research_interest', 'target_model' => 'Teacher', 'target_field' => 'research_interest', 'is_identifier' => false],
            ['source_field' => 'department_id', 'target_model' => 'Teacher', 'target_field' => 'department_id', 'is_identifier' => false],
            ['source_field' => 'department.id', 'target_model' => 'Teacher', 'target_field' => 'department_id', 'is_identifier' => false],
            ['source_field' => 'designation_id', 'target_model' => 'Teacher', 'target_field' => 'designation_id', 'is_identifier' => false],
            ['source_field' => 'designation.id', 'target_model' => 'Teacher', 'target_field' => 'designation_id', 'is_identifier' => false],

            // Educations
            ['source_field' => 'educations.institution', 'target_model' => 'Education', 'target_field' => 'institution', 'is_identifier' => false],
            ['source_field' => 'educations.major', 'target_model' => 'Education', 'target_field' => 'major', 'is_identifier' => false],
            ['source_field' => 'educations.passing_year', 'target_model' => 'Education', 'target_field' => 'passing_year', 'is_identifier' => false],
            ['source_field' => 'educations.duration', 'target_model' => 'Education', 'target_field' => 'duration', 'is_identifier' => false],
            ['source_field' => 'educations.cgpa', 'target_model' => 'Education', 'target_field' => 'cgpa', 'is_identifier' => false],
            ['source_field' => 'educations.scale', 'target_model' => 'Education', 'target_field' => 'scale', 'is_identifier' => false],
            ['source_field' => 'educations.grade', 'target_model' => 'Education', 'target_field' => 'grade', 'is_identifier' => false],
            ['source_field' => 'employeeEducationalInformations.instituteName', 'target_model' => 'Education', 'target_field' => 'institution', 'is_identifier' => false],
            ['source_field' => 'employeeEducationalInformations.majorName', 'target_model' => 'Education', 'target_field' => 'major', 'is_identifier' => false],
            ['source_field' => 'employeeEducationalInformations.passingYear', 'target_model' => 'Education', 'target_field' => 'passing_year', 'is_identifier' => false],
            ['source_field' => 'employeeEducationalInformations.duration', 'target_model' => 'Education', 'target_field' => 'duration', 'is_identifier' => false],
            ['source_field' => 'employeeEducationalInformations.cgpa', 'target_model' => 'Education', 'target_field' => 'cgpa', 'is_identifier' => false],
            ['source_field' => 'employeeEducationalInformations.scale', 'target_model' => 'Education', 'target_field' => 'scale', 'is_identifier' => false],

            // Training Experiences
            ['source_field' => 'training_experiences.title', 'target_model' => 'TrainingExperience', 'target_field' => 'title', 'is_identifier' => false],
            ['source_field' => 'training_experiences.organization', 'target_model' => 'TrainingExperience', 'target_field' => 'organization', 'is_identifier' => false],
            ['source_field' => 'training_experiences.category', 'target_model' => 'TrainingExperience', 'target_field' => 'category', 'is_identifier' => false],
            ['source_field' => 'training_experiences.duration_days', 'target_model' => 'TrainingExperience', 'target_field' => 'duration_days', 'is_identifier' => false],
            ['source_field' => 'training_experiences.completion_date', 'target_model' => 'TrainingExperience', 'target_field' => 'completion_date', 'is_identifier' => false],
            ['source_field' => 'training_experiences.year', 'target_model' => 'TrainingExperience', 'target_field' => 'year', 'is_identifier' => false],

            // Certifications
            ['source_field' => 'certifications.title', 'target_model' => 'Certification', 'target_field' => 'title', 'is_identifier' => false],
            ['source_field' => 'certifications.issuing_authority', 'target_model' => 'Certification', 'target_field' => 'issuing_authority', 'is_identifier' => false],
            ['source_field' => 'certifications.credential_id', 'target_model' => 'Certification', 'target_field' => 'credential_id', 'is_identifier' => false],

            // Skills
            ['source_field' => 'skills.name', 'target_model' => 'Skill', 'target_field' => 'name', 'is_identifier' => false],
            ['source_field' => 'skills.proficiency', 'target_model' => 'Skill', 'target_field' => 'proficiency', 'is_identifier' => false],
            ['source_field' => 'employeeSkills.skill.name', 'target_model' => 'Skill', 'target_field' => 'name', 'is_identifier' => false],
            ['source_field' => 'employeeSkills.proficiency.name', 'target_model' => 'Skill', 'target_field' => 'proficiency', 'is_identifier' => false],

            // Teaching Areas
            ['source_field' => 'teaching_areas.area', 'target_model' => 'TeachingArea', 'target_field' => 'area', 'is_identifier' => false],
            ['source_field' => 'teaching_areas.description', 'target_model' => 'TeachingArea', 'target_field' => 'description', 'is_identifier' => false],

            // Memberships
            ['source_field' => 'memberships.membership_id', 'target_model' => 'Membership', 'target_field' => 'membership_id', 'is_identifier' => false],
            ['source_field' => 'memberships.position', 'target_model' => 'Membership', 'target_field' => 'position', 'is_identifier' => false],

            // Awards
            ['source_field' => 'awards.title', 'target_model' => 'Award', 'target_field' => 'title', 'is_identifier' => false],
            ['source_field' => 'awards.awarding_body', 'target_model' => 'Award', 'target_field' => 'awarding_body', 'is_identifier' => false],

            // Job Experiences
            ['source_field' => 'job_experiences.position', 'target_model' => 'JobExperience', 'target_field' => 'position', 'is_identifier' => false],
            ['source_field' => 'job_experiences.organization', 'target_model' => 'JobExperience', 'target_field' => 'organization', 'is_identifier' => false],
            ['source_field' => 'job_experiences.department', 'target_model' => 'JobExperience', 'target_field' => 'department', 'is_identifier' => false],
            ['source_field' => 'job_experiences.start_date', 'target_model' => 'JobExperience', 'target_field' => 'start_date', 'is_identifier' => false],
            ['source_field' => 'job_experiences.end_date', 'target_model' => 'JobExperience', 'target_field' => 'end_date', 'is_identifier' => false],
            ['source_field' => 'employeeJobExperiences.companyName', 'target_model' => 'JobExperience', 'target_field' => 'organization', 'is_identifier' => false],
            ['source_field' => 'employeeJobExperiences.designation', 'target_model' => 'JobExperience', 'target_field' => 'position', 'is_identifier' => false],
            ['source_field' => 'employeeJobExperiences.startDate', 'target_model' => 'JobExperience', 'target_field' => 'start_date', 'is_identifier' => false],
            ['source_field' => 'employeeJobExperiences.endDate', 'target_model' => 'JobExperience', 'target_field' => 'end_date', 'is_identifier' => false],

            // Publications
            ['source_field' => 'publications.title', 'target_model' => 'Publication', 'target_field' => 'title', 'is_identifier' => false],
            ['source_field' => 'publications.journal_name', 'target_model' => 'Publication', 'target_field' => 'journal_name', 'is_identifier' => false],
            ['source_field' => 'publications.publication_year', 'target_model' => 'Publication', 'target_field' => 'publication_year', 'is_identifier' => false],
            ['source_field' => 'employeePublications.paperTitle', 'target_model' => 'Publication', 'target_field' => 'title', 'is_identifier' => false],
            ['source_field' => 'employeePublications.journalName', 'target_model' => 'Publication', 'target_field' => 'journal_name', 'is_identifier' => false],
            ['source_field' => 'employeePublications.publicationYear', 'target_model' => 'Publication', 'target_field' => 'publication_year', 'is_identifier' => false],

            // Social Links
            ['source_field' => 'social_links.username', 'target_model' => 'SocialLink', 'target_field' => 'username', 'is_identifier' => false],
            ['source_field' => 'social_links.url', 'target_model' => 'SocialLink', 'target_field' => 'url', 'is_identifier' => false],
            ['source_field' => 'employeeSocialMedias.name', 'target_model' => 'SocialLink', 'target_field' => 'username', 'is_identifier' => false],
            ['source_field' => 'employeeSocialMedias.socialMediaProfileUrl', 'target_model' => 'SocialLink', 'target_field' => 'url', 'is_identifier' => false],
        ];

        IntegrationMapping::updateOrCreate(
            ['slug' => 'erp_teacher_profile'],
            [
                'name' => 'ERP Teacher Profile Full Mapping',
                'api_url' => 'http://localhost:8000/api/v1/profile/mahbub',
                'api_method' => 'GET',
                'mapping_config' => $mappingConfig,
            ]
        );
    }
}
