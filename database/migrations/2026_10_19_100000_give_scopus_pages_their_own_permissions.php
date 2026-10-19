<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Scopus Review and Institution Identity now check their own permissions.
 *
 * Both pages asked for ViewAny:Publication and Create:Publication, so a role
 * given those two for the Publications screen got both pages as well, and the
 * View:ScopusReview and View:InstitutionIdentity ticks Shield shows on the role
 * screen changed nothing.
 *
 * The pages belong to the people who run Scopus reviews: the research team and
 * admin. super_admin passes on its role. Any other role that reached the pages
 * through the publication permissions loses them here, which is the point; a
 * role that should have them can be ticked on the role screen.
 *
 * RolePermissionsSeeder grants the same for a fresh install; this is here
 * because deploys run migrations and never seeders.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['View:ScopusReview', 'View:InstitutionIdentity'];

    private const ROLES = ['super_admin', 'admin', 'research_team'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $name) {
            Role::where('name', $name)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Only admin and research_team: super_admin held both before this ran,
     * because SuperAdminPermissionSeeder gives it every permission there is.
     */
    public function down(): void
    {
        foreach (['admin', 'research_team'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();

            foreach (self::PERMISSIONS as $permission) {
                $role?->revokePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
