<?php

namespace Database\Seeders;

use App\Policies\EmailBatchPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Who can read the delivery record of what has been emailed to teachers.
 *
 * Written by hand rather than generated, and run after RolePermissionsSeeder,
 * for the reason set out in DatabaseSeeder: that seeder assigns with
 * syncPermissions(), which replaces a role's whole set, so anything granted
 * before it is silently taken back off.
 *
 * Reading goes to the roles that can send in the first place. Deleting is kept
 * to super_admin: a batch is the only record that a message was ever sent, and
 * it carries the addresses it went to.
 */
class EmailBatchPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            EmailBatchPolicy::VIEW_ANY => ['super_admin', 'admin', 'registrar'],
            EmailBatchPolicy::VIEW => ['super_admin', 'admin', 'registrar'],
            EmailBatchPolicy::DELETE => ['super_admin'],
            EmailBatchPolicy::DELETE_ANY => ['super_admin'],
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
