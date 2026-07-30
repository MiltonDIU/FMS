<?php

namespace Tests\Feature;

use App\Helpers\Seo;
use App\Models\Publication;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the canonical URLs the directory advertises.
 *
 * The frontend publishes roughly twelve thousand URLs, and two properties of the
 * routing make duplicates unavoidable without canonical tags: a publication's
 * path carries the teacher slug, so a co-authored paper is reachable once per
 * author; and MySQL matches the faculty and department segments
 * case-insensitively, so /FSIT/CSE and /fsit/cse both render.
 */
class SeoCanonicalTest extends TestCase
{
    protected function canonicalOf(string $path): string
    {
        $html = $this->get($path)->assertStatus(200)->getContent();

        $this->assertMatchesRegularExpression(
            '#<link rel="canonical" href="[^"]+">#',
            $html,
            "no canonical tag rendered for {$path}",
        );

        preg_match('#<link rel="canonical" href="([^"]+)"#', $html, $m);

        return $m[1];
    }

    /**
     * A teacher whose faculty, department and slug are all present, so every
     * frontend route can be built for them.
     */
    protected function routableTeacher(): Teacher
    {
        $teacher = Teacher::with('department.faculty')
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->whereHas('department.faculty', fn ($q) => $q->whereNotNull('short_name'))
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no routable teacher in the database');
        }

        return $teacher;
    }

    public function test_every_page_type_advertises_a_canonical_rooted_at_app_url(): void
    {
        $teacher = $this->routableTeacher();
        $faculty = strtolower($teacher->department->faculty->short_name);
        $department = strtolower($teacher->department->code);
        $root = rtrim((string) config('app.url'), '/');

        $paths = [
            '/',
            "/{$faculty}",
            "/{$faculty}/{$department}",
            "/{$faculty}/{$department}/contact",
            "/{$faculty}/{$department}/{$teacher->webpage}",
        ];

        foreach ($paths as $path) {
            $canonical = $this->canonicalOf($path);

            $this->assertStringStartsWith($root, $canonical, "canonical for {$path} left APP_URL");
            $this->assertSame($root . rtrim($path, '/'), rtrim($canonical, '/'));
        }
    }

    /**
     * Pinned to APP_URL rather than the request host so a site answering on both
     * www and non-www does not advertise two different canonicals, and so the
     * tags agree with the sitemap, which is built from the CLI.
     */
    public function test_canonical_ignores_the_requesting_host(): void
    {
        $teacher = $this->routableTeacher();
        $faculty = strtolower($teacher->department->faculty->short_name);
        $root = rtrim((string) config('app.url'), '/');

        $html = $this->get("http://some-other-host.test/{$faculty}")->getContent();

        preg_match('#<link rel="canonical" href="([^"]+)"#', $html, $m);

        $this->assertSame($root . "/{$faculty}", $m[1] ?? null);
    }

    public function test_mixed_case_segments_canonicalise_to_lower_case(): void
    {
        $teacher = $this->routableTeacher();
        $faculty = $teacher->department->faculty->short_name;
        $department = $teacher->department->code;
        $root = rtrim((string) config('app.url'), '/');

        $canonical = $this->canonicalOf('/' . strtoupper($faculty) . '/' . strtoupper($department));

        $this->assertSame(
            $root . '/' . strtolower($faculty) . '/' . strtolower($department),
            $canonical,
        );
    }

    /**
     * Every author's copy of a paper must name the same address, or search
     * engines pick the winner themselves and drop the rest.
     */
    public function test_a_co_authored_publication_canonicalises_onto_one_url(): void
    {
        $publicationId = DB::table('publication_authors')
            ->where('authorable_type', Teacher::class)
            ->select('publication_id', DB::raw('COUNT(DISTINCT authorable_id) as authors'))
            ->groupBy('publication_id')
            ->having('authors', '>=', 2)
            ->orderByDesc('authors')
            ->value('publication_id');

        if (! $publicationId) {
            $this->markTestSkipped('no co-authored publication in the database');
        }

        $publication = Publication::with('teachers.department.faculty')->find($publicationId);
        $expected = Seo::publicationUrl($publication);

        $this->assertNotNull($expected, 'no author could host the publication');

        $slug = Seo::publicationSlug($publication);
        $checked = 0;

        foreach ($publication->teachers as $author) {
            $department = $author->department;
            $faculty = $department?->faculty;

            if (! $author->is_active || $author->is_archived || blank($author->webpage)
                || ! $department || blank($faculty?->short_name)) {
                continue;
            }

            $path = sprintf(
                '/%s/%s/%s/publication/%s',
                strtolower($faculty->short_name),
                strtolower($department->code),
                $author->webpage,
                $slug,
            );

            $this->assertSame($expected, $this->canonicalOf($path), "diverged at {$path}");
            $checked++;
        }

        $this->assertGreaterThan(1, $checked, 'needed at least two routable authors to prove the point');
    }

    /**
     * The paper leads with its `first` author where one is recorded, so the
     * canonical matches how the citation itself reads.
     */
    public function test_primary_author_prefers_the_first_author_role(): void
    {
        $publicationId = DB::table('publication_authors')
            ->where('authorable_type', Teacher::class)
            ->where('author_role', 'first')
            ->whereIn('publication_id', function ($q) {
                $q->select('publication_id')
                    ->from('publication_authors')
                    ->where('authorable_type', Teacher::class)
                    ->groupBy('publication_id')
                    ->havingRaw('COUNT(DISTINCT authorable_id) >= 2');
            })
            ->value('publication_id');

        if (! $publicationId) {
            $this->markTestSkipped('no co-authored publication with a first author');
        }

        $publication = Publication::with('teachers.department.faculty')->find($publicationId);
        $primary = Seo::primaryAuthor($publication);

        $this->assertNotNull($primary);
        $this->assertSame('first', $primary->pivot->author_role);
    }
}
