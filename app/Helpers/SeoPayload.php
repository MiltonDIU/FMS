<?php

namespace App\Helpers;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use App\Models\Teacher;
use Illuminate\Support\Str;

/**
 * Builds the sharing and structured-data payload for a public page.
 *
 * Only teacher profiles carried OpenGraph and Schema.org markup. The faculty,
 * department and publication pages — several thousand URLs between them, and the
 * ones most likely to be shared into a chat or a mailing list — shipped a title
 * and nothing else, so they previewed as a bare link and told search engines
 * nothing about what they contained.
 *
 * The payload is built here rather than in Blade because it is data, and because
 * four themes render it: a copy per theme would drift the moment one changed.
 * Each method returns the same shape, which each theme's own seo-tags partial
 * renders — the themes are independent, so there are four copies of that markup.
 *
 * @phpstan-type Payload array{title: string, description: string, url: string, image: string|null, type: string, schema: array<string, mixed>|null}
 */
class SeoPayload
{
    /** Descriptions are cut to what a search result or a chat preview will show. */
    public const DESCRIPTION_LIMIT = 160;

    /**
     * The whole directory: the front page with no faculty selected.
     */
    public static function forDirectory(int $faculties = 0, int $departments = 0, int $teachers = 0): array
    {
        $brand = Branding::all();
        $name = $brand['site_name'] ?? 'Faculty Directory';

        return [
            'title' => $name . ($brand['meta_title_suffix'] ?? ''),
            'description' => static::clean(
                $brand['meta_description'] ?: "Browse {$teachers} academics across {$departments} departments and {$faculties} faculties at {$name}."
            ),
            'url' => Seo::absolute('home'),
            'image' => Branding::logoUrl(),
            'type' => 'website',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $name . ' — Faculty Directory',
                'url' => Seo::absolute('home'),
                'about' => static::organisation(),
            ],
        ];
    }

    /**
     * A faculty: the front page with one selected.
     */
    public static function forFaculty(Faculty $faculty, int $departments = 0, int $teachers = 0): array
    {
        $url = Seo::absolute('faculty.show', [
            'faculty_short_name' => strtolower((string) $faculty->short_name),
        ]);

        $description = strip_tags((string) $faculty->description)
            ?: "The {$faculty->name} at " . Branding::get('site_name')
                . ($departments ? ": {$departments} departments" : '')
                . ($teachers ? ", {$teachers} faculty members." : '.');

        return [
            'title' => $faculty->name . Branding::get('meta_title_suffix'),
            'description' => static::clean($description),
            'url' => $url,
            'image' => Branding::logoUrl(),
            'type' => 'website',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $faculty->name,
                'url' => $url,
                'about' => array_filter([
                    '@type' => 'EducationalOrganization',
                    'name' => $faculty->name,
                    'alternateName' => $faculty->short_name,
                    'url' => $url,
                    'parentOrganization' => static::organisation(),
                ]),
                'breadcrumb' => static::breadcrumb([
                    ['Directory', Seo::absolute('home')],
                    [$faculty->name, $url],
                ]),
            ],
        ];
    }

    /**
     * A department, with the number of academics it lists.
     */
    public static function forDepartment(Faculty $faculty, Department $department, int $teachers = 0): array
    {
        $facultyUrl = Seo::absolute('faculty.show', [
            'faculty_short_name' => strtolower((string) $faculty->short_name),
        ]);

        $url = Seo::absolute('department.show', [
            'faculty_short_name' => strtolower((string) $faculty->short_name),
            'department_code' => strtolower((string) $department->code),
        ]);

        $description = strip_tags((string) $department->description)
            ?: trim("{$department->name} at " . Branding::get('site_name') . ', part of the ' . $faculty->name
                . ($teachers ? ". {$teachers} faculty members." : '.'));

        return [
            'title' => $department->name . Branding::get('meta_title_suffix'),
            'description' => static::clean($description),
            'url' => $url,
            'image' => Branding::logoUrl(),
            'type' => 'website',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $department->name,
                'url' => $url,
                'about' => array_filter([
                    '@type' => 'EducationalOrganization',
                    'name' => $department->name,
                    'alternateName' => $department->code,
                    'url' => $url,
                    'parentOrganization' => array_filter([
                        '@type' => 'EducationalOrganization',
                        'name' => $faculty->name,
                        'url' => $facultyUrl,
                    ]),
                ]),
                'breadcrumb' => static::breadcrumb([
                    ['Directory', Seo::absolute('home')],
                    [$faculty->name, $facultyUrl],
                    [$department->name, $url],
                ]),
            ],
        ];
    }

    /**
     * A teacher's profile.
     *
     * How many publications a card may advertise. Five is what fits a
     * description and is a fair sample of someone's work.
     */
    public const SUGGESTED_PUBLICATIONS = 5;

    public static function forTeacher(Teacher $teacher, Faculty $faculty, Department $department): array
    {
        $url = Seo::absolute('teacher.show', [
            'faculty_short_name' => strtolower((string) $faculty->short_name),
            'department_code' => strtolower((string) $department->code),
            'teacher_webpage' => $teacher->webpage,
        ]);

        $name = $teacher->full_name ?: trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
        $designation = optional($teacher->designation)->name ?: 'Faculty Member';

        /*
         * Papers worth suggesting: the ones that carry an abstract, so a reader
         * following the link finds something to read rather than a bare title.
         * About 7,000 of 17,500 publications have one.
         */
        $suggested = $teacher->publications
            ->filter(fn ($publication) => filled($publication->abstract))
            ->sortByDesc(fn ($publication) => $publication->publication_year ?? 0)
            ->take(static::SUGGESTED_PUBLICATIONS)
            ->values();

        // A social card is one image, a title and a line of text — it cannot
        // list papers. The line names what is there; the list itself goes into
        // the structured data, which is what search engines read.
        $description = static::clean(
            $teacher->bio
                ?: implode(', ', $teacher->researchInterestNames())
                ?: trim("{$designation}, {$department->name} at " . Branding::get('site_name') . '.')
        );

        if ($suggested->isNotEmpty()) {
            $lead = static::clean(
                $description . ' Recent work includes “' . $suggested->first()->title . '”.',
                static::DESCRIPTION_LIMIT
            );

            // Only take the longer line if the paper's title actually survived
            // the cut; otherwise it reads as a sentence that stops mid-word.
            if (str_contains($lead, 'Recent work includes')) {
                $description = $lead;
            }
        }

        return [
            'title' => "{$name} — {$designation} | {$department->name}" . Branding::get('meta_title_suffix'),
            'description' => $description,
            'url' => $url,
            // The rendered card, not the 90px thumbnail the legacy host serves.
            'image' => Seo::absolute('teacher.share-image', [
                'faculty_short_name' => strtolower((string) $faculty->short_name),
                'department_code' => strtolower((string) $department->code),
                'teacher_webpage' => $teacher->webpage,
            ]),
            'type' => 'profile',
            'schema' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $name,
                'url' => $url,
                'image' => $teacher->photo_url,
                'jobTitle' => optional($teacher->designation)->name,
                /*
                 * No email and no telephone. They are on the page for a visitor
                 * who came looking, but putting them in structured data hands
                 * them to every crawler that fetches the URL, and a teacher
                 * sharing their own profile is not consenting to that.
                 */
                'description' => static::clean($teacher->bio ?: implode(', ', $teacher->researchInterestNames()), 400) ?: null,
                'worksFor' => array_filter([
                    '@type' => 'EducationalOrganization',
                    'name' => $department->name,
                    'parentOrganization' => array_filter([
                        '@type' => 'EducationalOrganization',
                        'name' => $faculty->name,
                    ]),
                ]),
                'affiliation' => static::organisation(),
                'sameAs' => $teacher->socialLinks?->pluck('url')->filter()->values()->all() ?: null,
                'subjectOf' => $suggested
                    ->map(fn ($publication) => array_filter([
                        '@type' => 'ScholarlyArticle',
                        'name' => $publication->title,
                        'url' => Seo::publicationUrl($publication),
                        'datePublished' => $publication->publication_date?->format('Y-m-d')
                            ?: ($publication->publication_year ? (string) $publication->publication_year : null),
                        'abstract' => static::clean($publication->abstract, 300) ?: null,
                        'isPartOf' => $publication->journal_name
                            ? ['@type' => 'Periodical', 'name' => $publication->journal_name]
                            : null,
                    ]))
                    ->all() ?: null,
                'breadcrumb' => static::breadcrumb([
                    ['Directory', Seo::absolute('home')],
                    [$faculty->name, Seo::absolute('faculty.show', [
                        'faculty_short_name' => strtolower((string) $faculty->short_name),
                    ])],
                    [$department->name, Seo::absolute('department.show', [
                        'faculty_short_name' => strtolower((string) $faculty->short_name),
                        'department_code' => strtolower((string) $department->code),
                    ])],
                    [$name, $url],
                ]),
            ]),
        ];
    }

    /**
     * A single paper.
     *
     * ScholarlyArticle rather than the generic Article: it is what Google
     * Scholar and reference managers look for, and this is the one page type on
     * the site that is genuinely a citation target.
     */
    public static function forPublication(Publication $publication, string $authors, Faculty $faculty, Department $department): array
    {
        $url = Seo::publicationUrl($publication) ?? url()->current();
        $venue = $publication->journal_name;

        $description = strip_tags((string) $publication->abstract)
            ?: trim(implode(' ', array_filter([
                $authors ? $authors . '.' : null,
                $publication->publication_year ? '(' . $publication->publication_year . ').' : null,
                $venue ? 'Published in ' . $venue . '.' : null,
            ]))) ?: $publication->title;

        $authorList = collect(explode(',', $authors))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->map(fn ($name) => ['@type' => 'Person', 'name' => $name])
            ->values()
            ->all();

        return [
            'title' => Str::limit($publication->title, 110) . Branding::get('meta_title_suffix'),
            'description' => static::clean($description),
            'url' => $url,
            'image' => Branding::logoUrl(),
            'type' => 'article',
            'schema' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'ScholarlyArticle',
                'headline' => Str::limit($publication->title, 110),
                'name' => $publication->title,
                'url' => $url,
                'author' => $authorList ?: null,
                // The exact day is recorded for about one paper in twelve; the
                // year is what the rest have, and a year alone is valid here.
                'datePublished' => $publication->publication_date?->format('Y-m-d')
                    ?: ($publication->publication_year ? (string) $publication->publication_year : null),
                'abstract' => static::clean($publication->abstract, 500) ?: null,
                'keywords' => filled($publication->keywords) ? $publication->keywords : null,
                'isPartOf' => $venue ? ['@type' => 'Periodical', 'name' => $venue] : null,
                'publisher' => static::organisation(),
                'breadcrumb' => static::breadcrumb([
                    ['Directory', Seo::absolute('home')],
                    [$faculty->name, Seo::absolute('faculty.show', [
                        'faculty_short_name' => strtolower((string) $faculty->short_name),
                    ])],
                    [$department->name, Seo::absolute('department.show', [
                        'faculty_short_name' => strtolower((string) $faculty->short_name),
                        'department_code' => strtolower((string) $department->code),
                    ])],
                    [Str::limit($publication->title, 60), $url],
                ]),
            ]),
        ];
    }

    /** The university itself, reused as the parent of everything else. */
    protected static function organisation(): array
    {
        return array_filter([
            '@type' => 'EducationalOrganization',
            'name' => Branding::get('site_name'),
            'alternateName' => Branding::get('short_name'),
            'url' => Branding::get('main_site_url') ?: url('/'),
            'logo' => Branding::logoUrl(),
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $trail
     */
    protected static function breadcrumb(array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($trail)
                ->values()
                ->map(fn (array $step, int $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $step[0],
                    'item' => $step[1],
                ])
                ->all(),
        ];
    }

    /** Flatten markup and whitespace, then cut to a length a preview will show. */
    protected static function clean(?string $value, int $limit = self::DESCRIPTION_LIMIT): string
    {
        if (blank($value)) {
            return '';
        }

        // Str::limit() appends its marker on top of the limit, so the budget is
        // one short of it — a description that overshoots gets cut by the search
        // engine anyway, and mid-word.
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($value))), max(1, $limit - 1), '…');
    }
}
