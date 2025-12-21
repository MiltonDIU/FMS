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
            ["name" => "A+", "active" => true],
            ["name" => "A-", "active" => true],
            ["name" => "AB+", "active" => true],
            ["name" => "AB-", "active" => true],
            ["name" => "B+", "active" => true],
            ["name" => "B-", "active" => true],
            ["name" => "O+", "active" => true],
            ["name" => "O-", "active" => true],
        ];

        foreach ($bloodGroups as $data) {
            DB::table('blood_groups')->updateOrInsert(
                // For BloodGroup, slug might be tricky (A+ -> a-).
                // Str::slug('A+') is empty or weird?
                // Str::slug('A+') is 'a'. Wait.
                // Str::slug('A+') -> 'a' (removes +).
                // Str::slug('A-') -> 'a' (removes -? No, space).
                // Laravel Str::slug uses helper. ASCII.
                // I should verify slug generation for symbols.
                // If slug is duplicate, it fails unique constraint.
                // A+ and A- might duplicate if slug removes +-.
                // I'll assume name is unique.
                // But duplicate slugs will crash.
                // I'll check if I should use custom slug logic for BloodGroups or just 'name' as slug?
                // User provided code "A+".
                // I'll use custom logic: replace + with 'pos', - with 'neg' before slugging?
                // Or just use name as slug if allowed? No, slug should be URL friendly.
                // Let's safe-guard: Str::slug($data['name']) . '-' . ($data['name'] == 'A+' ? 'pos' : ...).
                // Let's check Str::slug('A+') behavior.
                // It usually returns 'a' or empty.
                // I will add custom mapping for blood groups.
                ['name' => $data['name']],
                [
                    'name' => trim($data['name']),
                    'is_active' => $data['active'],
                    'slug' => $this->generateSlug($data['name']),
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
