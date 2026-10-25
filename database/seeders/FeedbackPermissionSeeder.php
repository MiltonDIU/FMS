<?php

namespace Database\Seeders;

use App\Policies\FeedbackPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Who works the feedback inbox.
 *
 * Run after RolePermissionsSeeder for the reason set out in DatabaseSeeder.
 * Sending feedback is not a permission: it is one switch for everybody, on the
 * System Settings page.
 */
class FeedbackPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            FeedbackPolicy::VIEW_ANY => ['super_admin', 'admin'],
            FeedbackPolicy::VIEW => ['super_admin', 'admin'],
            FeedbackPolicy::UPDATE => ['super_admin', 'admin'],
            FeedbackPolicy::DELETE => ['super_admin'],
            FeedbackPolicy::DELETE_ANY => ['super_admin'],
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
