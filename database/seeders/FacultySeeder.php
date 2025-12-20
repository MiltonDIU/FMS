<?php

namespace Database\Seeders;

use App\Models\Faculty;
use Illuminate\Database\Seeder;

class FacultySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faculties = [
            [
                'name' => 'Faculty of Business & Entrepreneurship',
                'short_name' => 'FBE',
                'code' => 'FBE',
                'description' => 'The Faculty of Business & Entrepreneurship focuses on developing future business leaders and entrepreneurs. It offers programs in Business Administration, Management, Marketing, Finance, Accounting, and Innovation & Entrepreneurship with emphasis on practical skills and industry exposure.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Faculty of Science and Information Technology',
                'short_name' => 'FSIT',
                'code' => 'FSIT',
                'description' => 'The Faculty of Science and Information Technology is dedicated to producing skilled IT professionals and researchers. It covers Computer Science, Software Engineering, Multimedia & Creative Technology, and Information Systems with state-of-the-art labs and industry partnerships.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Faculty of Engineering',
                'short_name' => 'FE',
                'code' => 'FE',
                'description' => 'The Faculty of Engineering provides comprehensive engineering education in fields like Electrical & Electronic Engineering, Civil Engineering, Textile Engineering, and Architecture. Students gain hands-on experience through modern laboratories and industry collaborations.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Faculty of Health and Life Sciences',
                'short_name' => 'FHLS',
                'code' => 'FHLS',
                'description' => 'The Faculty of Health and Life Sciences focuses on healthcare and biological sciences education. It offers programs in Pharmacy, Public Health, Nutrition, Environmental Science, and Biotechnology, preparing students for careers in healthcare and research.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Faculty of Humanities & Social Sciences',
                'short_name' => 'FHSS',
                'code' => 'FHSS',
                'description' => 'The Faculty of Humanities & Social Sciences nurtures critical thinking and communication skills. It offers programs in English, Law, Journalism & Media Communication, and Development Studies, preparing graduates for diverse careers in society and governance.',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($faculties as $faculty) {
            Faculty::updateOrCreate(
                ['code' => $faculty['code']],
                $faculty
            );
        }
    }
}
