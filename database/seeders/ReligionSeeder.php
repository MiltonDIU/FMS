<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReligionSeeder extends Seeder
{
    public function run(): void
    {
        $religions = [
            ["name" => "Buddhist", "active" => true],
            ["name" => "Christian", "active" => true],
            ["name" => "Hindu", "active" => true],
            ["name" => "Islam", "active" => true],
            ["name" => "Jewish", "active" => true],
            ["name" => "Sikh", "active" => true],
        ];

        foreach ($religions as $data) {
            DB::table('religions')->updateOrInsert(
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
