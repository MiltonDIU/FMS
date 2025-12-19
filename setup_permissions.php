<?php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$permissions = [
    'ViewAny:User',
    'View:User',
    'Create:User',
    'Update:User',
    'Delete:User',
    'Restore:User',
    'ForceDelete:User',
];

foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    echo "Created permission: $permission\n";
}

$role = Role::where('name', 'super_admin')->first();
if ($role) {
    $role->givePermissionTo(Permission::all());
    echo "Assigned all permissions to super_admin\n";
}
