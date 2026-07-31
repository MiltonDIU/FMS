<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Splits writing System Settings out from viewing it.
 *
 * The page only ever had View:SystemSettings, while its methods could save every
 * setting and dispatch the master import and export. Those methods now require
 * Update:SystemSettings, so the permission has to exist and reach whoever could
 * already write — otherwise the fix locks out the very people who need it,
 * super_admin included.
 *
 * Granting it to exactly the holders of View:SystemSettings keeps today's
 * behaviour identical; the split only becomes useful once someone is given read
 * access without write.
 *
 * Usage: php artisan db:seed --class=SystemSettingsPermissionSeeder
 */
class SystemSettingsPermissionSeeder extends Seeder
{
    protected const VIEW = 'View:SystemSettings';

    protected const UPDATE = 'Update:SystemSettings';

    public function run(): void
    {
        $update = Permission::firstOrCreate([
            'name' => self::UPDATE,
            'guard_name' => 'web',
        ]);

        $view = Permission::where('name', self::VIEW)->where('guard_name', 'web')->first();

        if (! $view) {
            $this->command->warn(self::VIEW . ' does not exist yet; run shield:generate first.');

            return;
        }

        $granted = [];

        foreach ($view->roles as $role) {
            if (! $role->hasPermissionTo($update)) {
                $role->givePermissionTo($update);
                $granted[] = $role->name;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info(empty($granted)
            ? self::UPDATE . ' already held by every role that can view settings.'
            : self::UPDATE . ' granted to: ' . implode(', ', $granted));
    }
}
