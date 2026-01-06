<?php

namespace Database\Seeders;

use App\Models\JobType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JobTypeSeeder extends Seeder
{
    public function run(): void
    {
        $jobTypes = [
            [
                'name' => 'Full Time',
                'slug' => 'full-time',
                'description' => 'Full-time permanent position',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Part Time',
                'slug' => 'part-time',
                'description' => 'Part-time position',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Adjunct Faculty',
                'slug' => 'adjunct-faculty',
                'description' => 'Adjunct or visiting faculty member',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Contractual',
                'slug' => 'contractual',
                'description' => 'Contract-based employment',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Visiting Faculty',
                'slug' => 'visiting-faculty',
                'description' => 'Temporary visiting faculty',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Emeritus',
                'slug' => 'emeritus',
                'description' => 'Retired professor with honorary title',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($jobTypes as $type) {
            JobType::updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }

        $this->command->info('Job types seeded successfully!');
    }
}
