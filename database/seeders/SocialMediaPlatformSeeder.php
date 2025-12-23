<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialMediaPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['name' => 'ResearchGate', 'icon_class' => 'fa-solid fa-graduation-cap', 'base_url' => 'https://researchgate.net/profile/'],
            ['name' => 'Google Scholar', 'icon_class' => 'fa-brands fa-google-scholar', 'base_url' => 'https://scholar.google.com/citations?user='],
            ['name' => 'ORCID', 'icon_class' => 'fa-brands fa-orcid', 'base_url' => 'https://orcid.org/'],
            ['name' => 'Facebook', 'icon_class' => 'fab fa-facebook', 'base_url' => 'https://facebook.com/'],
            ['name' => 'LinkedIn', 'icon_class' => 'fab fa-linkedin', 'base_url' => 'https://linkedin.com/in/'],
            ['name' => 'Twitter (X)', 'icon_class' => 'fab fa-x-twitter', 'base_url' => 'https://x.com/'],
            ['name' => 'Instagram', 'icon_class' => 'fab fa-instagram', 'base_url' => 'https://instagram.com/'],
            ['name' => 'YouTube', 'icon_class' => 'fab fa-youtube', 'base_url' => 'https://youtube.com/'],
            ['name' => 'GitHub', 'icon_class' => 'fab fa-github', 'base_url' => 'https://github.com/'],
            ['name' => 'Website', 'icon_class' => 'fas fa-globe', 'base_url' => null],
        ];

        foreach ($platforms as $index => $platform) {
            DB::table('social_media_platforms')->updateOrInsert(
                ['slug' => Str::slug($platform['name'])],
                [
                    'name' => $platform['name'],
                    'icon_class' => $platform['icon_class'],
                    'base_url' => $platform['base_url'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
