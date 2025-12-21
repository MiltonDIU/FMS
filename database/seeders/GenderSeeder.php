<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenderSeeder extends Seeder
{
    public function run(): void
    {
        $genders = [
            ["name" => "Female", "active" => true],
            ["name" => "Male", "active" => true],
            ["name" => "Other", "active" => true],
        ];

        foreach ($genders as $data) {
            DB::table('genders')->updateOrInsert(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => trim($data['name']),
                    'is_active' => $data['active'],
                    'slug' => Str::slug($data['name']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
