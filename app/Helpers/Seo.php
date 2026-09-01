<?php

namespace App\Helpers;

use App\Models\Department;
use App\Models\Publication;
use App\Models\Teacher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Canonical URL resolution for the public frontend.
 *
 * Two things make canonical tags necessary here rather than optional:
 *
 * 1. A publication's URL carries the teacher slug, so a paper with N teacher
 *    authors is reachable at N URLs serving the same content. Without a
 *    canonical, search engines pick a winner themselves and drop the rest.
 * 2. Faculty and department segments are matched case-insensitively by MySQL,
 *    so /FSIT/CSE and /fsit/cse both render. Rebuilding the URL from the model
 *    in lower case collapses those variants onto one address.
 */
class Seo
{
    /**
     * The canonical URL for the current request.
     *
     * Pass $override when the page already knows its canonical differs from the
     * requested URL — publication pages do, since they canonicalise onto their
     * primary author.
     */
    public static function canonicalUrl(?string $override = null): string
    {
        if (filled($override)) {
            return $override;
        }

        $route = Route::current();
        $name = $route?->getName();
        $params = $route?->parameters() ?? [];

        $faculty = strtolower((string) ($params['faculty_short_name'] ?? ''));
        $department = strtolower((string) ($params['department_code'] ?? ''));
        $teacher = (string) ($params['teacher_webpage'] ?? '');

        return match ($name) {
            'home' => self::absolute('home'),

            'faculty.show' => self::absolute('faculty.show', [
                'faculty_short_name' => $faculty,
            ]),

            'department.show' => self::absolute('department.show', [
                'faculty_short_name' => $faculty,
                'department_code' => $department,
            ]),

            'department.contact' => self::absolute('department.contact', [
                'faculty_short_name' => $faculty,
                'department_code' => $department,
            ]),

            'teacher.show' => self::absolute('teacher.show', [
                'faculty_short_name' => $faculty,
                'department_code' => $department,
                'teacher_webpage' => $teacher,
            ]),

            // Reached only if a publication page rendered without an override.
            default => self::root() . request()->getPathInfo(),
        };
    }

    /**
     * Build a route URL rooted at APP_URL rather than the requested host.
     *
     * A canonical has to name one address. Left to the request, a site reachable
     * at both www and non-www — or over http and https — would advertise a
     * different canonical per visit, which defeats the point. Pinning to APP_URL
     * also keeps these tags identical to the sitemap, which is generated from the
     * CLI where no request host exists; conflicting signals are worse than none.
     *
     * Public so the sitemap builds its entries the same way. If the two ever
     * disagreed, the sitemap would advertise addresses the pages disown.
     *
     * @param  array<string,mixed>  $parameters
     */
    public static function absolute(string $routeName, array $parameters = []): string
    {
        return self::root() . route($routeName, $parameters, false);
    }

    protected static function root(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * The single canonical URL for a publication, or null when no teacher author
     * can host it.
     *
     * Picks the author the citation itself would lead with: the one flagged
     * `first`, then `corresponding`, then the lowest sort_order, and finally the
     * lowest id so the result never depends on row order. Only teachers who can
     * actually serve a page are eligible — an inactive or archived teacher, or
     * one without a webpage slug, has no URL to point at.
     */
    public static function publicationUrl(Publication $publication): ?string
    {
        $teacher = self::primaryAuthor($publication);

        if (! $teacher) {
            return null;
        }

        $department = $teacher->department;
        $faculty = $department?->faculty;

        if (! $department || ! $faculty?->short_name) {
            return null;
        }

        return self::absolute('publication.show', [
            'faculty_short_name' => strtolower($faculty->short_name),
            'department_code' => strtolower($department->code),
            'teacher_webpage' => $teacher->webpage,
            'publication_slug' => self::publicationSlug($publication),
        ]);
    }

    /**
     * A teacher's own page, or null when they have nothing to be reached at.
     *
     * Three things have to be there — a faculty short name, a department code
     * and a webpage slug — and a teacher missing any of them has no address, so
     * their name on a byline is text rather than a link.
     *
     * $department overrides the teacher's home department. A teacher assigned to
     * several departments has a page under each of them — the public controller
     * matches on the home department or any department_teacher assignment — so a
     * screen listing assignments has to link to the one it is showing rather than
     * silently sending every row to the same home department page.
     */
    public static function teacherUrl(?Teacher $teacher, ?Department $department = null): ?string
    {
        $department ??= $teacher?->department;
        $faculty = $department?->faculty;

        if (! $teacher || ! $department || ! $faculty?->short_name || blank($teacher->webpage)) {
            return null;
        }

        return self::absolute('teacher.show', [
            'faculty_short_name' => strtolower($faculty->short_name),
            'department_code' => strtolower($department->code),
            'teacher_webpage' => $teacher->webpage,
        ]);
    }

    /**
     * The teacher a publication canonicalises onto.
     */
    public static function primaryAuthor(Publication $publication): ?Teacher
    {
        $rolePriority = ['first' => 0, 'corresponding' => 1, 'co_author' => 2];

        return $publication->teachers
            ->filter(fn (Teacher $teacher) => $teacher->is_active
                && ! $teacher->is_archived
                && filled($teacher->webpage))
            ->sortBy([
                fn (Teacher $a, Teacher $b) => ($rolePriority[$a->pivot->author_role] ?? 3)
                    <=> ($rolePriority[$b->pivot->author_role] ?? 3),
                fn (Teacher $a, Teacher $b) => ($a->pivot->sort_order ?? PHP_INT_MAX)
                    <=> ($b->pivot->sort_order ?? PHP_INT_MAX),
                fn (Teacher $a, Teacher $b) => $a->id <=> $b->id,
            ])
            ->first();
    }

    /**
     * The slug segment used in publication URLs.
     *
     * Mirrors the fallback the frontend links and PublicationController already
     * use, so a canonical never points at an address that would 404.
     */
    public static function publicationSlug(Publication $publication): string
    {
        return $publication->slug ?: Str::slug($publication->title);
    }
}
