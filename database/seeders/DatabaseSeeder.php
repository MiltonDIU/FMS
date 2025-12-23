<?php

namespace Database\Seeders;

use App\Filament\Resources\Genders\GenderResource;
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
            FacultySeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
            AdministrativeRoleSeeder::class,
            NationalitySeeder::class,
            GenderSeeder::class,
            BloodGroupSeeder::class,
            ReligionSeeder::class,
            SocialMediaPlatformSeeder::class,

            TeacherSeeder::class,
            TeacherPermissionSeeder::class,
        ]);
    }
}
