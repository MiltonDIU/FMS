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
            ["name" => "Female", "code"=>"F", "active" => true],
            ["name" => "Male","code"=>"M", "active" => true],
        ];

        foreach ($genders as $index=> $data) {
            DB::table('genders')->updateOrInsert(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => trim($data['name']),
                    'code' => trim($data['code']),
                    'is_active' => $data['active'],
                    'slug' => Str::slug($data['name']),
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
