<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ResultType;

class ResultTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resultTypes = [
            [
                'type_name' => 'CGPA',
                'description' => 'Cumulative Grade Point Average (on a 4.0, 5.0 or other scale)',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type_name' => 'GPA',
                'description' => 'Grade Point Average (semester/year-wise)',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type_name' => 'Percentage',
                'description' => 'Percentage based grading system (out of 100%)',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type_name' => 'Grade',
                'description' => 'Letter grade or class system (A, B, C, First Class, etc.)',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type_name' => 'Division',
                'description' => 'Division system (First, Second, Third, Pass)',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type_name' => 'Pass/Fail',
                'description' => 'Pass or Fail only (no grading)',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'type_name' => 'Out of',
                'description' => 'Marks out of total (e.g., 850 out of 1100)',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'type_name' => 'Rank',
                'description' => 'Rank or position in class/board',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'type_name' => 'Points',
                'description' => 'Points system (used in some international boards)',
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'type_name' => 'Not Applicable',
                'description' => 'No result/grading system applicable',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'type_name' => 'Credits',
                'description' => 'Credit hours or credit-based system',
                'sort_order' => 11,
                'is_active' => true,
            ],
            [
                'type_name' => 'Honours',
                'description' => 'Honours classification (1st, 2:1, 2:2, 3rd)',
                'sort_order' => 12,
                'is_active' => true,
            ],
            [
                'type_name' => 'Appearing',
                'description' => 'Result is yet to be published or currently appearing',
                'sort_order' => 13,
                'is_active' => true,
            ],
        ];

        foreach ($resultTypes as $type) {
            ResultType::updateOrCreate(
                ['type_name' => $type['type_name']],
                $type
            );
        }


    }
}
