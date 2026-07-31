<?php

namespace Tests\Feature;

use App\Helpers\SeoPayload;
use App\Helpers\Theme;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sharing and structured data on every public page type, not just profiles.
 *
 * Faculty, department and publication pages carried a title and nothing else:
 * pasted into a chat they previewed as a bare link, and search engines were told
 * nothing about what they held. Four themes render the same payload, so these
 * check every one of them rather than whichever happens to be active.
 */
class SeoPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Theme::forget();
    }

    protected function tearDown(): void
    {
        Theme::forget();

        parent::tearDown();
    }

    protected function useTheme(string $slug): void
    {
        Cache::put('setting.active_theme', new Setting([
            'key' => 'active_theme',
            'value' => $slug,
            'type' => 'string',
        ]), 600);

        Theme::forget();
    }

    /** @return array<string, string> page label => URL */
    protected function urls(): array
    {
        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->whereHas('publications')
            ->whereHas('department.faculty')
            ->with('department.faculty', 'publications')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no published teacher with publications');
        }

        $faculty = strtolower($teacher->department->faculty->short_name);
        $department = strtolower($teacher->department->code);
        $publication = $teacher->publications->first();
        $slug = $publication->slug ?: Str::slug($publication->title);

        return [
            'directory' => '/',
            'faculty' => "/{$faculty}",
            'department' => "/{$faculty}/{$department}",
            'profile' => "/{$faculty}/{$department}/{$teacher->webpage}",
            'publication' => "/{$faculty}/{$department}/{$teacher->webpage}/publication/{$slug}",
        ];
    }

    public function test_every_page_type_carries_sharing_tags_in_every_theme(): void
    {
        $urls = $this->urls();

        $expectedSchema = [
            'directory' => 'CollectionPage',
            'faculty' => 'CollectionPage',
            'department' => 'CollectionPage',
            'profile' => 'Person',
            'publication' => 'ScholarlyArticle',
        ];

        foreach (Theme::slugs() as $theme) {
            $this->useTheme($theme);

            foreach ($urls as $label => $url) {
                $html = $this->get($url)->assertStatus(200)->getContent();

                $this->assertStringContainsString('property="og:title"', $html, "{$theme} {$label}: no og:title");
                $this->assertStringContainsString('property="og:url"', $html, "{$theme} {$label}: no og:url");
                $this->assertStringContainsString('name="twitter:card"', $html, "{$theme} {$label}: no twitter card");

                // Exactly one meta description: the head partial emits it from
                // the section, and a second one is a duplicate-content signal.
                $this->assertSame(
                    1,
                    substr_count($html, '<meta name="description"'),
                    "{$theme} {$label}: expected one meta description",
                );

                preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
                $this->assertNotEmpty($matches, "{$theme} {$label}: no JSON-LD");

                $schema = json_decode(trim($matches[1]), true);
                $this->assertIsArray($schema, "{$theme} {$label}: JSON-LD does not parse");
                $this->assertSame($expectedSchema[$label], $schema['@type'], "{$theme} {$label}: wrong schema type");
            }
        }
    }

    public function test_descriptions_stay_within_the_preview_budget(): void
    {
        $urls = $this->urls();
        $this->useTheme(Theme::active());

        foreach ($urls as $label => $url) {
            $html = $this->get($url)->assertStatus(200)->getContent();

            preg_match('/<meta name="description" content="([^"]*)"/', $html, $matches);

            $this->assertLessThanOrEqual(
                SeoPayload::DESCRIPTION_LIMIT,
                mb_strlen(html_entity_decode($matches[1] ?? '')),
                "{$label}: description longer than the limit",
            );
        }
    }

    public function test_a_publication_advertises_a_date_even_when_only_the_year_is_known(): void
    {
        $teacher = Teacher::whereHas('publications', fn ($q) => $q
            ->whereNull('publication_date')
            ->whereNotNull('publication_year'))
            ->whereHas('department.faculty')
            ->with('department.faculty')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no year-only publication on a published teacher');
        }

        $publication = $teacher->publications
            ->firstWhere(fn ($p) => $p->publication_date === null && $p->publication_year !== null);

        $payload = SeoPayload::forPublication(
            $publication,
            'A Author',
            $teacher->department->faculty,
            $teacher->department,
        );

        $this->assertSame((string) $publication->publication_year, $payload['schema']['datePublished']);
    }
}
