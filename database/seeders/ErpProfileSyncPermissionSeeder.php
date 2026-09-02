<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The two permissions the ERP profile field sync is gated on.
 *
 * Written by hand rather than generated: Shield builds its permission list from
 * a resource's declared abilities, and these are custom policy methods it does
 * not know about — the same reason ImportErp:Teacher and
 * BatchCalculateProfileScores:Teacher were seeded this way.
 *
 * Granted narrowly on purpose. The bulk run writes to hundreds of teachers at
 * once and does so past the approval workflow, because the ERP is the system of
 * record for those fields. That is not something a department or faculty
 * officer should be able to start, so only super_admin and admin get it here;
 * anyone else can be given it from the role screen deliberately.
 */
class ErpProfileSyncPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // One teacher, from their own row.
            'SyncErpProfile:Teacher' => ['super_admin', 'admin', 'registrar'],

            // A whole employment status at a time.
            'BulkSyncErpProfiles:Teacher' => ['super_admin', 'admin'],
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
