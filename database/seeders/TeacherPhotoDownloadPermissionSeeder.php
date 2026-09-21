<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The permission behind the photograph download on a teacher's row.
 *
 * Written by hand for the same reason as the ERP sync permissions: Shield
 * builds its list from a resource's declared abilities and knows nothing about
 * custom policy methods.
 *
 * Granted to super_admin only. A photograph saved out of the system cannot be
 * recalled, so it starts with the narrowest possible audience and is widened
 * deliberately.
 *
 * Widening it needs no code: the permission is listed in config/filament-shield
 * under custom_permissions, so it shows up on the roles screen and can be
 * ticked for any role. Adding a role name below and re-running this seeder does
 * the same thing from the command line.
 */
class TeacherPhotoDownloadPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'DownloadPhoto:Teacher' => ['super_admin'],
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

        /*
         * This shipped for one day as a bulk export of every photograph, under
         * the plural name. Nothing checks it any more, and a permission nothing
         * checks is worse than no permission at all: it sits on the roles screen
         * looking like a control somebody can grant, and granting it does
         * nothing whatsoever.
         */
        Permission::query()->where('name', 'DownloadPhotos:Teacher')->delete();

        Artisan::call('permission:cache-reset');
    }
}
