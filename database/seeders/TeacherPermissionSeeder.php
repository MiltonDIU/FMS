<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TeacherPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure teacher role exists
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        // Define permissions needed for teacher
        // The TeacherPolicy allows teachers to update their OWN profile based on user_id.
        // We do NOT give 'ViewAny:Teacher' so they don't see the full list.
        $permissions = [
            'View:MyProfile',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);

            if (! $teacherRole->hasPermissionTo($permission)) {
                $teacherRole->givePermissionTo($permission);
            }
        }
    }
}
