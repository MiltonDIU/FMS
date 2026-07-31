<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the DeleteAny:* permissions and hands them to whoever could already
 * delete.
 *
 * Filament authorizes a DeleteBulkAction with the policy's deleteAny() method,
 * but Shield's configured method list omitted 'deleteAny', so no policy defined
 * it. A missing method plus non-strict authorization means the check silently
 * passes: any role that could list a table could bulk delete every row in it,
 * whether or not it held Delete:*.
 *
 * Adding deleteAny() to the policies closes that, but on its own it would also
 * take bulk delete away from the roles legitimately using it, since the matching
 * permission never existed. Granting DeleteAny:X to every role that already has
 * Delete:X keeps intent intact — if you could delete a row, you can still delete
 * a selection — while everyone else is now correctly refused.
 *
 * Usage: php artisan db:seed --class=BulkDeletePermissionSeeder
 */
class BulkDeletePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $singles = Permission::query()
            ->where('name', 'like', 'Delete:%')
            ->where('guard_name', 'web')
            ->get();

        if ($singles->isEmpty()) {
            $this->command->warn('No Delete:* permissions found; run shield:generate first.');

            return;
        }

        $created = 0;
        $grants = [];

        foreach ($singles as $single) {
            $subject = substr($single->name, strlen('Delete:'));

            $bulk = Permission::firstOrCreate([
                'name' => 'DeleteAny:' . $subject,
                'guard_name' => 'web',
            ]);

            if ($bulk->wasRecentlyCreated) {
                $created++;
            }

            foreach ($single->roles as $role) {
                if ($role->hasPermissionTo($bulk)) {
                    continue;
                }

                $role->givePermissionTo($bulk);
                $grants[$role->name] = ($grants[$role->name] ?? 0) + 1;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info(sprintf(
            '%d DeleteAny permission(s) created from %d Delete permission(s).',
            $created,
            $singles->count(),
        ));

        foreach ($grants as $role => $count) {
            $this->command->line(sprintf('  %-18s +%d', $role, $count));
        }

        if (empty($grants)) {
            $this->command->line('  Every role already had its matching DeleteAny permissions.');
        }
    }
}
