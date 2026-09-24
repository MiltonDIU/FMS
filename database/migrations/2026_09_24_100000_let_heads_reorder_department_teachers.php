<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lets Heads and Associate Heads drag their department's teachers into order.
 *
 * The order on the department teachers screen is the order the public site
 * lists a department's teachers in, and the people who know what it should be
 * are the department's own Head and Associate Head. The table has always been
 * reorderable, but only to someone holding Reorder:DepartmentTeacher, which
 * neither role had.
 *
 * RolePermissionsSeeder grants it too, for a fresh install; this is here
 * because deploys run migrations and never seeders, so without it the live
 * roles would not get it.
 */
return new class extends Migration
{
    private const PERMISSION = 'Reorder:DepartmentTeacher';

    private const ROLES = ['head', 'associate_head'];

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        foreach (self::ROLES as $name) {
            Role::where('name', $name)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::ROLES as $name) {
            Role::where('name', $name)->where('guard_name', 'web')->first()?->revokePermissionTo(self::PERMISSION);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
