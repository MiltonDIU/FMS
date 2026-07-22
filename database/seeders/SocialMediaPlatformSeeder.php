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
            // Research & Academic Platforms
            ['name' => 'Google Scholar', 'icon_class' => 'fa-brands fa-google-scholar', 'allow_multiple' => '0', 'base_url' => 'https://scholar.google.com/citations?user='],
            ['name' => 'ResearchGate', 'icon_class' => 'fa-brands fa-researchgate', 'allow_multiple' => '0', 'base_url' => 'https://www.researchgate.net/profile/'],
            ['name' => 'ORCID', 'icon_class' => 'fa-brands fa-orcid', 'allow_multiple' => '0', 'base_url' => 'https://orcid.org/'],
            ['name' => 'Scopus', 'icon_class' => 'fa-solid fa-book-bookmark', 'allow_multiple' => '0', 'base_url' => 'https://www.scopus.com/authid/detail.uri?authorId='],
            ['name' => 'Web of Science', 'icon_class' => 'fa-solid fa-flask', 'allow_multiple' => '0', 'base_url' => 'https://www.webofscience.com/wos/author/record/'],
            ['name' => 'IEEE Xplore', 'icon_class' => 'fa-solid fa-microchip', 'allow_multiple' => '0', 'base_url' => 'https://ieeexplore.ieee.org/author/'],
            ['name' => 'SSRN', 'icon_class' => 'fa-solid fa-file-lines', 'allow_multiple' => '0', 'base_url' => 'https://papers.ssrn.com/sol3/cf_dev/AbsByAuth.cfm?per_id='],
            ['name' => 'Academia.edu', 'icon_class' => 'fa-solid fa-university', 'allow_multiple' => '0', 'base_url' => 'https://independent.academia.edu/'],
            ['name' => 'Semantic Scholar', 'icon_class' => 'fa-solid fa-brain', 'allow_multiple' => '0', 'base_url' => 'https://www.semanticscholar.org/author/'],
            ['name' => 'Loop (Frontiers)', 'icon_class' => 'fa-solid fa-circle-nodes', 'allow_multiple' => '0', 'base_url' => 'https://loop.frontiersin.org/people/'],
            ['name' => 'DBLP', 'icon_class' => 'fa-solid fa-database', 'allow_multiple' => '0', 'base_url' => 'https://dblp.org/pid/'],
            ['name' => 'arXiv', 'icon_class' => 'fa-solid fa-file-code', 'allow_multiple' => '0', 'base_url' => 'https://arxiv.org/a/'],
            ['name' => 'PhilPeople', 'icon_class' => 'fa-solid fa-book-open', 'allow_multiple' => '0', 'base_url' => 'https://philpeople.org/profiles/'],
            ['name' => 'Dimensions', 'icon_class' => 'fa-solid fa-chart-pie', 'allow_multiple' => '0', 'base_url' => 'https://app.dimensions.ai/discover/publication?and_facet_researcher=ur.'],
            ['name' => 'PubMed', 'icon_class' => 'fa-solid fa-notes-medical', 'allow_multiple' => '0', 'base_url' => 'https://pubmed.ncbi.nlm.nih.gov/?term='],

            // Technical & Professional Platforms
            ['name' => 'GitHub', 'icon_class' => 'fab fa-github', 'allow_multiple' => '0', 'base_url' => 'https://github.com/'],
            ['name' => 'Kaggle', 'icon_class' => 'fab fa-kaggle', 'allow_multiple' => '0', 'base_url' => 'https://www.kaggle.com/'],
            ['name' => 'LinkedIn', 'icon_class' => 'fab fa-linkedin', 'allow_multiple' => '0', 'base_url' => 'https://linkedin.com/in/'],

            // Media & Social Platforms
            ['name' => 'Twitter (X)', 'icon_class' => 'fab fa-x-twitter', 'allow_multiple' => '0', 'base_url' => 'https://x.com/'],
            ['name' => 'Facebook', 'icon_class' => 'fab fa-facebook', 'allow_multiple' => '0', 'base_url' => 'https://facebook.com/'],
            ['name' => 'YouTube', 'icon_class' => 'fab fa-youtube', 'allow_multiple' => '0', 'base_url' => 'https://youtube.com/'],
            ['name' => 'Instagram', 'icon_class' => 'fab fa-instagram', 'allow_multiple' => '0', 'base_url' => 'https://instagram.com/'],
            ['name' => 'Medium', 'icon_class' => 'fab fa-medium', 'allow_multiple' => '0', 'base_url' => 'https://medium.com/@'],
            ['name' => 'Substack', 'icon_class' => 'fa-solid fa-bookmark', 'allow_multiple' => '0', 'base_url' => 'https://substack.com/@'],

            // General Website / Portfolio
            ['name' => 'Website', 'icon_class' => 'fas fa-globe', 'allow_multiple' => '1', 'base_url' => null],
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
