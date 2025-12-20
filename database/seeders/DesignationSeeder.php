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
            [
                'name' => 'Senior Lecturer',
                'short_name' => 'Sr. Lect.',
                'rank' => 4,
                'description' => 'Experienced teaching position requiring Master\'s degree with 5+ years of teaching experience. Primarily focused on teaching excellence, course development, student mentoring, and may supervise undergraduate projects. Research involvement is encouraged.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Lecturer',
                'short_name' => 'Lect.',
                'rank' => 5,
                'description' => 'Entry-level teaching position requiring Master\'s degree. Responsibilities include teaching undergraduate courses, assisting in laboratory sessions, grading assignments, and contributing to departmental activities. Encouraged to pursue higher degrees.',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Adjunct Faculty',
                'short_name' => 'Adj.',
                'rank' => 6,
                'description' => 'Part-time or visiting faculty position for industry professionals or academics from other institutions. Responsibilities include teaching specific courses, sharing industry expertise, and providing practical insights to students.',
                'is_active' => true,
                'sort_order' => 6,
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
