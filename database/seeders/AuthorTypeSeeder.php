<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuthorTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $authorTypes = [
            ['name' => 'VF', 'is_active' => true],
            ['name' => 'GA', 'is_active' => true],
            ['name' => 'SA', 'is_active' => true],
        ];

        foreach ($authorTypes as $data) {
            DB::table('author_types')->updateOrInsert(
                ['name' => $data['name']],
                [
                    'name' => trim($data['name']),
                    'is_active' => $data['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
