<?php

namespace Database\Seeders;

use App\Filament\Resources\Genders\GenderResource;
use App\Models\DegreeLevel;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Existing seeder (keeps existing users)
        $this->call([
            FMSSeeder::class,
            SettingsSeeder::class,
            CountrySeeder::class,
            FacultySeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
            AdministrativeRoleSeeder::class,
            GenderSeeder::class,
            BloodGroupSeeder::class,
            ReligionSeeder::class,
            EmploymentStatusSeeder::class,
            JobTypeSeeder::class,
            SocialMediaPlatformSeeder::class,
            PublicationLookupSeeder::class,
            DegreeLevelSeeder::class,
            ResultTypeSeeder::class,
            DegreeTypeSeeder::class,
            MembershipTypeSeeder::class,
            MembershipOrganizationSeeder::class,
            TeacherSeeder::class,
            TeacherPermissionSeeder::class,
            ApprovalSettingsSeeder::class,
            ApprovalPermissionsSeeder::class,
            NotificationRoutingSeeder::class,
        ]);
    }
}
