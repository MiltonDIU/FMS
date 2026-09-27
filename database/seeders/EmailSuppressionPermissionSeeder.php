<?php

namespace Database\Seeders;

use App\Policies\EmailSuppressionPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Who can manage the list of blocked email addresses.
 *
 * Same shape and the same place in DatabaseSeeder as EmailBatchPermissionSeeder,
 * for the same reason: run after RolePermissionsSeeder, whose syncPermissions()
 * would otherwise take these back off.
 *
 * Reading and adding go to the roles that send email, because they are the ones
 * who see the bounces. Removing an address — which lets mail reach it again —
 * stays with super_admin and admin.
 */
class EmailSuppressionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            EmailSuppressionPolicy::VIEW_ANY => ['super_admin', 'admin', 'registrar'],
            EmailSuppressionPolicy::VIEW => ['super_admin', 'admin', 'registrar'],
            EmailSuppressionPolicy::CREATE => ['super_admin', 'admin', 'registrar'],
            EmailSuppressionPolicy::UPDATE => ['super_admin', 'admin', 'registrar'],
            EmailSuppressionPolicy::DELETE => ['super_admin', 'admin'],
            EmailSuppressionPolicy::DELETE_ANY => ['super_admin', 'admin'],
        ];

        foreach ($permissions as $name => $roles) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            foreach ($roles as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }

                $this->command?->info("✔ [{$roleName}] → {$name}");
            }
        }

        Artisan::call('permission:cache-reset');
    }
}
