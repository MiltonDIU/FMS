<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\DegreeLevel;

class DegreeLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $degreeLevels = [
            [
                'name' => 'High School',
                'slug' => 'high-school',
                'sort_order' => 1,
                'description' => 'Secondary/High School Education',
                'is_active' => true,
            ],
            [
                'name' => 'Diploma',
                'slug' => 'diploma',
                'sort_order' => 2,
                'description' => 'Diploma or Certificate Programs',
                'is_active' => true,
            ],
            [
                'name' => 'Associate',
                'slug' => 'associate',
                'sort_order' => 3,
                'description' => 'Associate Degree (2-year program)',
                'is_active' => true,
            ],
            [
                'name' => 'Bachelor',
                'slug' => 'bachelor',
                'sort_order' => 4,
                'description' => 'Undergraduate Degree (4-year program)',
                'is_active' => true,
            ],
            [
                'name' => 'Master',
                'slug' => 'master',
                'sort_order' => 5,
                'description' => 'Postgraduate/Masters Degree',
                'is_active' => true,
            ],
            [
                'name' => 'Doctoral',
                'slug' => 'doctoral',
                'sort_order' => 6,
                'description' => 'Doctoral Degree (PhD)',
                'is_active' => true,
            ],
            [
                'name' => 'Post-Doctoral',
                'slug' => 'post-doctoral',
                'sort_order' => 7,
                'description' => 'Post-Doctoral Research/Fellowship',
                'is_active' => true,
            ],
            [
                'name' => 'Professional Certification',
                'slug' => 'professional-certification',
                'sort_order' => 8,
                'description' => 'Professional Certifications and Licenses',
                'is_active' => true,
            ],
        ];

        foreach ($degreeLevels as $level) {
            // Ensure slug is generated if not provided
            if (!isset($level['slug']) || empty($level['slug'])) {
                $level['slug'] = Str::slug($level['name']);
            }

            DegreeLevel::updateOrCreate(
                ['slug' => $level['slug']],
                $level
            );
        }
    }
}
