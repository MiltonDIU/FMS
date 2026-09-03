<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * The publication permissions, for research_team.
 *
 * It used to look the role up as Role::find(7), which is a guess about insert
 * order rather than a fact. On this database id 7 is head, so a department head
 * was being handed full CRUD over every publication table — invisible only
 * because RolePermissionsSeeder runs afterwards and syncs head back down.
 *
 * The name is the identity, so it looks up the name.
 *
 * RolePermissionsSeeder remains the authority on what research_team holds; this
 * seeder is the one to reach for when the grants are wanted on their own,
 * without re-syncing every other role.
 */
class RoleSevenPermissionSeeder extends Seeder
{
    protected const ROLE = 'research_team';

    public function run(): void
    {
        $role = Role::where('name', self::ROLE)->where('guard_name', 'web')->first();

        if (!$role) {
            $this->command->error(self::ROLE . ' role not found!');
            return;
        }

        $permissions = [
            'ViewAny:GrantType',
            'View:GrantType',
            'Create:GrantType',
            'Update:GrantType',
            'Delete:GrantType',
            'Restore:GrantType',
            'ForceDelete:GrantType',
            'ForceDeleteAny:GrantType',
            'RestoreAny:GrantType',
            'Replicate:GrantType',
            'Reorder:GrantType',
            'ViewAny:PublicationIncentive',
            'View:PublicationIncentive',
            'Create:PublicationIncentive',
            'Update:PublicationIncentive',
            'Delete:PublicationIncentive',
            'ViewAny:PublicationLinkage',
            'View:PublicationLinkage',
            'Create:PublicationLinkage',
            'Update:PublicationLinkage',
            'Delete:PublicationLinkage',
            'Restore:PublicationLinkage',
            'ForceDelete:PublicationLinkage',
            'ForceDeleteAny:PublicationLinkage',
            'RestoreAny:PublicationLinkage',
            'Replicate:PublicationLinkage',
            'Reorder:PublicationLinkage',
            'ViewAny:PublicationQuartile',
            'View:PublicationQuartile',
            'Create:PublicationQuartile',
            'Update:PublicationQuartile',
            'Delete:PublicationQuartile',
            'Restore:PublicationQuartile',
            'ForceDelete:PublicationQuartile',
            'ForceDeleteAny:PublicationQuartile',
            'RestoreAny:PublicationQuartile',
            'Replicate:PublicationQuartile',
            'Reorder:PublicationQuartile',
            'ViewAny:PublicationType',
            'View:PublicationType',
            'Create:PublicationType',
            'Update:PublicationType',
            'Delete:PublicationType',
            'Restore:PublicationType',
            'ForceDelete:PublicationType',
            'ForceDeleteAny:PublicationType',
            'RestoreAny:PublicationType',
            'Replicate:PublicationType',
            'Reorder:PublicationType',
            'ViewAny:Publication',
            'View:Publication',
            'Create:Publication',
            'Update:Publication',
            'Delete:Publication',
            'Restore:Publication',
            'ForceDelete:Publication',
            'ForceDeleteAny:Publication',
            'RestoreAny:Publication',
            'Replicate:Publication',
            'Reorder:Publication',
            'ViewAny:ResearchCollaboration',
            'View:ResearchCollaboration',
            'Create:ResearchCollaboration',
            'Update:ResearchCollaboration',
            'Delete:ResearchCollaboration',
            'Restore:ResearchCollaboration',
            'ForceDelete:ResearchCollaboration',
            'ForceDeleteAny:ResearchCollaboration',
            'RestoreAny:ResearchCollaboration',
            'Replicate:ResearchCollaboration',
            'Reorder:ResearchCollaboration',
            'ViewAny:Teacher',
            'View:Dashboard',
            'View:TeacherDashboard',
            // Widget permissions are not listed here. Which role sees which
            // widget is decided by the matrix at the top of
            // RolePermissionsSeeder, and this seeder granting a few of them by
            // hand is how research_team ended up with the queue monitor.
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            
            // Assign permission if not already assigned
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
        
        $this->command->info('Publication permissions assigned to ' . self::ROLE . '.');
    }
}
