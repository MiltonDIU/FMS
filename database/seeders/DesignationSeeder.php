<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $designations = [
            [
                'name' => 'Professor',
                'short_name' => 'Prof.',
                'rank' => 1,
                'description' => 'Highest academic rank requiring Ph.D. with 15+ years of teaching experience. Responsibilities include leading research programs, mentoring junior faculty, curriculum development, and representing the department in academic bodies. Expected to have significant publications and research grants.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Associate Professor',
                'short_name' => 'Assoc. Prof.',
                'rank' => 2,
                'description' => 'Senior academic position requiring Ph.D. with 10+ years of experience. Responsibilities include conducting research, supervising graduate students, teaching graduate/undergraduate courses, and contributing to departmental administration. Expected to have regular publications.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Assistant Professor',
                'short_name' => 'Asst. Prof.',
                'rank' => 3,
                'description' => 'Mid-level academic position requiring Ph.D. or terminal degree with 5+ years of experience. Responsibilities include teaching undergraduate/graduate courses, conducting research, publishing papers, and participating in departmental activities.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            /*
             * Senior Lecturer and Lecturer (Senior Scale) are one grade under two
             * names: the title the university awarded before, and the one it
             * awards now. Both are kept because the people holding the old title
             * still hold it and the HR system still sends it, and both carry the
             * same rank and the same sort_order so a department lists them
             * together rather than as two separate blocks.
             */
            [
                'name' => 'Senior Lecturer',
                'short_name' => 'Sr. Lect.',
                'rank' => 4,
                'description' => 'Experienced teaching position requiring Master\'s degree with 5+ years of teaching experience. Primarily focused on teaching excellence, course development, student mentoring, and may supervise undergraduate projects. Research involvement is encouraged. Superseded by Lecturer (Senior Scale); kept for the faculty members who hold this title.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Lecturer (Senior Scale)',
                'short_name' => 'Lect. (Sr. Scale)',
                'rank' => 5,
                'description' => 'Lecturer on the senior scale: the same tier as Lecturer, one pay scale above it. This is the grade the university awards now where it formerly awarded Senior Lecturer.',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Lecturer',
                'short_name' => 'Lect.',
                'rank' => 6,
                'description' => 'Entry-level teaching position requiring Master\'s degree. Responsibilities include teaching undergraduate courses, assisting in laboratory sessions, grading assignments, and contributing to departmental activities. Encouraged to pursue higher degrees.',
                'is_active' => true,
                'sort_order' => 6,
            ],
            /*
             * "Adjunct Faculty" used to be on this list and is deliberately not
             * any more: it is not a grade the university awards but how somebody
             * is engaged, and job_types carries that — Regular, Part Time,
             * Adjunct Faculty, Contractual, Visiting Faculty, Emeritus. An
             * existing installation has it retired by the
             * retire_adjunct_faculty_designation migration, which moves those
             * teachers' adjunct status into their job type first.
             *
             * The row below is the one exception to this table holding only
             * ranks, and is_rank = false says so. It exists because
             * teachers.designation_id is NOT NULL and a record that names no
             * grade still needs a value; such a teacher is shown by their job
             * type instead — see Teacher::getDesignationTitleAttribute().
             */
            [
                'name' => 'System - Unassigned Designation',
                'short_name' => 'SUD.',
                'rank' => 20,
                'is_rank' => false,
                'description' => 'System - Unassigned Designation',
                'is_active' => false,
                'sort_order' => 20,
            ],
        ];

        foreach ($designations as $designation) {
            Designation::updateOrCreate(
                ['name' => $designation['name']],
                $designation
            );
        }
    }
}
