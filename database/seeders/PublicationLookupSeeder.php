<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\PublicationType;
use App\Models\PublicationLinkage;
use App\Models\PublicationQuartile;
use App\Models\GrantType;
use App\Models\ResearchCollaboration;

class PublicationLookupSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Publication Types.
         *
         * The first eight are the university's own wording. The rest come from
         * the PD export, which speaks Scopus: a Letter, an Erratum, a Data
         * Paper and an Editorial are not journal articles and had nowhere to
         * go, so 7,203 publications carried no type at all.
         *
         * Scopus names that mean something we already had — "Article",
         * "Conference paper", "Review" — are not rows here. They are synonyms,
         * resolved by PublicationTypeRule; adding them would split one kind of
         * output across two categories.
         */
        $types = [
            ['name' => 'Journal Article', 'slug' => 'journal-article', 'sort_order' => 1],
            ['name' => 'Conference Proceeding', 'slug' => 'conference-proceeding', 'sort_order' => 2],
            ['name' => 'Book', 'slug' => 'book', 'sort_order' => 3],
            ['name' => 'Book Chapter', 'slug' => 'book-chapter', 'sort_order' => 4],
            ['name' => 'Review Article', 'slug' => 'review-article', 'sort_order' => 5],
            ['name' => 'Report', 'slug' => 'report', 'sort_order' => 6],
            ['name' => 'Thesis', 'slug' => 'thesis', 'sort_order' => 7],
            ['name' => 'Patent', 'slug' => 'patent', 'sort_order' => 8],
            ...\App\Support\PublicationTypeRule::additionalTypes(),
        ];
        foreach ($types as $item) {
            PublicationType::firstOrCreate(['slug' => $item['slug']], $item);
        }

        // Publication Linkages
        $linkages = [
            ['name' => 'Scopus', 'slug' => 'scopus'],
            ['name' => 'Web of Science (WoS)', 'slug' => 'wos'],
            ['name' => 'Scopus & WoS', 'slug' => 'scopus-wos'],
            ['name' => 'UGC Listed', 'slug' => 'ugc-listed'],
            ['name' => 'Non-Indexed', 'slug' => 'non-indexed'],
        ];
        foreach ($linkages as $index => $item) {
            PublicationLinkage::firstOrCreate(['slug' => $item['slug']], array_merge($item, ['sort_order' => $index + 1]));
        }

        // Quartiles
        $quartiles = [
            ['name' => 'Q1', 'slug' => 'q1'],
            ['name' => 'Q2', 'slug' => 'q2'],
            ['name' => 'Q3', 'slug' => 'q3'],
            ['name' => 'Q4', 'slug' => 'q4'],
            // Not ranked in a quartile. Named N/A because that is what the
            // source writes; it was "N/Q" until the two were reconciled.
            ['name' => 'N/A', 'slug' => 'n-a'],
        ];
        foreach ($quartiles as $index => $item) {
            PublicationQuartile::firstOrCreate(['slug' => $item['slug']], array_merge($item, ['sort_order' => $index + 1]));
        }

        // Grant Types
        $grants = [
            ['name' => 'DIU Project', 'slug' => 'diu-project', 'sort_order' => 1],
            ['name' => 'External Project', 'slug' => 'external-project', 'sort_order' => 2],
            ['name' => 'Self Funded', 'slug' => 'self-funded', 'sort_order' => 3],
            ['name' => 'Govt. Funded', 'slug' => 'govt-funded', 'sort_order' => 4],
            // Not a funder: where a publication lands when nothing on record
            // names one. Last in every dropdown, hence the gap in sort_order.
            ['name' => 'Not Assigned', 'slug' => 'not-assigned', 'sort_order' => 99],
        ];
        foreach ($grants as $item) {
            GrantType::firstOrCreate(['slug' => $item['slug']], $item);
        }

        // Research Collaborations
        $collabs = [
            ['name' => 'DIU Researcher', 'slug' => 'diu-researcher'],
            ['name' => 'Collaboration (External)', 'slug' => 'collaboration-external'],
            ['name' => 'Visiting Faculty + DIU Researcher', 'slug' => 'visiting-faculty-diu'],
        ];
        foreach ($collabs as $index => $item) {
            ResearchCollaboration::firstOrCreate(['slug' => $item['slug']], array_merge($item, ['sort_order' => $index + 1]));
        }
    }
}
