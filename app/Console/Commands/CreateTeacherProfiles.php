<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;

class CreateTeacherProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teachers:create-profiles 
                            {--user= : Specific user ID to create profile for}
                            {--department= : Default department code}
                            {--designation= : Default designation name}
                            {--all : Create profiles for all users without teacher profiles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create teacher profiles for users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        $departmentCode = $this->option('department') ?? 'CSE';
        $designationName = $this->option('designation') ?? 'Lecturer';
        $createAll = $this->option('all');

        // Get default department and designation
        $department = Department::where('code', $departmentCode)->first();
        $designation = Designation::where('name', $designationName)->first();

        if (!$department) {
            $this->error("Department with code '{$departmentCode}' not found. Please run seeders first.");
            return Command::FAILURE;
        }

        if (!$designation) {
            $this->error("Designation '{$designationName}' not found. Please run seeders first.");
            return Command::FAILURE;
        }

        if ($userId) {
            // Create profile for specific user
            return $this->createProfileForUser($userId, $department, $designation);
        }

        if ($createAll) {
            // Create profiles for all users without teacher profiles
            return $this->createProfilesForAllUsers($department, $designation);
        }

        // Interactive mode
        return $this->interactiveMode($department, $designation);
    }

    /**
     * Create profile for a specific user
     */
    private function createProfileForUser(int $userId, Department $department, Designation $designation): int
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return Command::FAILURE;
        }

        if ($user->teacher) {
            $this->warn("User '{$user->name}' already has a teacher profile.");
            return Command::SUCCESS;
        }

        $teacher = $this->createTeacherProfile($user, $department, $designation);
        $this->info("Created teacher profile for '{$user->name}' (ID: {$teacher->id})");

        return Command::SUCCESS;
    }

    /**
     * Create profiles for all users without teacher profiles
     */
    private function createProfilesForAllUsers(Department $department, Designation $designation): int
    {
        $usersWithoutProfiles = User::whereDoesntHave('teacher')->get();

        if ($usersWithoutProfiles->isEmpty()) {
            $this->info('All users already have teacher profiles.');
            return Command::SUCCESS;
        }

        $this->info("Found {$usersWithoutProfiles->count()} users without teacher profiles.");

        if (!$this->confirm('Do you want to create teacher profiles for all these users?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($usersWithoutProfiles->count());
        $bar->start();

        foreach ($usersWithoutProfiles as $user) {
            $this->createTeacherProfile($user, $department, $designation);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Created {$usersWithoutProfiles->count()} teacher profiles.");

        return Command::SUCCESS;
    }

    /**
     * Interactive mode for creating profiles
     */
    private function interactiveMode(Department $defaultDepartment, Designation $defaultDesignation): int
    {
        $usersWithoutProfiles = User::whereDoesntHave('teacher')->get();

        if ($usersWithoutProfiles->isEmpty()) {
            $this->info('All users already have teacher profiles.');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email'],
            $usersWithoutProfiles->map(fn($u) => [$u->id, $u->name, $u->email])
        );

        $selectedIds = $this->ask('Enter user IDs to create profiles for (comma separated, or "all")');

        if ($selectedIds === 'all') {
            return $this->createProfilesForAllUsers($defaultDepartment, $defaultDesignation);
        }

        $ids = array_map('trim', explode(',', $selectedIds));

        foreach ($ids as $id) {
            $this->createProfileForUser((int) $id, $defaultDepartment, $defaultDesignation);
        }

        return Command::SUCCESS;
    }

    /**
     * Create a teacher profile
     */
    private function createTeacherProfile(User $user, Department $department, Designation $designation): Teacher
    {
        $nameParts = explode(' ', $user->name);
        $firstName = $nameParts[0] ?? '';
        $lastName = count($nameParts) > 1 ? end($nameParts) : '';
        $middleName = count($nameParts) > 2 ? implode(' ', array_slice($nameParts, 1, -1)) : '';

        return Teacher::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'profile_status' => 'draft',
            'is_public' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}
