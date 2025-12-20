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
                'description' => 'Highest academic rank',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Associate Professor',
                'short_name' => 'Assoc. Prof.',
                'rank' => 2,
                'description' => 'Senior academic rank',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Assistant Professor',
                'short_name' => 'Asst. Prof.',
                'rank' => 3,
                'description' => 'Mid-level academic rank',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Senior Lecturer',
                'short_name' => 'Sr. Lect.',
                'rank' => 4,
                'description' => 'Senior teaching position',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Lecturer',
                'short_name' => 'Lect.',
                'rank' => 5,
                'description' => 'Entry-level teaching position',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Adjunct Faculty',
                'short_name' => 'Adj.',
                'rank' => 6,
                'description' => 'Part-time or visiting faculty',
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
