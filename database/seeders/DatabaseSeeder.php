<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Everything a fresh install needs, in one command.
 *
 *     php artisan db:seed                  on a new setup
 *     php artisan migrate:fresh --seed     to start over
 *
 * The rule for this file: if a seeder produces something the system cannot run
 * without, it belongs here. Two seeders deliberately do not — they are named at
 * the bottom rather than left out silently, so their absence reads as a decision
 * instead of an oversight.
 *
 * Order matters. Roles come before the permission seeders that grant to them,
 * and lookup tables before the records that point at them.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // ── Foundation: users, roles, permissions ───────────────────────
            // FMSSeeder creates the roles everything below grants to, so nothing
            // here can move above it.
            FMSSeeder::class,
            SystemSettingsPermissionSeeder::class,
            BulkDeletePermissionSeeder::class,
            TeacherPermissionSeeder::class,
            ApprovalPermissionsSeeder::class,
            RoleSevenPermissionSeeder::class,
            RolePermissionsSeeder::class,
            ActivityLogPermissionSeeder::class,

            // ── Configuration ───────────────────────────────────────────────
            SettingsSeeder::class,
            BrandingSettingsSeeder::class,
            ThemeSettingsSeeder::class,
            IntegrationMappingSeeder::class,
            NotificationRoutingSeeder::class,
            ApprovalSettingsSeeder::class,

            // ── Email templates ─────────────────────────────────────────────
            // AccountActivation is kept apart from the other three so that
            // re-running it cannot overwrite wording the administration edited.
            EmailTemplateSeeder::class,
            AccountActivationTemplateSeeder::class,

            // ── Lookup tables ───────────────────────────────────────────────
            CountrySeeder::class,
            GenderSeeder::class,
            BloodGroupSeeder::class,
            ReligionSeeder::class,
            EmploymentStatusSeeder::class,
            JobTypeSeeder::class,
            SocialMediaPlatformSeeder::class,
            DegreeLevelSeeder::class,
            DegreeTypeSeeder::class,
            ResultTypeSeeder::class,
            MembershipTypeSeeder::class,
            MembershipOrganizationSeeder::class,
            PublicationLookupSeeder::class,
            AuthorTypeSeeder::class,

            // ── Academic structure ──────────────────────────────────────────
            FacultySeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
            AdministrativeRoleSeeder::class,
            AdministrativeRoleUserSeeder::class,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Deliberately not called
    |--------------------------------------------------------------------------
    | TeacherSeeder and PublicationSeeder invent their contents with Faker —
    | fifty publications with generated abstracts, teachers with generated
    | names. They are useful for looking at a populated screen while developing,
    | and would be actively harmful on a real installation, where the data
    | arrives by migration from the legacy system instead.
    |
    | Run them by hand when a demo is what is wanted:
    |
    |     php artisan db:seed --class=TeacherSeeder
    |     php artisan db:seed --class=PublicationSeeder
    |--------------------------------------------------------------------------
    */
}
