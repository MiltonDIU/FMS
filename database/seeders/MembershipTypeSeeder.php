<?php

namespace Database\Seeders;

use App\Models\MembershipType;
use Illuminate\Database\Seeder;

class MembershipTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Student', 'description' => 'Student membership', 'sort_order' => 1],
            ['name' => 'Graduate Student', 'description' => 'Graduate student membership', 'sort_order' => 2],
            ['name' => 'Professional', 'description' => 'Professional membership', 'sort_order' => 3],
            ['name' => 'Senior', 'description' => 'Senior membership', 'sort_order' => 4],
            ['name' => 'Fellow', 'description' => 'Fellow membership', 'sort_order' => 5],
            ['name' => 'Life', 'description' => 'Life membership', 'sort_order' => 6],
            ['name' => 'Honorary', 'description' => 'Honorary membership', 'sort_order' => 7],
        ];

        foreach ($types as $type) {
            MembershipType::firstOrCreate(
                ['name' => $type['name']],
                $type + ['is_active' => true]
            );
        }
    }
}
