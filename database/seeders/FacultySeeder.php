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
                'description' => 'Faculty of Business & Entrepreneurship at Daffodil International University',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Faculty of Science and Information Technology',
                'short_name' => 'FSIT',
                'code' => 'FSIT',
                'description' => 'Faculty of Science and Information Technology at Daffodil International University',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Faculty of Engineering',
                'short_name' => 'FE',
                'code' => 'FE',
                'description' => 'Faculty of Engineering at Daffodil International University',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Faculty of Health and Life Sciences',
                'short_name' => 'FHLS',
                'code' => 'FHLS',
                'description' => 'Faculty of Health and Life Sciences at Daffodil International University',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Faculty of Humanities & Social Sciences',
                'short_name' => 'FHSS',
                'code' => 'FHSS',
                'description' => 'Faculty of Humanities & Social Sciences at Daffodil International University',
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
