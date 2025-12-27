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
            ['name' => 'ResearchGate', 'icon_class' => 'fa-solid fa-graduation-cap', 'allow_multiple'=>false,'base_url' => 'https://researchgate.net/profile/'],
            ['name' => 'Google Scholar', 'icon_class' => 'fa-brands fa-google-scholar','allow_multiple'=>false, 'base_url' => 'https://scholar.google.com/citations?user='],
            ['name' => 'ORCID', 'icon_class' => 'fa-brands fa-orcid', 'allow_multiple'=>false,'base_url' => 'https://orcid.org/'],
            ['name' => 'Facebook', 'icon_class' => 'fab fa-facebook', 'allow_multiple'=>false,'base_url' => 'https://facebook.com/'],
            ['name' => 'LinkedIn', 'icon_class' => 'fab fa-linkedin', 'allow_multiple'=>false,'base_url' => 'https://linkedin.com/in/'],
            ['name' => 'Twitter (X)', 'icon_class' => 'fab fa-x-twitter', 'allow_multiple'=>false,'base_url' => 'https://x.com/'],
            ['name' => 'Instagram', 'icon_class' => 'fab fa-instagram', 'allow_multiple'=>false,'base_url' => 'https://instagram.com/'],
            ['name' => 'YouTube', 'icon_class' => 'fab fa-youtube', 'allow_multiple'=>false,'base_url' => 'https://youtube.com/'],
            ['name' => 'GitHub', 'icon_class' => 'fab fa-github', 'allow_multiple'=>false,'base_url' => 'https://github.com/'],
            ['name' => 'Website', 'icon_class' => 'fas fa-globe','allow_multiple'=>true, 'base_url' => null],
        ];

        foreach ($platforms as $index => $platform) {
            DB::table('social_media_platforms')->updateOrInsert(
                ['slug' => Str::slug($platform['name'])],
                [
                    'name' => $platform['name'],
                    'icon_class' => $platform['icon_class'],
                    'base_url' => $platform['base_url'],
                    'allow_multiple' => $platform['allow_multiple'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
