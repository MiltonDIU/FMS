<?php

namespace Database\Seeders;

use App\Models\AdministrativeRole;
use Illuminate\Database\Seeder;

class AdministrativeRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Vice Chancellor',
                'short_name' => 'VC',
                'scope' => 'university',
                'rank' => 1,
                'description' => 'Head of the University',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro Vice Chancellor',
                'short_name' => 'Pro-VC',
                'scope' => 'university',
                'rank' => 2,
                'description' => 'Deputy Head of the University',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Dean',
                'short_name' => 'Dean',
                'scope' => 'faculty',
                'rank' => 3,
                'description' => 'Head of a Faculty',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Associate Dean',
                'short_name' => 'Assoc. Dean',
                'scope' => 'faculty',
                'rank' => 4,
                'description' => 'Deputy Head of a Faculty',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Head of Department',
                'short_name' => 'HoD',
                'scope' => 'department',
                'rank' => 5,
                'description' => 'Head of a Department',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Associate Head',
                'short_name' => 'Assoc. Head',
                'scope' => 'department',
                'rank' => 6,
                'description' => 'Deputy Head of a Department',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Program Coordinator',
                'short_name' => 'PC',
                'scope' => 'program',
                'rank' => 7,
                'description' => 'Coordinator of a Program',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Exam Controller',
                'short_name' => 'EC',
                'scope' => 'university',
                'rank' => 8,
                'description' => 'Controller of Examinations',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Registrar',
                'short_name' => 'Reg.',
                'scope' => 'university',
                'rank' => 9,
                'description' => 'University Registrar',
                'is_active' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($roles as $role) {
            AdministrativeRole::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
