<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\AdministrativeRole;
use Carbon\Carbon;

class AdministrativeRoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch Role IDs
        $roles = AdministrativeRole::whereIn('name', [
            'Dean', 'Associate Dean', 'Dean officer',
            'Head of Department', 'Associate Head', 'Head Officer'
        ])->pluck('id', 'name');

        // Helper to check missing roles
        if ($roles->count() < 6) {
             $this->command->warn("Some roles are missing in the database. Found: " . $roles->keys()->implode(', '));
             // Proceeding with available roles
        }

        $users = User::where('is_active', true)->inRandomOrder()->get();
        if ($users->isEmpty()) {
            $this->command->error("No active users found to assign roles.");
            return;
        }

        // Iterator for users to distribute roles somewhat evenly
        $userIterator = $users->getIterator();

        $getNextUser = function() use ($users, &$userIterator) {
            if (!$userIterator->valid()) {
                $userIterator = $users->getIterator(); // Reset if exhausted
            }
            $user = $userIterator->current();
            $userIterator->next();
            return $user;
        };

        // 2. Assign Faculty Roles
        $faculties = Faculty::all();
        $this->command->info("Assigning roles to {$faculties->count()} faculties...");

        foreach ($faculties as $faculty) {
            // Dean
            if (isset($roles['Dean'])) {
                $this->assignRole($getNextUser()->id, $roles['Dean'], facultyId: $faculty->id);
            }
            // Associate Dean
            if (isset($roles['Associate Dean'])) {
                $this->assignRole($getNextUser()->id, $roles['Associate Dean'], facultyId: $faculty->id);
            }
            // Dean Officer
            if (isset($roles['Dean officer'])) {
                $this->assignRole($getNextUser()->id, $roles['Dean officer'], facultyId: $faculty->id);
            }
        }

        // 3. Assign Department Roles
        $departments = Department::all();
        $this->command->info("Assigning roles to {$departments->count()} departments...");

        foreach ($departments as $department) {
            // Head of Department
            if (isset($roles['Head of Department'])) {
                $this->assignRole($getNextUser()->id, $roles['Head of Department'], departmentId: $department->id);
            }
            // Associate Head
            if (isset($roles['Associate Head'])) {
                $this->assignRole($getNextUser()->id, $roles['Associate Head'], departmentId: $department->id);
            }
            // Head Officer
            if (isset($roles['Head Officer'])) {
                $this->assignRole($getNextUser()->id, $roles['Head Officer'], departmentId: $department->id);
            }
        }
    }

    private function assignRole($userId, $roleId, $facultyId = null, $departmentId = null)
    {
        // Check if assignment exists to avoid duplicates
        $exists = DB::table('administrative_role_user')
            ->where('administrative_role_id', $roleId)
            ->where('faculty_id', $facultyId)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->exists();

        // Also check if the specific user already has this role in this context?
        // The requirement is "one dean per faculty". If one exists, we might skip or replace.
        // For seeding, let's assume we want to fill 'empty' slots. 
        // But if the user wants to forcingly set data, maybe we should not check 'exists' globally, but for that specific context.
        
        if ($exists) {
            // Check if it's the same scope
            // Actually, if a Dean already exists for Faculty A, we shouldn't add another one?
            // The user says "ekjon dean thakbe" (there will be one dean).
            // So if one exists, skip.
            return;
        }

        DB::table('administrative_role_user')->insert([
            'user_id' => $userId,
            'administrative_role_id' => $roleId,
            'faculty_id' => $facultyId,
            'department_id' => $departmentId,
            'start_date' => Carbon::now(),
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
