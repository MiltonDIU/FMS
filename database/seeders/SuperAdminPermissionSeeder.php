<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives super_admin every permission that exists, whoever created it.
 *
 * This used to be a hand-kept list inside RolePermissionsSeeder, and that list
 * could only ever be a subset. shield:generate writes a permission per resource,
 * page, widget and custom entry; other seeders add their own; a new resource
 * adds eleven more. None of that reaches a literal array written months ago, and
 * because the assignment is a syncPermissions() rather than a grant, everything
 * missing from the array was actively taken away again on every seed — including
 * grants the seeders immediately above had just made.
 *
 * The result was the thing that prompted this: after a full seed, super_admin
 * still could not reach parts of the system, and somebody had to open the role
 * screen and tick the boxes by hand.
 *
 * Reading the permissions table instead means the role is complete by
 * construction. Shield's own super-admin gate is off in this project
 * (config/filament-shield.php, 'define_via_gate' => false), so the permissions
 * genuinely have to be on the role — there is no bypass behind them.
 *
 * Run it last. Anything seeded afterwards would not be included.
 *
 * Usage: php artisan db:seed --class=SuperAdminPermissionSeeder
 */
class SuperAdminPermissionSeeder extends Seeder
{
    protected const ROLE = 'super_admin';

    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => self::ROLE, 'guard_name' => 'web']);

        $all = Permission::where('guard_name', 'web')->pluck('name');

        if ($all->isEmpty()) {
            $this->command?->warn('No permissions exist yet; run shield:generate --all first.');

            return;
        }

        $before = $role->permissions()->count();

        $role->syncPermissions($all->all());

        // Spatie caches the permission map, so without this the grants stay
        // invisible for the rest of the request and to anything reading the
        // cache afterwards.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            '%s now holds all %d permissions (%+d).',
            self::ROLE,
            $all->count(),
            $all->count() - $before,
        ));
    }
}
