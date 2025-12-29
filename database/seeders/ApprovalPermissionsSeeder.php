<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ApprovalPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define permissions needed for the approval system
        $permissions = [
            'approve:teacher-profile',          // Can approve any profile
            'approve:own-department-teacher',   // Can approve own department
            'approve:own-faculty-teacher',      // Can approve own faculty
            'view:pending-approvals',           // Can view pending list
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to Super Admin
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->givePermissionTo($permissions);
        
        // Assign to Dean/Head roles if they exist
        // Note: You might want to customize which existing roles get these
    }
}
