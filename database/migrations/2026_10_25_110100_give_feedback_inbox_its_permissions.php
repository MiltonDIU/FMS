<?php

use App\Policies\FeedbackPolicy;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who works the feedback inbox.
 *
 * Reading and answering go to super_admin and admin; deleting is kept to
 * super_admin, since a thread is the record of what was asked and promised.
 * Sending feedback needs no permission at all — it is switched on and off for
 * everybody on the System Settings page.
 *
 * FeedbackPermissionSeeder grants the same for a fresh install; this is here
 * because deploys run migrations and never seeders.
 */
return new class extends Migration
{
    private const GRANTS = [
        FeedbackPolicy::VIEW_ANY => ['super_admin', 'admin'],
        FeedbackPolicy::VIEW => ['super_admin', 'admin'],
        FeedbackPolicy::UPDATE => ['super_admin', 'admin'],
        FeedbackPolicy::DELETE => ['super_admin'],
        FeedbackPolicy::DELETE_ANY => ['super_admin'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $name => $roles) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            foreach ($roles as $roleName) {
                Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($name);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::GRANTS))->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
