<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BloodGroupSeeder extends Seeder
{
    public function run(): void
    {
        $bloodGroups = [
            ["name" => "A+","code"=>"A+", "active" => true],
            ["name" => "A-","code"=>"A-", "active" => true],
            ["name" => "AB+","code"=>"AB+", "active" => true],
            ["name" => "AB-","code"=>"AB-", "active" => true],
            ["name" => "B+", "code"=>"B+","active" => true],
            ["name" => "B-", "code"=>"B-","active" => true],
            ["name" => "O+", "code"=>"O+","active" => true],
            ["name" => "O-", "code"=>"O-","active" => true],
        ];

        foreach ($bloodGroups as $index=> $data) {
            DB::table('blood_groups')->updateOrInsert(
                ['name' => $data['name']],
                [
                    'name' => trim($data['name']),
                    'code' => trim($data['code']),
                    'is_active' => $data['active'],
                    'slug' => $this->generateSlug($data['name']),
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function generateSlug($name)
    {
        $map = [
            '+' => '-positive',
            '-' => '-negative',
        ];
        return Str::slug(str_replace(array_keys($map), array_values($map), $name));
    }
}
