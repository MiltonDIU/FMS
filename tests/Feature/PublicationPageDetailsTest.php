<?php

namespace Tests\Feature;

use App\Helpers\Seo;
use App\Helpers\Theme;
use App\Models\GrantType;
use App\Models\Publication;
use App\Models\PublicationLinkage;
use App\Models\PublicationQuartile;
use App\Models\ResearchCollaboration;
use App\Models\Setting;
use App\Models\Teacher;
use Tests\TestCase;

/**
 * What a publication's own page shows.
 *
 * Every fact the record carries that a reader can use — and only when the
 * paper has one. Grant type and collaboration stay off the page, as do the
 * record's own settings.
 */
class PublicationPageDetailsTest extends TestCase
{
    public function test_keywords_are_split_on_either_separator_and_cleaned(): void
    {
        $publication = new Publication(['keywords' => '"Deep Learning , Resnet50; resnet50 ,   Mobile  Net ;; Phishing attack >"']);

        $this->assertSame(['Deep Learning', 'Resnet50', 'Mobile Net', 'Phishing attack'], $publication->keywordList());
    }

    public function test_keywords_with_no_separator_stay_one_entry(): void
    {
        // Not guessed apart at its spaces.
        $publication = new Publication(['keywords' => 'microstrip feed line']);

        $this->assertSame(['microstrip feed line'], $publication->keywordList());
        $this->assertSame([], (new Publication(['keywords' => '  ']))->keywordList());
    }

    public function test_only_links_that_open_are_offered(): void
    {
        $publication = new Publication([
            'doi' => 'https://doi.org/10.1000/xyz123',
            'journal_link' => 'IEEE Access, vol. 9',
            'scopus_eid' => '2-s2.0-85000000000',
        ]);

        $this->assertSame([
            'DOI: 10.1000/xyz123' => 'https://doi.org/10.1000/xyz123',
            'Scopus' => 'https://www.scopus.com/record/display.uri?eid=2-s2.0-85000000000&origin=resultslist',
        ], $publication->publicLinks());
    }

    public function test_a_journal_link_that_is_the_doi_is_not_listed_twice(): void
    {
        $publication = new Publication([
            'doi' => 'doi: 10.1000/xyz123',
            'journal_link' => 'https://doi.org/10.1000/xyz123/',
        ]);

        $this->assertSame(['DOI: 10.1000/xyz123' => 'https://doi.org/10.1000/xyz123'], $publication->publicLinks());

        $publisher = new Publication(['journal_link' => 'https://www.ieeexplore.ieee.org/document/1']);
        $this->assertSame(['ieeexplore.ieee.org' => 'https://www.ieeexplore.ieee.org/document/1'], $publisher->publicLinks());
    }

    public function test_placeholder_index_and_quartile_read_as_empty(): void
    {
        $publication = new Publication;
        $publication->setRelation('linkage', new PublicationLinkage(['name' => 'Non-Indexed']));
        $publication->setRelation('quartile', new PublicationQuartile(['name' => 'N/A']));

        $this->assertNull($publication->indexedIn());
        $this->assertNull($publication->quartileLabel());

        $publication->setRelation('linkage', new PublicationLinkage(['name' => 'Scopus']));
        $publication->setRelation('quartile', new PublicationQuartile(['name' => 'Q1']));

        $this->assertSame('Scopus', $publication->indexedIn());
        $this->assertSame('Q1', $publication->quartileLabel());
    }

    public function test_every_theme_shows_the_facts_and_leaves_out_grant_and_collaboration(): void
    {
        [$publication, $url] = $this->aPublishedPaper();

        $publication->update([
            'publication_linkage_id' => PublicationLinkage::where('name', 'Scopus')->value('id'),
            'publication_quartile_id' => PublicationQuartile::where('name', 'Q1')->value('id'),
            'keywords' => 'Rose Plant Disease; Resnet50',
            'doi' => '10.1000/test-fms',
            'student_involvement' => true,
            'grant_type_id' => GrantType::where('name', 'External Project')->value('id'),
            'research_collaboration_id' => ResearchCollaboration::where('name', 'Collaboration (External)')->value('id'),
        ]);

        $initial = Setting::get('active_theme');

        try {
            foreach (Theme::slugs() as $theme) {
                Setting::set('active_theme', $theme);

                $response = $this->get($url)->assertOk();

                foreach (['Scopus', 'Q1', 'Rose Plant Disease', 'Resnet50', 'https://doi.org/10.1000/test-fms'] as $fact) {
                    $response->assertSee($fact, false);
                }

                $response->assertSee('nvolvement');
                $response->assertDontSee('External Project');
                $response->assertDontSee('Collaboration (External)');
            }
        } finally {
            Setting::set('active_theme', $initial);
        }
    }

    public function test_a_paper_with_nothing_extra_shows_nothing_extra(): void
    {
        [$publication, $url] = $this->aPublishedPaper();

        $publication->update([
            'publication_linkage_id' => PublicationLinkage::where('name', 'Non-Indexed')->value('id'),
            'publication_quartile_id' => PublicationQuartile::where('name', 'N/A')->value('id'),
            'keywords' => null,
            'journal_link' => null,
            'doi' => null,
            'scopus_eid' => null,
            'student_involvement' => false,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertDontSee('Non-Indexed')
            ->assertDontSee('Indexed')
            ->assertDontSee('Quartile')
            ->assertDontSee('Keywords')
            ->assertDontSee('Read the Paper')
            ->assertDontSee('Read the paper')
            ->assertDontSee('nvolvement');
    }

    /** @return array{0: Publication, 1: string} */
    protected function aPublishedPaper(): array
    {
        $teacher = Teacher::published()
            ->whereNotNull('webpage')
            ->whereHas('publications')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no published teacher with a publication');
        }

        $publication = $teacher->publications()->first();
        $url = Seo::publicationUrl($publication);

        if (! $url) {
            $this->markTestSkipped('the publication has no public address');
        }

        return [$publication, $url];
    }
}
