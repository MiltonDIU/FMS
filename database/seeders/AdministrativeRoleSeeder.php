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
                'description' => 'Chief Executive Officer of the university responsible for overall academic and administrative leadership. Oversees strategic planning, policy implementation, external relations, and ensures the university achieves its mission and vision.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro Vice Chancellor',
                'short_name' => 'Pro-VC',
                'scope' => 'university',
                'rank' => 2,
                'description' => 'Deputy to the Vice Chancellor, assists in academic governance and administration. Responsible for specific portfolios such as academic affairs, research, or student affairs. Acts as VC in their absence.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Dean',
                'short_name' => 'Dean',
                'scope' => 'faculty',
                'rank' => 3,
                'description' => 'Academic and administrative head of a faculty. Responsible for faculty governance, curriculum development, faculty recruitment, budget management, and maintaining academic standards across all departments in the faculty.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Associate Dean',
                'short_name' => 'Assoc. Dean',
                'scope' => 'faculty',
                'rank' => 4,
                'description' => 'Assists the Dean in faculty administration with specific responsibilities such as academic programs, research, or student affairs. Represents the Dean in meetings and committees when needed.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Head of Department',
                'short_name' => 'HoD',
                'scope' => 'department',
                'rank' => 5,
                'description' => 'Academic and administrative leader of a department. Responsible for department operations, course scheduling, faculty workload, student issues, curriculum updates, and departmental budget. Reports to the Dean.',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Associate Head',
                'short_name' => 'Assoc. Head',
                'scope' => 'department',
                'rank' => 6,
                'description' => 'Assists the Head of Department with administrative duties. May focus on specific areas like student affairs, academic programs, or research coordination. Acts as HoD in their absence.',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Program Coordinator',
                'short_name' => 'PC',
                'scope' => 'program',
                'rank' => 7,
                'description' => 'Coordinates a specific academic program (e.g., MBA, MPhil). Responsible for program curriculum, student advising, course scheduling, and ensuring program quality and accreditation requirements.',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Exam Controller',
                'short_name' => 'EC',
                'scope' => 'university',
                'rank' => 8,
                'description' => 'Head of the examination department responsible for conducting all university examinations. Manages exam scheduling, question paper security, results processing, and certification. Ensures examination integrity.',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Registrar',
                'short_name' => 'Reg.',
                'scope' => 'university',
                'rank' => 9,
                'description' => 'Chief administrative officer managing university records, academic regulations, student enrollment, and official correspondence. Serves as secretary to academic councils and maintains university archives.',
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
