<?php

namespace Database\Seeders;

use App\Models\Award;
use App\Models\Certification;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Education;
use App\Models\JobExperience;
use App\Models\Membership;
use App\Models\MembershipOrganization;
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
use App\Models\Country;
use App\Models\Religion;
use Spatie\Permission\Models\Role;

class TeacherSeeder extends Seeder
{
    protected $faker;
    protected $departments;
    protected $designations;
    protected $teacherRole;
    protected $countries;

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
        $numberOfTeachers = 300; // Change this number as needed (e.g., 100, 500, 1000, 5000)
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
        $this->countries = Country::all();

        if ($this->departments->isEmpty() || $this->designations->isEmpty() || $this->countries->isEmpty()) {
            $this->command->error('Please run FacultySeeder, DepartmentSeeder, DesignationSeeder, and CountrySeeder first.');
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
        $bangladesh = $this->countries->firstWhere('slug', 'bangladesh') ?? $this->countries->first();

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
            'country_id' => $bangladesh->id,
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
            'employment_status_id' => \App\Models\EmploymentStatus::where('slug', 'active')->first()->id,
            'job_type_id' => \App\Models\JobType::where('slug', 'full-time')->first()->id,
            'is_archived' => false,
            'sort_order' => 1,
        ]);

        // Manually populate department_teacher pivot table
        $teacher->departments()->attach($teacher->department_id, [
            'job_type_id' => $teacher->job_type_id,
            'sort_order' => 0,
            'assigned_by' => 1, // Default to user ID 1
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
            'password' => Hash::make('123456789'),
            'is_active' => true,
        ]);

        // 2. Assign Teacher role
        $user->assignRole($this->teacherRole);

        // 3. Create Teacher Profile
        $department = $this->departments->random();
        $designation = $this->designations->random();
        $bangladesh = $this->countries->firstWhere('slug', 'bangladesh') ?? $this->countries->first();

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
            'country_id' => $bangladesh->id, // Mostly Bangladeshi
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
            'employment_status_id' => \App\Models\EmploymentStatus::whereIn('slug', ['active', 'on-leave', 'study-leave', 'deputation'])->inRandomOrder()->first()->id,
            'job_type_id' => \App\Models\JobType::inRandomOrder()->first()->id,
            'is_archived' => $this->faker->boolean(5), // 5% archived
            'sort_order' => 0, // Will be updated after all teachers are created
        ]);

        // Calculate sort_order based on department and designation
        // Get count of teachers in same department with same or higher designation rank
        $sortOrder = Teacher::where('department_id', $teacher->department_id)
            ->join('designations', 'teachers.designation_id', '=', 'designations.id')
            ->where(function($query) use ($designation, $teacher) {
                $query->where('designations.rank', '<', $designation->rank)
                    ->orWhere(function($q) use ($designation, $teacher) {
                        $q->where('designations.rank', '=', $designation->rank)
                            ->where('teachers.id', '<', $teacher->id);
                    });
            })
            ->count() + 1;

        $teacher->update(['sort_order' => $sortOrder]);

        // Manually populate department_teacher pivot table with calculated sort_order
        $teacher->departments()->attach($teacher->department_id, [
            'job_type_id' => $teacher->job_type_id,
            'sort_order' => $sortOrder,
            'assigned_by' => 1, // Default to user ID 1
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
        // Fetch Lookups
        $degreeTypes = \App\Models\DegreeType::all()->groupBy(fn($dt) => $dt->level->name);
        $results = \App\Models\ResultType::all();

        // Check if data exists
        if ($degreeTypes->isEmpty() || $results->isEmpty()) {
            return;
        }

        // Define hierarchy preference
        // Only 20% (1 in 5) teachers should have Doctoral degree
        $hasDoctoral = $this->faker->boolean(20); // 20% chance

        $hierarchy = $hasDoctoral
            ? ['Doctoral', 'Master', 'Bachelor', 'High School']
            : ['Master', 'Bachelor', 'High School']; // Skip Doctoral for 80%

        $count = $this->faker->numberBetween(2, 4);
        $created = 0;

        foreach ($hierarchy as $levelName) {
            if ($created >= $count) break;

            $types = $degreeTypes->get($levelName) ?? collect();

            if ($types->isNotEmpty()) {
                // Pick a random degree type
                $degreeType = $types->random();

                // Pick a random result type
                $resultType = $results->random();

                // Base education data
                $educationData = [
                    'teacher_id' => $teacher->id,
                    'degree_type_id' => $degreeType->id,
                    'major' => $this->faker->randomElement([
                        'Computer Science', 'Electrical Engineering', 'Mathematics',
                        'Physics', 'Chemistry', 'Business Administration',
                        'Economics', 'English Literature', 'Civil Engineering'
                    ]),
                    'institution' => $this->faker->company . ' University',
                    'country_id' => $this->countries->random()->id,
                    'passing_year' => $this->faker->numberBetween(1990, 2023),
                    'duration' => $this->faker->randomElement(['2 years', '3 years', '4 years', '5 years']),
                    'result_type_id' => $resultType->id,
                    'sort_order' => $created + 1,
                ];

                // Add result-specific fields based on result type
                switch ($resultType->type_name) {
                    case 'CGPA':
                    case 'GPA':
                        $educationData['cgpa'] = $this->faker->randomFloat(2, 3.0, 4.0);
                        $educationData['scale'] = 4.0;
                        break;
                    case 'Percentage':
                        $educationData['marks'] = $this->faker->randomFloat(2, 60, 95);
                        break;
                    case 'Grade':
                    case 'Pass/Fail':
                        $educationData['grade'] = $this->faker->randomElement([
                            'First Class', 'Second Class', 'A+', 'A', 'B+', 'Pass'
                        ]);
                        break;
                }

                Education::create($educationData);
                $created++;
            }
        }
    }

    private function createPublications(Teacher $teacher): void
    {
        $count = $this->faker->numberBetween(1, 5);

        $types = \App\Models\PublicationType::pluck('id')->toArray();
        $linkages = \App\Models\PublicationLinkage::pluck('id')->toArray();
        $quartiles = \App\Models\PublicationQuartile::pluck('id')->toArray();
        $grants = \App\Models\GrantType::pluck('id')->toArray();
        $collabs = \App\Models\ResearchCollaboration::pluck('id')->toArray();

        if (empty($types)) return;

        // Research areas for realistic data
        $researchAreas = [
            'Artificial Intelligence', 'Machine Learning', 'Deep Learning',
            'Data Science', 'Big Data Analytics', 'IoT', 'Cyber Security',
            'Cloud Computing', 'Blockchain', 'Natural Language Processing',
            'Computer Vision', 'Robotics', 'Software Engineering',
        ];

        $keywordPool = [
            'machine learning', 'deep learning', 'neural networks', 'data mining',
            'classification', 'prediction', 'optimization', 'algorithm',
            'model', 'framework', 'system', 'analysis', 'performance',
        ];

        // Get admin user for incentive approval
        $adminUser = \App\Models\User::where('email', 'like', '%admin%')->first() ?? \App\Models\User::first();

        for ($i = 0; $i < $count; $i++) {
            // Generate realistic abstract
            $abstractTopics = ['This study investigates', 'This paper presents', 'We propose', 'This research explores'];
            $abstract = $this->faker->randomElement($abstractTopics) . ' ' . $this->faker->paragraph(4);

            $year = $this->faker->numberBetween(2020, 2026);

            $pub = Publication::create([
                'faculty_id' => $teacher->department->faculty_id,
                'department_id' => $teacher->department_id,
                'publication_type_id' => $this->faker->randomElement($types),
                'publication_linkage_id' => !empty($linkages) ? $this->faker->randomElement($linkages) : null,
                'publication_quartile_id' => !empty($quartiles) ? $this->faker->randomElement($quartiles) : null,
                'grant_type_id' => !empty($grants) ? $this->faker->randomElement($grants) : null,
                'research_collaboration_id' => !empty($collabs) ? $this->faker->randomElement($collabs) : null,

                'title' => $this->faker->sentence(rand(8, 12)),
                'journal_name' => $this->faker->randomElement([
                    'IEEE Transactions on Neural Networks', 'Nature Scientific Reports',
                    'Springer Journal of CS', 'Elsevier Data Science', 'ACM Computing Surveys',
                ]),
                'journal_link' => $this->faker->url,
                'publication_date' => $this->faker->dateTimeBetween("{$year}-01-01", "{$year}-12-31"),
                'publication_year' => $year,

                // Filled fields
                'research_area' => $this->faker->randomElement($researchAreas),
                'abstract' => $abstract,
                'keywords' => implode(', ', $this->faker->randomElements($keywordPool, rand(4, 7))),
                'h_index' => rand(5, 50),
                'citescore' => rand(10, 100) / 10,
                'impact_factor' => rand(5, 80) / 10,
                'student_involvement' => $this->faker->boolean(40),
                'is_featured' => $this->faker->boolean(20),
                'status' => 'approved',
                'sort_order' => $i + 1,
            ]);

            // Build authors collection with weights
            $authors = collect();

            // Main teacher's role (50% First, 30% Co, 20% Corresponding)
            $mainRole = $this->faker->randomElement(array_merge(
                array_fill(0, 5, 'first'),
                array_fill(0, 3, 'co_author'),
                array_fill(0, 2, 'corresponding')
            ));

            $authors->push([
                'teacher' => $teacher,
                'role' => $mainRole,
                'sort_order' => $mainRole === 'first' ? 0 : 1,
                'share_weight' => $mainRole === 'first' ? 40 : ($mainRole === 'corresponding' ? 30 : 15),
            ]);

            // Add collaborators
            $collaborators = Teacher::where('id', '!=', $teacher->id)
                ->inRandomOrder()
                ->take($this->faker->numberBetween(1, 5))
                ->get();

            $sortOrder = 2;
            foreach ($collaborators as $collaborator) {
                $collabRole = 'co_author';

                // Ensure we have a first author
                if ($mainRole !== 'first' && !$authors->where('role', 'first')->count()) {
                    $collabRole = 'first';
                } elseif ($mainRole !== 'corresponding' && !$authors->where('role', 'corresponding')->count() && $this->faker->boolean(50)) {
                    $collabRole = 'corresponding';
                }

                $authors->push([
                    'teacher' => $collaborator,
                    'role' => $collabRole,
                    'sort_order' => $collabRole === 'first' ? 0 : $sortOrder++,
                    'share_weight' => $collabRole === 'first' ? 40 : ($collabRole === 'corresponding' ? 30 : 15),
                ]);
            }

            // Normalize weights to 100%
            $totalWeight = $authors->sum('share_weight');
            $authors = $authors->map(function ($a) use ($totalWeight) {
                $a['share_percent'] = $a['share_weight'] / $totalWeight * 100;
                return $a;
            });

            // Create incentive (70% chance)
            $hasIncentive = $this->faker->boolean(70);
            $totalIncentive = $hasIncentive ? rand(10, 100) * 1000 : 0;

            // Attach authors with incentive amounts
            foreach ($authors as $authorData) {
                $incentiveAmount = $hasIncentive
                    ? round($totalIncentive * $authorData['share_percent'] / 100, 2)
                    : 0;

                $pub->teachers()->attach($authorData['teacher']->id, [
                    'author_role' => $authorData['role'],
                    'sort_order' => $authorData['sort_order'],
                    'incentive_amount' => $incentiveAmount,
                ]);
            }

            // Create PublicationIncentive record
            if ($hasIncentive) {
                $status = $this->faker->randomElement(['pending', 'approved', 'paid']);

                \App\Models\PublicationIncentive::withoutEvents(function () use ($pub, $totalIncentive, $status, $adminUser) {
                    \App\Models\PublicationIncentive::create([
                        'publication_id' => $pub->id,
                        'total_amount' => $totalIncentive,
                        'status' => $status,
                        'approved_by' => $status !== 'pending' ? $adminUser?->id : null,
                        'approved_at' => $status !== 'pending' ? now()->subDays(rand(1, 30)) : null,
                        'paid_by' => $status === 'paid' ? $adminUser?->id : null,
                        'paid_at' => $status === 'paid' ? now()->subDays(rand(1, 15)) : null,
                        'remarks' => $status === 'paid' ? 'Payment processed.' : null,
                    ]);
                });
            }
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
                'organization' => $this->faker->company(),
                'category' => $this->faker->randomElement(['Technical', 'Pedagogy', 'Research Methodology', 'Leadership']),
                'duration_days' => $this->faker->numberBetween(1, 30),
                'completion_date' => $this->faker->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
                'year' => $this->faker->numberBetween(2015, 2024),
                'country_id' => $this->countries->random()->id,
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

        $count = $this->faker->numberBetween(0, 3);

        for ($i = 0; $i < $count; $i++) {


            // Get random membership type
            $typeId = \App\Models\MembershipType::where('is_active', true)->inRandomOrder()->first()?->id;
            $orgId = \App\Models\MembershipOrganization::where('is_active', true)->inRandomOrder()->first()?->id;

            Membership::create([
                'teacher_id' => $teacher->id,
                'membership_organization_id' => $orgId,
                'membership_type_id' => $typeId,
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
        $bangladesh = $this->countries->firstWhere('slug', 'bangladesh') ?? $this->countries->first();

        for ($i = 0; $i < $count; $i++) {
            $isCurrent = $i === 0;
            JobExperience::create([
                'teacher_id' => $teacher->id,
                'position' => $this->faker->randomElement(['Lecturer', 'Assistant Professor', 'Software Engineer', 'Research Associate']),
                'organization' => $isCurrent ? 'Daffodil International University' : $this->faker->company,
                'department' => $this->faker->randomElement(['CSE', 'IT', 'Research', 'Development']),
                'location' => $this->faker->city,
                'country_id' => $bangladesh->id,
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
            //'research_projects' => $teacher->researchProjects->toArray(),
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
