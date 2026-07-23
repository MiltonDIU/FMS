<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\AdministrativeRole;
use App\Models\UserAdministrativeRole;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class AdministrativeRoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch Administrative Role IDs
        $roles = AdministrativeRole::whereIn('name', [
            'Dean', 'Associate Dean', 'Dean officer',
            'Head of Department', 'Associate Head', 'Head Officer'
        ])->pluck('id', 'name');

        if ($roles->count() < 6) {
             $this->command->warn("Some roles are missing in the database. Found: " . $roles->keys()->implode(', '));
        }

        $users = User::where('is_active', true)->inRandomOrder()->get();
        if ($users->isEmpty()) {
            $this->command->error("No active users found to assign roles.");
            return;
        }

        $userIterator = $users->getIterator();

        $getNextUser = function() use ($users, &$userIterator) {
            if (!$userIterator->valid()) {
                $userIterator = $users->getIterator();
            }
            $user = $userIterator->current();
            $userIterator->next();
            return $user;
        };

        // 2. Assign Faculty Roles
        $faculties = Faculty::all();
        $this->command->info("Assigning roles to {$faculties->count()} faculties...");

        foreach ($faculties as $faculty) {
            if (isset($roles['Dean'])) {
                $this->assignRole($getNextUser()->id, $roles['Dean'], facultyId: $faculty->id);
            }
            if (isset($roles['Associate Dean'])) {
                $this->assignRole($getNextUser()->id, $roles['Associate Dean'], facultyId: $faculty->id);
            }
            if (isset($roles['Dean officer'])) {
                $this->assignRole($getNextUser()->id, $roles['Dean officer'], facultyId: $faculty->id);
            }
        }

        // 3. Assign Department Roles
        $departments = Department::all();
        $this->command->info("Assigning roles to {$departments->count()} departments...");

        foreach ($departments as $department) {
            if (isset($roles['Head of Department'])) {
                $this->assignRole($getNextUser()->id, $roles['Head of Department'], departmentId: $department->id);
            }
            if (isset($roles['Associate Head'])) {
                $this->assignRole($getNextUser()->id, $roles['Associate Head'], departmentId: $department->id);
            }
            if (isset($roles['Head Officer'])) {
                $this->assignRole($getNextUser()->id, $roles['Head Officer'], departmentId: $department->id);
            }
        }

        $this->command->info('✔ Spatie roles (dean/head) synced automatically via Observer.');
    }

    /**
     * Assign an administrative role to a user.
     * Uses Eloquent (not raw DB::insert) so the UserAdministrativeRoleObserver fires
     * and Spatie roles are automatically synced.
     */
    private function assignRole(int $userId, int $roleId, ?int $facultyId = null, ?int $departmentId = null): void
    {
        // Skip if an active assignment already exists for this scope
        $exists = UserAdministrativeRole::withTrashed()
            ->where('administrative_role_id', $roleId)
            ->where('faculty_id', $facultyId)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return;
        }

        // Use Eloquent::create() so booted() observer fires automatically
        UserAdministrativeRole::create([
            'user_id'                => $userId,
            'administrative_role_id' => $roleId,
            'faculty_id'             => $facultyId,
            'department_id'          => $departmentId,
            'start_date'             => Carbon::now(),
            'is_active'              => true,
        ]);

        // Explicit sync in case observer is not yet registered (e.g. first-time seeding)
        $this->syncSpatieRole($userId, $roleId);
    }

    /**
     * Sync Spatie role based on the administrative role's NAME.
     *
     * Mapping:
     *   Dean               → dean
     *   Associate Dean     → associate_dean
     *   Dean officer       → associate_dean
     *   Head of Department → head
     *   Associate Head     → associate_head
     *   Head Officer       → associate_head
     */
    private function syncSpatieRole(int $userId, int $adminRoleId): void
    {
        $adminRole = AdministrativeRole::find($adminRoleId);
        if (! $adminRole) {
            return;
        }

        $roleMap = [
            'dean'                => 'dean',
            'associate dean'      => 'associate_dean',
            'dean officer'        => 'associate_dean',
            'head of department'  => 'head',
            'associate head'      => 'associate_head',
            'head officer'        => 'associate_head',
        ];

        $spatieRoleName = $roleMap[strtolower($adminRole->name)] ?? null;

        if (! $spatieRoleName) {
            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $spatieRole = Role::firstOrCreate(['name' => $spatieRoleName, 'guard_name' => 'web']);

        if (! $user->hasRole($spatieRoleName)) {
            $user->assignRole($spatieRole);
        }
    }
}
