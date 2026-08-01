<?php

namespace Database\Seeders;

use App\Policies\ActivityPolicy;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the two permissions that guard the audit trail and gives them to
 * super_admin.
 *
 * Access is by permission rather than by role so that reading the trail can be
 * handed to somebody else — an auditor, an investigation — from the role editor,
 * without a deployment. super_admin is only where it starts.
 *
 * Only viewing exists. Nothing may create, edit or delete an entry, so those
 * permissions are deliberately absent rather than created and left unassigned:
 * a permission that exists invites somebody to grant it, and the policy would
 * refuse it anyway.
 *
 * The panel runs with strictAuthorization(), so without these the resource
 * raises rather than hiding — which is the point, but it means this seeder is
 * not optional.
 *
 * Usage: php artisan db:seed --class=ActivityLogPermissionSeeder
 */
class ActivityLogPermissionSeeder extends Seeder
{
    protected const PERMISSIONS = [
        ActivityPolicy::VIEW_ANY,
        ActivityPolicy::VIEW,
    ];

    /** Where access starts. Others are granted from the role editor. */
    protected const ROLE = 'super_admin';

    public function run(): void
    {
        $created = [];

        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            if ($permission->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        $role = Role::where('name', self::ROLE)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->command?->warn(self::ROLE . ' role not found; permissions created but granted to nobody.');

            return;
        }

        $granted = [];

        foreach (self::PERMISSIONS as $name) {
            if (! $role->hasPermissionTo($name)) {
                $role->givePermissionTo($name);
                $granted[] = $name;
            }
        }

        // Spatie caches the permission map; without this the change is invisible
        // until the cache expires on its own.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Activity log permissions: %d created, %d granted to %s.',
            count($created),
            count($granted),
            self::ROLE,
        ));
    }
}
