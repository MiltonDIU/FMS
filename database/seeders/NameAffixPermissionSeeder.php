<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The permissions for the two name lookup screens.
 *
 * Written by hand rather than left to `shield:generate`, because that command
 * is something a developer runs on their own machine — it is not part of
 * migrate or seed, and the deploy runs neither. Without this file the
 * permissions exist on whoever generated them and nowhere else: a fresh
 * install would have the screens but no rows on the roles page, so there would
 * be nothing to tick to give anybody else access.
 *
 * Granted to super_admin and to nobody else. These lists decide how every
 * teacher's name is written across the site, and a wrong edit here is not one
 * profile but every profile holding that title. Widening it later is a tick on
 * the roles screen; that is the point of the permissions existing at all.
 *
 * super_admin would reach the screens regardless — Shield intercepts the gate
 * for that role — so this grant is not what gives access. It is what makes the
 * grant visible and transferable.
 */
class NameAffixPermissionSeeder extends Seeder
{
    /** The twelve Shield generates for a resource, in its own order. */
    private const ABILITIES = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'DeleteAny',
        'Restore',
        'ForceDelete',
        'ForceDeleteAny',
        'RestoreAny',
        'Replicate',
        'Reorder',
    ];

    private const ENTITIES = [
        'NamePrefix',
        'AcademicSuffix',
    ];

    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $granted = 0;

        foreach (self::ENTITIES as $entity) {
            foreach (self::ABILITIES as $ability) {
                $permission = Permission::firstOrCreate([
                    'name' => $ability . ':' . $entity,
                    'guard_name' => 'web',
                ]);

                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                    $granted++;
                }
            }
        }

        Artisan::call('permission:cache-reset');

        $this->command?->info('✔ ' . (count(self::ENTITIES) * count(self::ABILITIES))
            . ' name affix permissions ensured, ' . $granted . ' newly granted to super_admin');
    }
}
