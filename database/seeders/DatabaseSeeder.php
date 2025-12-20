<?php

namespace Database\Seeders;

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
        ]);

        // FMS Core Table Seeders
        $this->call([
            FacultySeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
            AdministrativeRoleSeeder::class,
            TeacherSeeder::class,
            TeacherPermissionSeeder::class,
        ]);
    }
}
