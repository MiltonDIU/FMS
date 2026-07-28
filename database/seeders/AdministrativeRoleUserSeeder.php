<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\AdministrativeRole;
use App\Models\UserAdministrativeRole;
use Carbon\Carbon;

/**
 * Assigns exactly two administrative roles, to two known demo users:
 *
 *   dean@fms.diu.edu.bd → Dean            (faculty scope: FSIT)
 *   head@fms.diu.edu.bd → Head of Dept.   (department scope: CSE)
 *
 * No other user / role / scope combination is seeded — Spatie roles
 * (dean / head) are synced automatically by UserAdministrativeRoleObserver.
 */
class AdministrativeRoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faculty = Faculty::where('code', 'FSIT')->first();
        if (! $faculty) {
            $this->command->error("Faculty 'FSIT' not found. Run FacultySeeder first.");
            return;
        }

        $department = Department::where('code', 'CSE')->first();
        if (! $department) {
            $this->command->error("Department 'CSE' not found. Run DepartmentSeeder first.");
            return;
        }

        $this->assign(
            email:    'dean@fms.diu.edu.bd',
            roleName: 'Dean',
            scope:    ['faculty_id' => $faculty->id, 'department_id' => null],
            label:    "Dean of {$faculty->short_name}"
        );

        $this->assign(
            email:    'head@fms.diu.edu.bd',
            roleName: 'Head of Department',
            scope:    ['faculty_id' => null, 'department_id' => $department->id],
            label:    "Head of {$department->short_name}"
        );
    }

    /**
     * Assign one administrative role to one user for one scope.
     *
     * Idempotent, and safe to re-run over data left by an earlier seed: any
     * active assignment for the same role + scope held by a different user is
     * retired first, so the outgoing holder loses the Spatie role via the
     * observer instead of silently keeping it.
     */
    private function assign(string $email, string $roleName, array $scope, string $label): void
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->command->error("User '{$email}' not found. Run FMSSeeder first.");
            return;
        }

        $role = AdministrativeRole::where('name', $roleName)->first();
        if (! $role) {
            $this->command->error("Administrative role '{$roleName}' not found. Run AdministrativeRoleSeeder first.");
            return;
        }

        $scopeQuery = fn () => UserAdministrativeRole::query()
            ->where('administrative_role_id', $role->id)
            ->where('faculty_id', $scope['faculty_id'])
            ->where('department_id', $scope['department_id']);

        // Retire other holders of this exact role + scope. Saved one by one via
        // Eloquent so UserAdministrativeRoleObserver fires and drops their
        // Spatie role when nothing else justifies it.
        $scopeQuery()
            ->where('user_id', '!=', $user->id)
            ->where('is_active', true)
            ->get()
            ->each(function (UserAdministrativeRole $stale) {
                $stale->is_active = false;
                $stale->end_date  = Carbon::now();
                $stale->save();
            });

        // Eloquent (not raw DB) so the observer syncs the Spatie role.
        $assignment = $scopeQuery()->where('user_id', $user->id)->first()
            ?? new UserAdministrativeRole([
                'user_id'                => $user->id,
                'administrative_role_id' => $role->id,
                'faculty_id'             => $scope['faculty_id'],
                'department_id'          => $scope['department_id'],
            ]);

        $assignment->fill([
            'start_date' => $assignment->start_date ?? Carbon::now(),
            'end_date'   => null,
            'is_active'  => true,
        ])->save();

        $this->command->info("✔ {$email} → {$label}");
    }
}
