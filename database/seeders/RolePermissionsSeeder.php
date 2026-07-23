<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * RolePermissionsSeeder
 *
 * Defines and assigns permissions for all roles in the FMS system.
 *
 * Roles:
 *  - super_admin   : Full access — all permissions explicitly assigned + bypasses policy checks in Filament
 *  - admin         : Full CRUD on all resources
 *  - registrar     : Can manage teachers, departments, faculties, designations, etc. (read/write). No publication incentive management.
 *  - dean          : Can view teachers and publications scoped to their faculty. View-only on most.
 *  - head          : Can view teachers and publications scoped to their department. View-only on most.
 *  - research_team : Full access to publication-related resources
 *  - teacher       : Can only view their own profile
 *
 * Run with: php artisan db:seed --class=RolePermissionsSeeder
 */
class RolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ---------------------------------------------------------------
        // 1. Define ALL permissions used across the system
        // ---------------------------------------------------------------
        $allPermissions = [
            // Dashboard & Widgets
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
            'View:PublicationTypeChart',

            // Teacher Profile (self)
            'View:MyProfile',

            // Teachers
            'ViewAny:Teacher', 'View:Teacher',
            'Create:Teacher', 'Update:Teacher', 'Delete:Teacher',
            'Restore:Teacher', 'ForceDelete:Teacher',
            'ForceDeleteAny:Teacher', 'RestoreAny:Teacher',
            'Replicate:Teacher', 'Reorder:Teacher',

            // Teacher Versions
            'ViewAny:TeacherVersion', 'View:TeacherVersion',
            'Create:TeacherVersion', 'Update:TeacherVersion', 'Delete:TeacherVersion',
            'Restore:TeacherVersion', 'ForceDelete:TeacherVersion',
            'ForceDeleteAny:TeacherVersion', 'RestoreAny:TeacherVersion',
            'Replicate:TeacherVersion', 'Reorder:TeacherVersion',

            // Department Teachers
            'ViewAny:DepartmentTeacher', 'View:DepartmentTeacher',
            'Create:DepartmentTeacher', 'Update:DepartmentTeacher', 'Delete:DepartmentTeacher',
            'Restore:DepartmentTeacher', 'ForceDelete:DepartmentTeacher',
            'ForceDeleteAny:DepartmentTeacher', 'RestoreAny:DepartmentTeacher',
            'Replicate:DepartmentTeacher', 'Reorder:DepartmentTeacher',

            // Faculties
            'ViewAny:Faculty', 'View:Faculty',
            'Create:Faculty', 'Update:Faculty', 'Delete:Faculty',
            'Restore:Faculty', 'ForceDelete:Faculty',
            'ForceDeleteAny:Faculty', 'RestoreAny:Faculty',
            'Replicate:Faculty', 'Reorder:Faculty',

            // Departments
            'ViewAny:Department', 'View:Department',
            'Create:Department', 'Update:Department', 'Delete:Department',
            'Restore:Department', 'ForceDelete:Department',
            'ForceDeleteAny:Department', 'RestoreAny:Department',
            'Replicate:Department', 'Reorder:Department',

            // Designations
            'ViewAny:Designation', 'View:Designation',
            'Create:Designation', 'Update:Designation', 'Delete:Designation',
            'Restore:Designation', 'ForceDelete:Designation',
            'ForceDeleteAny:Designation', 'RestoreAny:Designation',
            'Replicate:Designation', 'Reorder:Designation',

            // Job Types
            'ViewAny:JobType', 'View:JobType',
            'Create:JobType', 'Update:JobType', 'Delete:JobType',
            'Restore:JobType', 'ForceDelete:JobType',
            'Replicate:JobType', 'Reorder:JobType',

            // Employment Statuses
            'ViewAny:EmploymentStatus', 'View:EmploymentStatus',
            'Create:EmploymentStatus', 'Update:EmploymentStatus', 'Delete:EmploymentStatus',
            'Restore:EmploymentStatus', 'ForceDelete:EmploymentStatus',
            'Replicate:EmploymentStatus', 'Reorder:EmploymentStatus',

            // Genders
            'ViewAny:Gender', 'View:Gender',
            'Create:Gender', 'Update:Gender', 'Delete:Gender',
            'Restore:Gender', 'ForceDelete:Gender',
            'Replicate:Gender', 'Reorder:Gender',

            // Religions
            'ViewAny:Religion', 'View:Religion',
            'Create:Religion', 'Update:Religion', 'Delete:Religion',
            'Restore:Religion', 'ForceDelete:Religion',
            'Replicate:Religion', 'Reorder:Religion',

            // Blood Groups
            'ViewAny:BloodGroup', 'View:BloodGroup',
            'Create:BloodGroup', 'Update:BloodGroup', 'Delete:BloodGroup',
            'Restore:BloodGroup', 'ForceDelete:BloodGroup',
            'Replicate:BloodGroup', 'Reorder:BloodGroup',

            // Countries
            'ViewAny:Country', 'View:Country',
            'Create:Country', 'Update:Country', 'Delete:Country',
            'Restore:Country', 'ForceDelete:Country',
            'Replicate:Country', 'Reorder:Country',

            // Degree Levels
            'ViewAny:DegreeLevel', 'View:DegreeLevel',
            'Create:DegreeLevel', 'Update:DegreeLevel', 'Delete:DegreeLevel',
            'Restore:DegreeLevel', 'ForceDelete:DegreeLevel',
            'Replicate:DegreeLevel', 'Reorder:DegreeLevel',

            // Degree Types
            'ViewAny:DegreeType', 'View:DegreeType',
            'Create:DegreeType', 'Update:DegreeType', 'Delete:DegreeType',
            'Restore:DegreeType', 'ForceDelete:DegreeType',
            'Replicate:DegreeType', 'Reorder:DegreeType',

            // Social Media Platforms
            'ViewAny:SocialMediaPlatform', 'View:SocialMediaPlatform',
            'Create:SocialMediaPlatform', 'Update:SocialMediaPlatform', 'Delete:SocialMediaPlatform',
            'Restore:SocialMediaPlatform', 'ForceDelete:SocialMediaPlatform',
            'Replicate:SocialMediaPlatform', 'Reorder:SocialMediaPlatform',

            // Membership Types
            'ViewAny:MembershipType', 'View:MembershipType',
            'Create:MembershipType', 'Update:MembershipType', 'Delete:MembershipType',
            'Restore:MembershipType', 'ForceDelete:MembershipType',
            'Replicate:MembershipType', 'Reorder:MembershipType',

            // Organizations
            'ViewAny:Organization', 'View:Organization',
            'Create:Organization', 'Update:Organization', 'Delete:Organization',
            'Restore:Organization', 'ForceDelete:Organization',
            'Replicate:Organization', 'Reorder:Organization',

            // Positions
            'ViewAny:Position', 'View:Position',
            'Create:Position', 'Update:Position', 'Delete:Position',
            'Restore:Position', 'ForceDelete:Position',
            'Replicate:Position', 'Reorder:Position',

            // Publications
            'ViewAny:Publication', 'View:Publication',
            'Create:Publication', 'Update:Publication', 'Delete:Publication',
            'Restore:Publication', 'ForceDelete:Publication',
            'ForceDeleteAny:Publication', 'RestoreAny:Publication',
            'Replicate:Publication', 'Reorder:Publication',

            // Publication Incentives
            'ViewAny:PublicationIncentive', 'View:PublicationIncentive',
            'Create:PublicationIncentive', 'Update:PublicationIncentive', 'Delete:PublicationIncentive',
            'Restore:PublicationIncentive', 'ForceDelete:PublicationIncentive',
            'ForceDeleteAny:PublicationIncentive', 'RestoreAny:PublicationIncentive',
            'Replicate:PublicationIncentive', 'Reorder:PublicationIncentive',

            // Incentive Logs
            'ViewAny:IncentiveLog', 'View:IncentiveLog',
            'Create:IncentiveLog', 'Update:IncentiveLog', 'Delete:IncentiveLog',
            'Restore:IncentiveLog', 'ForceDelete:IncentiveLog',
            'Replicate:IncentiveLog', 'Reorder:IncentiveLog',

            // Publication Types
            'ViewAny:PublicationType', 'View:PublicationType',
            'Create:PublicationType', 'Update:PublicationType', 'Delete:PublicationType',
            'Restore:PublicationType', 'ForceDelete:PublicationType',
            'ForceDeleteAny:PublicationType', 'RestoreAny:PublicationType',
            'Replicate:PublicationType', 'Reorder:PublicationType',

            // Publication Linkages
            'ViewAny:PublicationLinkage', 'View:PublicationLinkage',
            'Create:PublicationLinkage', 'Update:PublicationLinkage', 'Delete:PublicationLinkage',
            'Restore:PublicationLinkage', 'ForceDelete:PublicationLinkage',
            'ForceDeleteAny:PublicationLinkage', 'RestoreAny:PublicationLinkage',
            'Replicate:PublicationLinkage', 'Reorder:PublicationLinkage',

            // Publication Quartiles
            'ViewAny:PublicationQuartile', 'View:PublicationQuartile',
            'Create:PublicationQuartile', 'Update:PublicationQuartile', 'Delete:PublicationQuartile',
            'Restore:PublicationQuartile', 'ForceDelete:PublicationQuartile',
            'ForceDeleteAny:PublicationQuartile', 'RestoreAny:PublicationQuartile',
            'Replicate:PublicationQuartile', 'Reorder:PublicationQuartile',

            // Grant Types
            'ViewAny:GrantType', 'View:GrantType',
            'Create:GrantType', 'Update:GrantType', 'Delete:GrantType',
            'Restore:GrantType', 'ForceDelete:GrantType',
            'ForceDeleteAny:GrantType', 'RestoreAny:GrantType',
            'Replicate:GrantType', 'Reorder:GrantType',

            // Research Collaborations
            'ViewAny:ResearchCollaboration', 'View:ResearchCollaboration',
            'Create:ResearchCollaboration', 'Update:ResearchCollaboration', 'Delete:ResearchCollaboration',
            'Restore:ResearchCollaboration', 'ForceDelete:ResearchCollaboration',
            'ForceDeleteAny:ResearchCollaboration', 'RestoreAny:ResearchCollaboration',
            'Replicate:ResearchCollaboration', 'Reorder:ResearchCollaboration',

            // Research Projects
            'ViewAny:ResearchProject', 'View:ResearchProject',
            'Create:ResearchProject', 'Update:ResearchProject', 'Delete:ResearchProject',
            'Restore:ResearchProject', 'ForceDelete:ResearchProject',
            'Replicate:ResearchProject', 'Reorder:ResearchProject',

            // Authors
            'ViewAny:Author', 'View:Author',
            'Create:Author', 'Update:Author', 'Delete:Author',
            'Restore:Author', 'ForceDelete:Author',
            'Replicate:Author', 'Reorder:Author',

            // Author Types
            'ViewAny:AuthorType', 'View:AuthorType',
            'Create:AuthorType', 'Update:AuthorType', 'Delete:AuthorType',
            'Restore:AuthorType', 'ForceDelete:AuthorType',
            'Replicate:AuthorType', 'Reorder:AuthorType',

            // Result Types
            'ViewAny:ResultType', 'View:ResultType',
            'Create:ResultType', 'Update:ResultType', 'Delete:ResultType',
            'Restore:ResultType', 'ForceDelete:ResultType',
            'Replicate:ResultType', 'Reorder:ResultType',

            // Administrative Roles
            'ViewAny:AdministrativeRole', 'View:AdministrativeRole',
            'Create:AdministrativeRole', 'Update:AdministrativeRole', 'Delete:AdministrativeRole',
            'Restore:AdministrativeRole', 'ForceDelete:AdministrativeRole',
            'ForceDeleteAny:AdministrativeRole', 'RestoreAny:AdministrativeRole',
            'Replicate:AdministrativeRole', 'Reorder:AdministrativeRole',

            // User Administrative Roles
            'ViewAny:UserAdministrativeRole', 'View:UserAdministrativeRole',
            'Create:UserAdministrativeRole', 'Update:UserAdministrativeRole', 'Delete:UserAdministrativeRole',
            'Restore:UserAdministrativeRole', 'ForceDelete:UserAdministrativeRole',
            'ForceDeleteAny:UserAdministrativeRole', 'RestoreAny:UserAdministrativeRole',
            'Replicate:UserAdministrativeRole', 'Reorder:UserAdministrativeRole',

            // Users
            'ViewAny:User', 'View:User',
            'Create:User', 'Update:User', 'Delete:User',
            'Restore:User', 'ForceDelete:User',
            'ForceDeleteAny:User', 'RestoreAny:User',
            'Replicate:User', 'Reorder:User',

            // Roles
            'ViewAny:Role', 'View:Role',
            'Create:Role', 'Update:Role', 'Delete:Role',
            'Restore:Role', 'ForceDelete:Role',
            'Replicate:Role', 'Reorder:Role',

            // Settings / Approval Settings
            'ViewAny:Setting', 'View:Setting', 'Update:Setting', 'Restore:Setting',
            'ViewAny:ApprovalSetting', 'View:ApprovalSetting',
            'Create:ApprovalSetting', 'Update:ApprovalSetting', 'Delete:ApprovalSetting',
            'Restore:ApprovalSetting', 'ForceDelete:ApprovalSetting',
            'RestoreAny:ApprovalSetting', 'ForceDeleteAny:ApprovalSetting',
            'Replicate:ApprovalSetting', 'Reorder:ApprovalSetting',

            // Email Templates
            'ViewAny:EmailTemplate', 'View:EmailTemplate',
            'Create:EmailTemplate', 'Update:EmailTemplate', 'Delete:EmailTemplate',
            'Restore:EmailTemplate', 'ForceDelete:EmailTemplate',
            'Replicate:EmailTemplate', 'Reorder:EmailTemplate',

            // Notification Routings
            'ViewAny:NotificationRouting', 'View:NotificationRouting',
            'Create:NotificationRouting', 'Update:NotificationRouting', 'Delete:NotificationRouting',
            'Restore:NotificationRouting', 'ForceDelete:NotificationRouting',
            'Replicate:NotificationRouting', 'Reorder:NotificationRouting',

            // Integration Mappings
            'ViewAny:IntegrationMapping', 'View:IntegrationMapping',
            'Create:IntegrationMapping', 'Update:IntegrationMapping', 'Delete:IntegrationMapping',
            'Restore:IntegrationMapping', 'ForceDelete:IntegrationMapping',
            'Replicate:IntegrationMapping', 'Reorder:IntegrationMapping',

            // Majors / Field of Study
            'ViewAny:Major', 'View:Major',
            'Create:Major', 'Update:Major', 'Delete:Major',
            'Restore:Major', 'ForceDelete:Major',
            'Replicate:Major', 'Reorder:Major',
            'ViewAny:FieldOfStudy', 'View:FieldOfStudy',
            'Create:FieldOfStudy', 'Update:FieldOfStudy', 'Delete:FieldOfStudy',
            'Restore:FieldOfStudy', 'ForceDelete:FieldOfStudy',
            'Replicate:FieldOfStudy', 'Reorder:FieldOfStudy',

            // Approval permissions
            'approve:teacher-profile',
            'approve:own-department-teacher',
            'approve:own-faculty-teacher',
            'view:pending-approvals',
        ];

        // Create all permissions
        $this->command->info('Creating permissions...');
        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }
        $this->command->info('✔ Permissions created: ' . count($allPermissions));

        // ---------------------------------------------------------------
        // 2. Define role permission maps
        // ---------------------------------------------------------------

        // --- ADMIN ---
        // Full access to everything except super_admin-only items
        $adminPermissions = $allPermissions; // All permissions

        // --- REGISTRAR ---
        // Manage teachers, faculty, departments, lookups. Cannot manage users/roles/settings/publications incentives.
        $registrarPermissions = [
            'View:Dashboard',
            'View:TeacherDashboard',

            // Teachers - full CRUD
            'ViewAny:Teacher', 'View:Teacher',
            'Create:Teacher', 'Update:Teacher', 'Delete:Teacher',
            'Restore:Teacher', 'ForceDelete:Teacher',
            'ForceDeleteAny:Teacher', 'RestoreAny:Teacher',
            'Replicate:Teacher', 'Reorder:Teacher',

            // Teacher Versions - full CRUD
            'ViewAny:TeacherVersion', 'View:TeacherVersion',
            'Create:TeacherVersion', 'Update:TeacherVersion', 'Delete:TeacherVersion',
            'Restore:TeacherVersion', 'ForceDelete:TeacherVersion',
            'ForceDeleteAny:TeacherVersion', 'RestoreAny:TeacherVersion',

            // Department Teachers - full CRUD
            'ViewAny:DepartmentTeacher', 'View:DepartmentTeacher',
            'Create:DepartmentTeacher', 'Update:DepartmentTeacher', 'Delete:DepartmentTeacher',
            'Restore:DepartmentTeacher', 'ForceDelete:DepartmentTeacher',

            // Faculties
            'ViewAny:Faculty', 'View:Faculty',
            'Create:Faculty', 'Update:Faculty', 'Delete:Faculty',
            'Restore:Faculty', 'ForceDelete:Faculty',

            // Departments
            'ViewAny:Department', 'View:Department',
            'Create:Department', 'Update:Department', 'Delete:Department',
            'Restore:Department', 'ForceDelete:Department',

            // Designations
            'ViewAny:Designation', 'View:Designation',
            'Create:Designation', 'Update:Designation', 'Delete:Designation',
            'Restore:Designation', 'ForceDelete:Designation',

            // Job Types
            'ViewAny:JobType', 'View:JobType',
            'Create:JobType', 'Update:JobType', 'Delete:JobType',

            // Employment Statuses
            'ViewAny:EmploymentStatus', 'View:EmploymentStatus',
            'Create:EmploymentStatus', 'Update:EmploymentStatus', 'Delete:EmploymentStatus',

            // Degree Levels
            'ViewAny:DegreeLevel', 'View:DegreeLevel',
            'Create:DegreeLevel', 'Update:DegreeLevel', 'Delete:DegreeLevel',

            // Degree Types
            'ViewAny:DegreeType', 'View:DegreeType',
            'Create:DegreeType', 'Update:DegreeType', 'Delete:DegreeType',

            // Lookups (read + create for necessary references)
            'ViewAny:Gender', 'View:Gender', 'Create:Gender', 'Update:Gender',
            'ViewAny:Religion', 'View:Religion', 'Create:Religion', 'Update:Religion',
            'ViewAny:BloodGroup', 'View:BloodGroup', 'Create:BloodGroup', 'Update:BloodGroup',
            'ViewAny:Country', 'View:Country',
            'ViewAny:SocialMediaPlatform', 'View:SocialMediaPlatform', 'Create:SocialMediaPlatform', 'Update:SocialMediaPlatform',
            'ViewAny:MembershipType', 'View:MembershipType', 'Create:MembershipType', 'Update:MembershipType',
            'ViewAny:Organization', 'View:Organization', 'Create:Organization', 'Update:Organization',
            'ViewAny:Position', 'View:Position', 'Create:Position', 'Update:Position',
            'ViewAny:Major', 'View:Major', 'Create:Major', 'Update:Major',
            'ViewAny:FieldOfStudy', 'View:FieldOfStudy', 'Create:FieldOfStudy', 'Update:FieldOfStudy',
            'ViewAny:ResultType', 'View:ResultType', 'Create:ResultType', 'Update:ResultType',

            // Publications - view only
            'ViewAny:Publication', 'View:Publication',

            // Approval permissions
            'approve:teacher-profile',
            'view:pending-approvals',
        ];

        // --- DEAN ---
        // View teachers and publications for their faculty. View dept teachers. View-only publications/incentives.
        $deanPermissions = [
            'View:Dashboard',
            'View:TeacherDashboard',
            'View:PublicationStatsOverview',
            'View:PublicationYearWidget',
            'View:PublicationOverview',
            'View:PublicationQuartileWidget',
            'View:PublicationLinkageChart',
            'View:PublicationGrantTypeWidget',
            'View:PublicationTypeChart',

            // Teachers - view only (scoped by faculty in resource)
            'ViewAny:Teacher', 'View:Teacher',

            // Department Teachers - view only (scoped by faculty)
            'ViewAny:DepartmentTeacher', 'View:DepartmentTeacher',

            // Publications - view only (scoped by faculty)
            'ViewAny:Publication', 'View:Publication',

            // Publication Incentives - view only
            'ViewAny:PublicationIncentive', 'View:PublicationIncentive',

            // Incentive Logs - view only
            'ViewAny:IncentiveLog', 'View:IncentiveLog',

            // Lookup views for references
            'ViewAny:Faculty', 'View:Faculty',
            'ViewAny:Department', 'View:Department',
            'ViewAny:Designation', 'View:Designation',
            'ViewAny:JobType', 'View:JobType',
            'ViewAny:EmploymentStatus', 'View:EmploymentStatus',

            // Approval permissions
            'approve:own-faculty-teacher',
            'view:pending-approvals',
        ];

        // --- HEAD ---
        // Same as Dean but scoped to their department only
        $headPermissions = [
            'View:Dashboard',
            'View:TeacherDashboard',
            'View:PublicationStatsOverview',
            'View:PublicationYearWidget',
            'View:PublicationOverview',
            'View:PublicationQuartileWidget',
            'View:PublicationLinkageChart',
            'View:PublicationGrantTypeWidget',
            'View:PublicationTypeChart',

            // Teachers - view only (scoped by department)
            'ViewAny:Teacher', 'View:Teacher',

            // Department Teachers - view only (scoped by department)
            'ViewAny:DepartmentTeacher', 'View:DepartmentTeacher',

            // Publications - view only (scoped by department)
            'ViewAny:Publication', 'View:Publication',

            // Publication Incentives - view only
            'ViewAny:PublicationIncentive', 'View:PublicationIncentive',

            // Incentive Logs - view only
            'ViewAny:IncentiveLog', 'View:IncentiveLog',

            // Lookups
            'ViewAny:Faculty', 'View:Faculty',
            'ViewAny:Department', 'View:Department',
            'ViewAny:Designation', 'View:Designation',
            'ViewAny:JobType', 'View:JobType',
            'ViewAny:EmploymentStatus', 'View:EmploymentStatus',

            // Approval permissions
            'approve:own-department-teacher',
            'view:pending-approvals',
        ];

        // --- RESEARCH_TEAM ---
        // Full access to publication-related resources
        $researchTeamPermissions = [
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
            'View:PublicationTypeChart',

            // Teachers - view only
            'ViewAny:Teacher', 'View:Teacher',

            // Department Teachers - view only
            'ViewAny:DepartmentTeacher', 'View:DepartmentTeacher',

            // Publications - full CRUD
            'ViewAny:Publication', 'View:Publication',
            'Create:Publication', 'Update:Publication', 'Delete:Publication',
            'Restore:Publication', 'ForceDelete:Publication',
            'ForceDeleteAny:Publication', 'RestoreAny:Publication',
            'Replicate:Publication', 'Reorder:Publication',

            // Publication Incentives - full CRUD
            'ViewAny:PublicationIncentive', 'View:PublicationIncentive',
            'Create:PublicationIncentive', 'Update:PublicationIncentive', 'Delete:PublicationIncentive',

            // Incentive Logs - view only
            'ViewAny:IncentiveLog', 'View:IncentiveLog',

            // Publication Types - full CRUD
            'ViewAny:PublicationType', 'View:PublicationType',
            'Create:PublicationType', 'Update:PublicationType', 'Delete:PublicationType',
            'Restore:PublicationType', 'ForceDelete:PublicationType',
            'ForceDeleteAny:PublicationType', 'RestoreAny:PublicationType',
            'Replicate:PublicationType', 'Reorder:PublicationType',

            // Publication Linkages - full CRUD
            'ViewAny:PublicationLinkage', 'View:PublicationLinkage',
            'Create:PublicationLinkage', 'Update:PublicationLinkage', 'Delete:PublicationLinkage',
            'Restore:PublicationLinkage', 'ForceDelete:PublicationLinkage',
            'ForceDeleteAny:PublicationLinkage', 'RestoreAny:PublicationLinkage',
            'Replicate:PublicationLinkage', 'Reorder:PublicationLinkage',

            // Publication Quartiles - full CRUD
            'ViewAny:PublicationQuartile', 'View:PublicationQuartile',
            'Create:PublicationQuartile', 'Update:PublicationQuartile', 'Delete:PublicationQuartile',
            'Restore:PublicationQuartile', 'ForceDelete:PublicationQuartile',
            'ForceDeleteAny:PublicationQuartile', 'RestoreAny:PublicationQuartile',
            'Replicate:PublicationQuartile', 'Reorder:PublicationQuartile',

            // Grant Types - full CRUD
            'ViewAny:GrantType', 'View:GrantType',
            'Create:GrantType', 'Update:GrantType', 'Delete:GrantType',
            'Restore:GrantType', 'ForceDelete:GrantType',
            'ForceDeleteAny:GrantType', 'RestoreAny:GrantType',
            'Replicate:GrantType', 'Reorder:GrantType',

            // Research Collaborations - full CRUD
            'ViewAny:ResearchCollaboration', 'View:ResearchCollaboration',
            'Create:ResearchCollaboration', 'Update:ResearchCollaboration', 'Delete:ResearchCollaboration',
            'Restore:ResearchCollaboration', 'ForceDelete:ResearchCollaboration',
            'ForceDeleteAny:ResearchCollaboration', 'RestoreAny:ResearchCollaboration',
            'Replicate:ResearchCollaboration', 'Reorder:ResearchCollaboration',

            // Authors & Author Types
            'ViewAny:Author', 'View:Author',
            'Create:Author', 'Update:Author', 'Delete:Author',
            'Restore:Author', 'ForceDelete:Author',
            'ViewAny:AuthorType', 'View:AuthorType',
            'Create:AuthorType', 'Update:AuthorType', 'Delete:AuthorType',
        ];

        // --- ASSOCIATE_DEAN ---
        // Same as dean but no approval permission (secondary/assistant faculty role)
        $associateDeanPermissions = [
            'View:Dashboard',
            'View:TeacherDashboard',
            'View:PublicationStatsOverview',
            'View:PublicationYearWidget',
            'View:PublicationOverview',
            'View:PublicationQuartileWidget',
            'View:PublicationLinkageChart',
            'View:PublicationGrantTypeWidget',
            'View:PublicationTypeChart',

            // Teachers - view only (scoped by faculty)
            'ViewAny:Teacher', 'View:Teacher',

            // Department Teachers - view only (scoped by faculty)
            'ViewAny:DepartmentTeacher', 'View:DepartmentTeacher',

            // Publications - view only (scoped by faculty)
            'ViewAny:Publication', 'View:Publication',

            // Publication Incentives - view only
            'ViewAny:PublicationIncentive', 'View:PublicationIncentive',

            // Incentive Logs - view only
            'ViewAny:IncentiveLog', 'View:IncentiveLog',

            // Lookups
            'ViewAny:Faculty', 'View:Faculty',
            'ViewAny:Department', 'View:Department',
            'ViewAny:Designation', 'View:Designation',
            'ViewAny:JobType', 'View:JobType',
            'ViewAny:EmploymentStatus', 'View:EmploymentStatus',

            'view:pending-approvals',
        ];

        // --- ASSOCIATE_HEAD ---
        // Same as head but no approval permission (secondary/assistant department role)
        $associateHeadPermissions = [
            'View:Dashboard',
            'View:TeacherDashboard',
            'View:PublicationStatsOverview',
            'View:PublicationYearWidget',
            'View:PublicationOverview',
            'View:PublicationQuartileWidget',
            'View:PublicationLinkageChart',
            'View:PublicationGrantTypeWidget',
            'View:PublicationTypeChart',

            // Teachers - view only (scoped by department)
            'ViewAny:Teacher', 'View:Teacher',

            // Department Teachers - view only (scoped by department)
            'ViewAny:DepartmentTeacher', 'View:DepartmentTeacher',

            // Publications - view only (scoped by department)
            'ViewAny:Publication', 'View:Publication',

            // Publication Incentives - view only
            'ViewAny:PublicationIncentive', 'View:PublicationIncentive',

            // Incentive Logs - view only
            'ViewAny:IncentiveLog', 'View:IncentiveLog',

            // Lookups
            'ViewAny:Faculty', 'View:Faculty',
            'ViewAny:Department', 'View:Department',
            'ViewAny:Designation', 'View:Designation',
            'ViewAny:JobType', 'View:JobType',
            'ViewAny:EmploymentStatus', 'View:EmploymentStatus',

            'view:pending-approvals',
        ];

        // --- TEACHER ---
        // Only their own profile
        $teacherPermissions = [
            'View:MyProfile',
            'View:Dashboard',
            'View:TeacherDashboard',

            // Can view their own publications
            'ViewAny:Publication', 'View:Publication',
        ];

        // ---------------------------------------------------------------
        // 3. Assign permissions to roles
        // ---------------------------------------------------------------
        $rolePermissionMap = [
            'super_admin'    => $allPermissions,           // Explicitly assigned + auto-bypasses policies
            'admin'          => $adminPermissions,
            'registrar'      => $registrarPermissions,
            'dean'           => $deanPermissions,
            'associate_dean' => $associateDeanPermissions,
            'head'           => $headPermissions,
            'associate_head' => $associateHeadPermissions,
            'research_team'  => $researchTeamPermissions,
            'teacher'        => $teacherPermissions,
        ];

        foreach ($rolePermissionMap as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            // Only assign permissions that actually exist
            $validPermissions = collect($permissions)
                ->filter(fn($p) => Permission::where('name', $p)->where('guard_name', 'web')->exists())
                ->values()
                ->toArray();

            $role->syncPermissions($validPermissions);

            $this->command->info("✔ [{$roleName}] → " . count($validPermissions) . ' permissions assigned');
        }

        // Reset cache again after assignment
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('');
        $this->command->info('✅ RolePermissionsSeeder completed successfully!');
        $this->command->info('   super_admin: All permissions explicitly assigned + auto-bypasses policy checks in Filament.');
    }
}
