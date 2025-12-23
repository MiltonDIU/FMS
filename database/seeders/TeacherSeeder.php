<?php

namespace Database\Seeders;

use App\Models\Award;
use App\Models\Certification;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Education;
use App\Models\JobExperience;
use App\Models\Membership;
use App\Models\Publication;
use App\Models\ResearchProject;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Models\Teacher;
use App\Models\TeacherVersion;
use App\Models\TeachingArea;
use App\Models\TrainingExperience;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\BloodGroup;
use App\Models\Gender;
use App\Models\Nationality;
use App\Models\Religion;
use Spatie\Permission\Models\Role;

class TeacherSeeder extends Seeder
{
    protected $faker;
    protected $departments;
    protected $designations;
    protected $teacherRole;

    /**
     * Run the database seeds.
     *
     * Usage: php artisan db:seed --class=TeacherSeeder
     *
     * To create specific number of teachers:
     * Modify the $numberOfTeachers variable below
     */
    public function run(): void
    {
        // ============================================
        // CONFIGURE NUMBER OF TEACHERS TO CREATE HERE
        // ============================================
        $numberOfTeachers = 100; // Change this number as needed (e.g., 100, 500, 1000, 5000)
        // ============================================

        $this->faker = Faker::create('en_US');

        // Get Teacher role (role_id = 4)
        $this->teacherRole = Role::find(4);
        if (!$this->teacherRole) {
            $this->command->error('Teacher role (id=4) not found. Please create roles first.');
            return;
        }

        // Cache departments and designations
        $this->departments = Department::all();
        $this->designations = Designation::all();

        if ($this->departments->isEmpty() || $this->designations->isEmpty()) {
            $this->command->error('Please run FacultySeeder, DepartmentSeeder, and DesignationSeeder first.');
            return;
        }

        // Create profile for the specific demo teacher
        $this->createSpecificTeacherProfile();

        $this->command->info("Creating {$numberOfTeachers} teacher profiles with all related data...");

        $created = 0;

        for ($i = 1; $i <= $numberOfTeachers; $i++) {
            try {
                $this->createCompleteTeacherProfile($i);
                $created++;
                if ($created % 10 === 0) {
                    $this->command->info("Created {$created} profiles...");
                }
            } catch (\Exception $e) {
                $this->command->error("Error creating teacher {$i}: " . $e->getMessage());
            }
        }

        $this->command->info("Successfully created {$created} complete teacher profiles.");
    }

    /**
     * Create profile for the default teacher user
     */
    private function createSpecificTeacherProfile(): void
    {
        $user = User::where('email', 'teacher@fms.diu.edu.bd')->first();

        if (! $user) {
            return;
        }

        // Check if teacher profile already exists
        if (Teacher::where('user_id', $user->id)->exists()) {
            return;
        }

        $department = $this->departments->first();
        $designation = $this->designations->first();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'employee_id' => '710000001',
            'webpage' => 'faculty-teacher',
            'first_name' => 'Faculty',
            'last_name' => 'Teacher',
            'phone' => '01700000000',
            'personal_phone' => '01800000000',
            'secondary_email' => 'teacher.personal@example.com',
            'date_of_birth' => '1990-01-01',
            'gender_id' => Gender::where('slug', 'male')->first()?->id ?? Gender::first()->id,
            'blood_group_id' => BloodGroup::where('name', 'A+')->first()?->id ?? BloodGroup::first()->id,
            'nationality_id' => Nationality::where('slug', 'bangladesh')->first()?->id ?? Nationality::first()->id,
            'religion_id' => Religion::where('slug', 'islam')->first()?->id ?? Religion::first()->id,
            'present_address' => 'Dhaka, Bangladesh',
            'permanent_address' => 'Dhaka, Bangladesh',
            'joining_date' => '2020-01-01',
            'work_location' => 'Main Campus',
            'office_room' => 'AB1-101',
            'bio' => 'I am a faculty teacher at DIU.',
            'research_interest' => 'Software Engineering, AI',
            'profile_status' => 'approved',
            'is_public' => true,
            'is_active' => true,
            'employment_status' => 'active',
            'is_archived' => false,
            'sort_order' => 1,
        ]);

        $this->createEducations($teacher);
        $this->createPublications($teacher);
        $this->createResearchProjects($teacher);
        $this->createSkills($teacher);

        $this->command->info("Created profile for teacher@fms.diu.edu.bd");
    }

    /**
     * Create a complete teacher profile with all related data
     */
    private function createCompleteTeacherProfile(int $index): void
    {
        $firstName = $this->faker->firstName;
        $lastName = $this->faker->lastName;
        $uniqueSuffix = $this->faker->unique()->numerify('####');
        $email = strtolower($firstName) . '.' . strtolower($lastName) . $uniqueSuffix . '@diu.edu.bd';

        // Check if email exists
        if (User::where('email', $email)->exists()) {
            return;
        }

        // 1. Create User
        $user = User::create([
            'name' => "Dr. {$firstName} {$lastName}",
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // 2. Assign Teacher role
        $user->assignRole($this->teacherRole);

        // 3. Create Teacher Profile
        $department = $this->departments->random();
        $designation = $this->designations->random();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'employee_id' => '7' . $uniqueSuffix . str_pad($index, 3, '0', STR_PAD_LEFT),
            'webpage' => strtolower($firstName) . '-' . strtolower($lastName) . '-' . $uniqueSuffix,
            'first_name' => $firstName,
            'middle_name' => $this->faker->optional(0.3)->firstName,
            'last_name' => $lastName,
            'phone' => '017' . $this->faker->numerify('########'),
            'personal_phone' => '018' . $this->faker->numerify('########'),
            'secondary_email' => $this->faker->optional(0.5)->email,
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            'gender_id' => Gender::inRandomOrder()->first()->id,
            'blood_group_id' => BloodGroup::inRandomOrder()->first()->id,
            'nationality_id' => Nationality::where('slug', 'bangladesh')->first()->id, // Mostly Bangladeshi
            'religion_id' => Religion::inRandomOrder()->first()->id,
            'present_address' => $this->faker->address,
            'permanent_address' => $this->faker->address,
            'joining_date' => $this->faker->dateTimeBetween('-20 years', '-1 year')->format('Y-m-d'),
            'work_location' => $this->faker->randomElement(['Main Campus', 'Permanent Campus', 'Uttara Campus']),
            'office_room' => 'AB' . $this->faker->numberBetween(1, 5) . '-' . $this->faker->numberBetween(101, 510),
            'bio' => $this->faker->paragraph(3),
            'research_interest' => implode(', ', $this->faker->words(5)),
            'profile_status' => $this->faker->randomElement(['draft', 'pending', 'approved']),
            'is_public' => $this->faker->boolean(70),
            'is_active' => true,
            'employment_status' => $this->faker->randomElement(['active', 'active', 'active', 'study_leave', 'on_leave', 'deputation']),
            'is_archived' => $this->faker->boolean(5), // 5% archived
            'sort_order' => $index,
        ]);

        // 4. Create Educations (2-4)
        $this->createEducations($teacher);

        // 5. Create Publications (0-10)
        $this->createPublications($teacher);

        // 6. Create Research Projects (0-3)
        $this->createResearchProjects($teacher);

        // 7. Create Training Experiences (0-5)
        $this->createTrainingExperiences($teacher);

        // 8. Create Certifications (0-4)
        $this->createCertifications($teacher);

        // 9. Create Skills (3-8)
        $this->createSkills($teacher);

        // 10. Create Teaching Areas (2-5)
        $this->createTeachingAreas($teacher);

        // 11. Create Memberships (0-3)
        $this->createMemberships($teacher);

        // 12. Create Awards (0-4)
        $this->createAwards($teacher);

        // 13. Create Job Experiences (1-5)
        $this->createJobExperiences($teacher);

        // 14. Create Social Links (0-4)
        $this->createSocialLinks($teacher);

        // 15. Create Version (snapshot of current profile)
        $this->createVersion($teacher, $user);
    }

    private function createEducations(Teacher $teacher): void
    {
        $levels = [
            ['level' => 'Doctorate', 'degree' => 'Ph.D.'],
            ['level' => 'Masters', 'degree' => 'M.Sc.'],
            ['level' => 'Bachelor', 'degree' => 'B.Sc.'],
            ['level' => 'Higher Secondary', 'degree' => 'HSC'],
            ['level' => 'Secondary', 'degree' => 'SSC'],
        ];

        $count = $this->faker->numberBetween(2, 4);
        $selectedLevels = array_slice($levels, 0, $count);

        foreach ($selectedLevels as $index => $edu) {
            Education::create([
                'teacher_id' => $teacher->id,
                'level_of_education' => $edu['level'],
                'degree' => $edu['degree'],
                'field_of_study' => $this->faker->randomElement(['Computer Science', 'Software Engineering', 'Information Technology', 'Electronics', 'Physics', 'Mathematics']),
                'institution' => $this->faker->company . ' University',
                'board' => $edu['level'] === 'Secondary' || $edu['level'] === 'Higher Secondary' ? $this->faker->randomElement(['Dhaka', 'Technical', 'Rajshahi']) : null,
                'country' => $this->faker->randomElement(['Bangladesh', 'USA', 'UK', 'Australia', 'Japan', 'Malaysia']),
                'passing_year' => $this->faker->numberBetween(1990, 2023),
                'duration' => $this->faker->randomElement(['2', '3', '4', '5']),
                'result_type' => $this->faker->randomElement(['CGPA', 'Grade', 'Division']),
                'cgpa' => $this->faker->randomFloat(2, 3.0, 4.0),
                'scale' => 4.0,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function createPublications(Teacher $teacher): void
    {
        $count = $this->faker->numberBetween(0, 10);

        for ($i = 0; $i < $count; $i++) {
            Publication::create([
                'teacher_id' => $teacher->id,
                'type' => $this->faker->randomElement(['journal', 'conference', 'book', 'book_chapter']),
                'title' => $this->faker->sentence(8),
                'authors' => $teacher->full_name . ', ' . $this->faker->name,
                'journal_name' => $this->faker->company . ' Journal',
                'publisher' => $this->faker->optional(0.5)->company,
                'indexed_by' => $this->faker->randomElement(['Scopus', 'Web of Science', 'IEEE', 'ACM', null]),
                'doi' => $this->faker->optional(0.6)->regexify('10\.[0-9]{4}/[a-z]{5}[0-9]{4}'),
                'volume' => (string) $this->faker->numberBetween(1, 50),
                'issue' => (string) $this->faker->numberBetween(1, 12),
                'pages' => $this->faker->numberBetween(1, 20) . '-' . $this->faker->numberBetween(21, 50),
                'publication_year' => $this->faker->numberBetween(2010, 2024),
                'is_international' => $this->faker->boolean(60),
                'status' => $this->faker->randomElement(['draft', 'approved']),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createResearchProjects(Teacher $teacher): void
    {
        $count = $this->faker->numberBetween(0, 3);

        for ($i = 0; $i < $count; $i++) {
            ResearchProject::create([
                'teacher_id' => $teacher->id,
                'title' => $this->faker->sentence(6),
                'description' => $this->faker->paragraph(2),
                'project_leader' => $this->faker->name,
                'funding_agency' => $this->faker->randomElement(['UGC Bangladesh', 'World Bank', 'USAID', 'DIU Research Fund', null]),
                'budget' => $this->faker->optional(0.7)->numberBetween(100000, 5000000),
                'currency' => 'BDT',
                'role' => $this->faker->randomElement(['pi', 'co_pi', 'researcher']),
                'start_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
                'end_date' => $this->faker->optional(0.5)->dateTimeBetween('now', '+2 years')?->format('Y-m-d'),
                'status' => $this->faker->randomElement(['ongoing', 'completed', 'pending']),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createTrainingExperiences(Teacher $teacher): void
    {
        $count = $this->faker->numberBetween(0, 5);

        for ($i = 0; $i < $count; $i++) {
            TrainingExperience::create([
                'teacher_id' => $teacher->id,
                'title' => $this->faker->sentence(4) . ' Training',
                'organization' => $this->faker->company,
                'category' => $this->faker->randomElement(['Technical', 'Pedagogy', 'Research Methodology', 'Leadership']),
                'duration_days' => $this->faker->numberBetween(1, 30),
                'completion_date' => $this->faker->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
                'year' => $this->faker->numberBetween(2015, 2024),
                'country' => $this->faker->randomElement(['Bangladesh', 'India', 'Malaysia', 'Singapore']),
                'is_online' => $this->faker->boolean(40),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createCertifications(Teacher $teacher): void
    {
        $count = $this->faker->numberBetween(0, 4);
        $certTypes = ['AWS Certified', 'Google Cloud', 'Microsoft Azure', 'Cisco CCNA', 'PMP', 'Scrum Master'];

        for ($i = 0; $i < $count; $i++) {
            Certification::create([
                'teacher_id' => $teacher->id,
                'title' => $this->faker->randomElement($certTypes),
                'type' => $this->faker->randomElement(['Professional', 'Technical', 'Academic']),
                'issuing_authority' => $this->faker->company,
                'issue_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
                'expiry_date' => $this->faker->optional(0.6)->dateTimeBetween('now', '+3 years')?->format('Y-m-d'),
                'credential_id' => strtoupper($this->faker->bothify('??##??##')),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createSkills(Teacher $teacher): void
    {
        $skills = [
            'Programming' => ['Python', 'Java', 'JavaScript', 'C++', 'PHP', 'C#'],
            'Framework' => ['Laravel', 'React', 'Django', 'Spring Boot', 'TensorFlow'],
            'Database' => ['MySQL', 'PostgreSQL', 'MongoDB', 'Oracle'],
            'Tools' => ['Git', 'Docker', 'AWS', 'Linux'],
        ];

        $count = $this->faker->numberBetween(3, 8);

        for ($i = 0; $i < $count; $i++) {
            $category = $this->faker->randomElement(array_keys($skills));
            Skill::create([
                'teacher_id' => $teacher->id,
                'category' => $category,
                'name' => $this->faker->randomElement($skills[$category]),
                'proficiency' => $this->faker->randomElement(['Beginner', 'Intermediate', 'Expert']),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createTeachingAreas(Teacher $teacher): void
    {
        $areas = ['Data Structures', 'Algorithms', 'Database Systems', 'Operating Systems',
                  'Computer Networks', 'Machine Learning', 'Artificial Intelligence',
                  'Web Development', 'Software Engineering', 'Cloud Computing'];

        $count = $this->faker->numberBetween(2, 5);
        $selectedAreas = $this->faker->randomElements($areas, $count);

        foreach ($selectedAreas as $index => $area) {
            TeachingArea::create([
                'teacher_id' => $teacher->id,
                'area' => $area,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function createMemberships(Teacher $teacher): void
    {
        $orgs = ['IEEE', 'ACM', 'Bangladesh Computer Society', 'ISTE', 'CSI'];
        $count = $this->faker->numberBetween(0, 3);

        for ($i = 0; $i < $count; $i++) {
            Membership::create([
                'teacher_id' => $teacher->id,
                'organization' => $this->faker->randomElement($orgs),
                'membership_type' => $this->faker->randomElement(['Student', 'Professional', 'Senior', 'Fellow']),
                'membership_id' => strtoupper($this->faker->bothify('???######')),
                'start_date' => $this->faker->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
                'status' => $this->faker->randomElement(['active', 'expired']),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createAwards(Teacher $teacher): void
    {
        $count = $this->faker->numberBetween(0, 4);

        for ($i = 0; $i < $count; $i++) {
            Award::create([
                'teacher_id' => $teacher->id,
                'title' => $this->faker->randomElement(['Best Paper Award', 'Teaching Excellence Award', 'Research Grant', 'Distinguished Faculty']),
                'awarding_body' => $this->faker->company,
                'type' => $this->faker->randomElement(['award', 'recognition', 'scholarship']),
                'date' => $this->faker->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
                'year' => $this->faker->numberBetween(2015, 2024),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createJobExperiences(Teacher $teacher): void
    {
        $count = $this->faker->numberBetween(1, 5);

        for ($i = 0; $i < $count; $i++) {
            $isCurrent = $i === 0;
            JobExperience::create([
                'teacher_id' => $teacher->id,
                'position' => $this->faker->randomElement(['Lecturer', 'Assistant Professor', 'Software Engineer', 'Research Associate']),
                'organization' => $isCurrent ? 'Daffodil International University' : $this->faker->company,
                'department' => $this->faker->randomElement(['CSE', 'IT', 'Research', 'Development']),
                'location' => $this->faker->city,
                'country' => 'Bangladesh',
                'start_date' => $this->faker->dateTimeBetween('-15 years', '-1 year')->format('Y-m-d'),
                'end_date' => $isCurrent ? null : $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'is_current' => $isCurrent,
                'responsibilities' => $this->faker->paragraph(2),
                'source' => 'manual',
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function createSocialLinks(Teacher $teacher): void
    {
        $platforms = \App\Models\SocialMediaPlatform::where('is_active', true)->get();

        if ($platforms->isEmpty()) {
             return;
        }

        $count = $this->faker->numberBetween(0, min(4, $platforms->count()));
        $selectedPlatforms = $platforms->random($count);

        foreach ($selectedPlatforms as $index => $platform) {
            $username = strtolower($teacher->first_name . $teacher->last_name);
            $url = $platform->base_url ? $platform->base_url . $username : 'https://example.com/' . $username;

            SocialLink::create([
                'teacher_id' => $teacher->id,
                'social_media_platform_id' => $platform->id,
                'username' => $username,
                'url' => $url,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function createVersion(Teacher $teacher, User $user): void
    {
        // 90% of teachers will have approved/published profiles
        $isPublished = $this->faker->boolean(90);

        // Create 1-3 versions to simulate version history
        $numberOfVersions = $this->faker->numberBetween(1, 3);

        $reviewRemarks = [
            'approved' => [
                'প্রোফাইল সম্পূর্ণ এবং সঠিক। প্রকাশের জন্য অনুমোদিত।',
                'All information verified. Profile approved for publication.',
                'Profile review complete. Approved.',
                'সকল তথ্য যাচাই করা হয়েছে। অনুমোদিত।',
                'Excellent profile. Published.',
            ],
            'rejected' => [
                'প্রোফাইল অসম্পূর্ণ। শিক্ষাগত যোগ্যতা যোগ করুন।',
                'Missing publication details. Please update.',
                'Photo quality is poor. Please upload a professional photo.',
                'Contact information incomplete.',
                'Research interests not clearly defined.',
            ],
            'pending' => [
                'প্রোফাইল রিভিউয়ের অপেক্ষায়।',
                'Awaiting registrar review.',
                'Profile submitted for approval.',
            ],
        ];

        for ($v = 1; $v <= $numberOfVersions; $v++) {
            $isLastVersion = ($v === $numberOfVersions);

            // Determine version status
            if ($isLastVersion && $isPublished) {
                $status = 'approved';
                $isActive = true;
                $teacher->update([
                    'profile_status' => 'approved',
                    'is_public' => true,
                ]);
            } elseif ($isLastVersion && !$isPublished) {
                $status = $this->faker->randomElement(['draft', 'pending', 'rejected']);
                $isActive = false;
            } else {
                // Previous versions - randomly approved or rejected
                $status = $this->faker->randomElement(['approved', 'rejected']);
                $isActive = false;
            }

            // Get appropriate review remarks
            $remark = null;
            if ($status === 'approved') {
                $remark = $this->faker->randomElement($reviewRemarks['approved']);
            } elseif ($status === 'rejected') {
                $remark = $this->faker->randomElement($reviewRemarks['rejected']);
            } elseif ($status === 'pending') {
                $remark = $this->faker->randomElement($reviewRemarks['pending']);
            }

            // Build version data with minor variations for history
            $versionData = $this->buildVersionData($teacher, $v);

            // Change summary based on version number
            $changeSummaries = [
                1 => 'Initial profile creation - প্রাথমিক প্রোফাইল তৈরি',
                2 => 'Updated education and publications - শিক্ষা ও প্রকাশনা আপডেট',
                3 => 'Added research projects and skills - গবেষণা প্রকল্প ও দক্ষতা যোগ',
            ];

            TeacherVersion::create([
                'teacher_id' => $teacher->id,
                'version_number' => $v,
                'data' => $versionData,
                'change_summary' => $changeSummaries[$v] ?? "Version {$v} update",
                'status' => $status,
                'is_active' => $isActive,
                'submitted_by' => $user->id,
                'submitted_at' => now()->subDays(($numberOfVersions - $v) * 7),
                'reviewed_by' => in_array($status, ['approved', 'rejected']) ? 1 : null,
                'reviewed_at' => in_array($status, ['approved', 'rejected']) ? now()->subDays(($numberOfVersions - $v) * 7 - 1) : null,
                'review_remarks' => $remark,
            ]);
        }
    }

    private function buildVersionData(Teacher $teacher, int $versionNumber): array
    {
        // Refresh teacher relationships
        $teacher->refresh();

        return [
            'teacher' => $teacher->toArray(),
            'educations' => $teacher->educations->toArray(),
            'publications' => $teacher->publications->toArray(),
            'research_projects' => $teacher->researchProjects->toArray(),
            'training_experiences' => $teacher->trainingExperiences->toArray(),
            'certifications' => $teacher->certifications->toArray(),
            'skills' => $teacher->skills->toArray(),
            'teaching_areas' => $teacher->teachingAreas->toArray(),
            'memberships' => $teacher->memberships->toArray(),
            'awards' => $teacher->awards->toArray(),
            'job_experiences' => $teacher->jobExperiences->toArray(),
            'social_links' => $teacher->socialLinks->toArray(),
            'version_info' => [
                'version' => $versionNumber,
                'created_at' => now()->toISOString(),
            ],
        ];
    }
}
