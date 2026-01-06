<?php

namespace Database\Seeders;

use App\Models\EmploymentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmploymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Active',
                'slug' => 'active',
                'description' => 'Currently employed and working',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'On Leave',
                'slug' => 'on-leave',
                'description' => 'On temporary leave',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Study Leave',
                'slug' => 'study-leave',
                'description' => 'On study leave for higher education',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Deputation',
                'slug' => 'deputation',
                'description' => 'On deputation to another institution',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Retired',
                'slug' => 'retired',
                'description' => 'Retired from service',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Resigned',
                'slug' => 'resigned',
                'description' => 'Resigned from the position',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Terminated',
                'slug' => 'terminated',
                'description' => 'Employment terminated',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Suspended',
                'slug' => 'suspended',
                'description' => 'Currently suspended',
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($statuses as $status) {
            EmploymentStatus::updateOrCreate(
                ['slug' => $status['slug']],
                $status
            );
        }

        $this->command->info('Employment statuses seeded successfully!');
    }
}
