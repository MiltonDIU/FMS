<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSevenPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Find Role ID 7
        $role = Role::find(7);

        if (!$role) {
            $this->command->error('Role with ID 7 not found!');
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
            'View:PublicationStatsOverview',
            'View:PublicationYearWidget',
            'View:QueueStatusWidget',
            'View:PublicationOverview',
            'View:TeacherResearchStatsWidget',
            'View:PublicationQuartileWidget',
            'View:PublicationLinkageChart',
            'View:PublicationGrantTypeWidget',
            'View:PublicationTypeChart'
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            
            // Assign permission if not already assigned
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
        
        $this->command->info('Permissions assigned to Role ID 7 successfully.');
    }
}
