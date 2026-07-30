<?php

namespace App\Console\Commands;

use App\Helpers\Seo;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use XMLWriter;

/**
 * Writes public/sitemap.xml for the whole public frontend.
 *
 * The directory is far too large to leave to link discovery — roughly fifteen
 * thousand URLs, most of them publication pages — so the full list is handed to
 * search engines directly.
 *
 * Only canonical URLs are listed. A publication with several teacher authors is
 * reachable once per author, and listing every variant would ask search engines
 * to index thousands of duplicates; Seo::publicationUrl() collapses each paper
 * onto its primary author, matching the canonical tag the page itself emits.
 *
 * Usage: php artisan sitemap:generate
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate
                            {--path= : Where to write the file (defaults to public/sitemap.xml)}
                            {--no-robots : Leave robots.txt untouched}';

    protected $description = 'Generate public/sitemap.xml for the public faculty directory';

    /**
     * Rows per chunk when walking teachers and publications.
     */
    protected const CHUNK = 250;

    public function handle(): int
    {
        $path = $this->option('path') ?: public_path('sitemap.xml');

        if (! str_starts_with((string) config('app.url'), 'http')) {
            $this->error('APP_URL is not set to an absolute URL; sitemap entries would be unusable.');

            return self::FAILURE;
        }

        $this->line('Base URL: ' . config('app.url'));

        $generatedAt = Carbon::now();

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);

        // Stamped into the file itself so anyone opening it — or fetching it over
        // HTTP months later — can tell how stale it is without checking the
        // database or the filesystem.
        $writer->writeComment(sprintf(
            ' Generated %s by "php artisan sitemap:generate" ',
            $generatedAt->toDateTimeString(),
        ));

        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $counts = [];

        $counts['home'] = $this->writeUrl($writer, Seo::absolute('home'), null) ? 1 : 0;
        $counts['faculty'] = $this->writeFaculties($writer);
        [$counts['department'], $counts['contact']] = $this->writeDepartments($writer);
        $counts['profile'] = $this->writeTeachers($writer);
        $counts['publication'] = $this->writePublications($writer);

        $writer->endElement();
        $writer->endDocument();

        file_put_contents($path, $writer->outputMemory());

        $total = array_sum($counts);
        foreach ($counts as $label => $n) {
            $this->line(sprintf('  %-12s %6d', $label, $n));
        }
        $this->info(sprintf('Wrote %d URLs to %s (%s)', $total, $path, $this->humanSize($path)));

        if (! $this->option('no-robots')) {
            $this->syncRobots();
        }

        $this->recordRun($generatedAt, $total);
        $this->printSubmissionSteps($generatedAt, $total);

        return self::SUCCESS;
    }

    /**
     * Remember when the sitemap was last built and how big it was.
     *
     * Kept in settings rather than inferred from the file's mtime, because the
     * file is generated per environment and excluded from the repository — after
     * a deploy the mtime says when the file was copied, not when it was built.
     */
    protected function recordRun(Carbon $generatedAt, int $total): void
    {
        Setting::set('sitemap_last_generated_at', $generatedAt->toDateTimeString());
        Setting::set('sitemap_last_url_count', (string) $total);
        Setting::set('sitemap_last_base_url', rtrim((string) config('app.url'), '/'));
    }

    /**
     * Print what is needed to register the sitemap.
     *
     * There is no programmatic submission left: Google retired its sitemap ping
     * endpoint in 2023 (it now answers 404) and Bing's answers 410, so the URL
     * has to be entered in Search Console once. After that both re-fetch it on
     * their own schedule and no further action is needed per rebuild.
     */
    protected function printSubmissionSteps(Carbon $generatedAt, int $total): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->line('  Generated : ' . $generatedAt->toDayDateTimeString() . ' (' . config('app.timezone') . ')');
        $this->line('  URLs      : ' . number_format($total));
        $this->line('  Sitemap   : ' . $base . '/sitemap.xml');
        $this->line('  robots.txt: ' . $base . '/robots.txt');

        if (str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            $this->newLine();
            $this->warn('  APP_URL still points at localhost, so these URLs are only good for local checks.');
            $this->warn('  Set APP_URL to the public origin and run this again before submitting.');

            return;
        }

        $this->newLine();
        $this->line('  Submit once in Google Search Console -> Sitemaps, entering "sitemap.xml":');
        $this->line('    https://search.google.com/search-console/sitemaps?resource_id=' . urlencode($base));
        $this->line('  Bing Webmaster Tools -> Sitemaps accepts the same URL.');
        $this->comment('  Ping endpoints are gone (Google 404, Bing 410); one manual submission is enough —');
        $this->comment('  both crawlers re-read the file afterwards, so later rebuilds need no action.');
    }

    /**
     * Point robots.txt at the sitemap.
     *
     * Done here rather than by hand because the URL depends on APP_URL, and a
     * static robots.txt cannot read it. Only the Sitemap line is touched, so any
     * crawl rules in the file survive.
     */
    protected function syncRobots(): void
    {
        $file = public_path('robots.txt');
        $line = 'Sitemap: ' . rtrim((string) config('app.url'), '/') . '/sitemap.xml';

        $lines = is_file($file)
            ? preg_split('/\R/', rtrim(file_get_contents($file)))
            : ['User-agent: *', 'Disallow:'];

        $lines = array_values(array_filter(
            $lines,
            fn (string $l) => ! str_starts_with(strtolower(trim($l)), 'sitemap:')
        ));

        if (end($lines) !== '') {
            $lines[] = '';
        }

        $lines[] = $line;

        file_put_contents($file, implode("\n", $lines) . "\n");
        $this->line('robots.txt -> ' . $line);
    }

    protected function writeFaculties(XMLWriter $writer): int
    {
        $n = 0;

        foreach (Faculty::where('is_active', true)->whereNotNull('short_name')->get() as $faculty) {
            $url = Seo::absolute('faculty.show', ['faculty_short_name' => strtolower($faculty->short_name)]);
            $n += $this->writeUrl($writer, $url, $faculty->updated_at) ? 1 : 0;
        }

        return $n;
    }

    /**
     * @return array{0:int,1:int} department count, contact count
     */
    protected function writeDepartments(XMLWriter $writer): array
    {
        $departments = $contacts = 0;

        $query = Department::with('faculty')
            ->where('is_active', true)
            ->whereHas('faculty', fn ($q) => $q->where('is_active', true)->whereNotNull('short_name'));

        foreach ($query->get() as $department) {
            $params = [
                'faculty_short_name' => strtolower($department->faculty->short_name),
                'department_code' => strtolower($department->code),
            ];

            $departments += $this->writeUrl($writer, Seo::absolute('department.show', $params), $department->updated_at) ? 1 : 0;
            $contacts += $this->writeUrl($writer, Seo::absolute('department.contact', $params), $department->updated_at) ? 1 : 0;
        }

        return [$departments, $contacts];
    }

    protected function writeTeachers(XMLWriter $writer): int
    {
        $n = 0;

        $this->eachVisibleTeacher(function (Teacher $teacher) use ($writer, &$n) {
            $department = $teacher->department;
            $faculty = $department?->faculty;

            if (! $department || ! $faculty?->short_name) {
                return;
            }

            $url = Seo::absolute('teacher.show', [
                'faculty_short_name' => strtolower($faculty->short_name),
                'department_code' => strtolower($department->code),
                'teacher_webpage' => $teacher->webpage,
            ]);

            $n += $this->writeUrl($writer, $url, $this->profileLastModified($teacher)) ? 1 : 0;
        });

        return $n;
    }

    protected function writePublications(XMLWriter $writer): int
    {
        $n = 0;

        Publication::with('teachers.department.faculty')
            ->whereHas('teachers')
            ->chunkById(self::CHUNK, function ($publications) use ($writer, &$n) {
                foreach ($publications as $publication) {
                    // Null when no author can host the page — an inactive or
                    // archived teacher, or one with no webpage slug.
                    $url = Seo::publicationUrl($publication);

                    if ($url === null) {
                        continue;
                    }

                    $n += $this->writeUrl($writer, $url, $publication->updated_at) ? 1 : 0;
                }
            });

        return $n;
    }

    /**
     * When a teacher's profile page last meaningfully changed.
     *
     * The teachers row only covers the header — name, designation, contact. Almost
     * everything the page shows lives in child tables, so a teacher who adds five
     * publications leaves teachers.updated_at untouched. Reporting that as lastmod
     * tells search engines nothing changed and delays recrawling the new content,
     * which is the opposite of what the tag is for.
     *
     * Google only honours lastmod when it is "consistently and verifiably
     * accurate", so it is worth taking the real maximum rather than the cheap one.
     */
    protected function profileLastModified(Teacher $teacher): ?Carbon
    {
        $timestamps = array_merge(
            [$teacher->updated_at],
            [self::childTimestamps()[$teacher->id] ?? null],
        );

        $timestamps = array_filter($timestamps);

        return empty($timestamps)
            ? null
            : collect($timestamps)->max();
    }

    /**
     * Latest child-record timestamp per teacher, loaded once.
     *
     * One grouped query per table rather than per teacher: at eleven hundred
     * teachers the per-row version would be thousands of queries for a value that
     * changes only between runs.
     *
     * @return array<int,Carbon>
     */
    protected static function childTimestamps(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];

        $tables = [
            'educations', 'awards', 'job_experiences', 'training_experiences',
            'certifications', 'memberships', 'skills', 'teaching_areas',
            'social_links', 'research_projects',
        ];

        foreach ($tables as $table) {
            $rows = \Illuminate\Support\Facades\DB::table($table)
                ->select('teacher_id', \Illuminate\Support\Facades\DB::raw('MAX(updated_at) as latest'))
                ->whereNotNull('teacher_id')
                ->groupBy('teacher_id')
                ->get();

            foreach ($rows as $row) {
                if (blank($row->latest)) {
                    continue;
                }

                $latest = Carbon::parse($row->latest);

                if (! isset($map[$row->teacher_id]) || $latest->greaterThan($map[$row->teacher_id])) {
                    $map[$row->teacher_id] = $latest;
                }
            }
        }

        // Publications hang off a polymorphic pivot rather than a teacher_id column.
        $publications = \Illuminate\Support\Facades\DB::table('publication_authors')
            ->join('publications', 'publications.id', '=', 'publication_authors.publication_id')
            ->where('publication_authors.authorable_type', Teacher::class)
            ->select(
                'publication_authors.authorable_id as teacher_id',
                \Illuminate\Support\Facades\DB::raw('MAX(publications.updated_at) as latest'),
            )
            ->groupBy('publication_authors.authorable_id')
            ->get();

        foreach ($publications as $row) {
            if (blank($row->latest)) {
                continue;
            }

            $latest = Carbon::parse($row->latest);

            if (! isset($map[$row->teacher_id]) || $latest->greaterThan($map[$row->teacher_id])) {
                $map[$row->teacher_id] = $latest;
            }
        }

        return $map;
    }

    protected function eachVisibleTeacher(callable $callback): void
    {
        Teacher::with('department.faculty')
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->chunkById(self::CHUNK, function ($teachers) use ($callback) {
                foreach ($teachers as $teacher) {
                    $callback($teacher);
                }
            });
    }

    /**
     * Write one <url> entry, skipping addresses already written.
     *
     * Only loc and lastmod are emitted. Google's sitemap documentation states
     * plainly that it "ignores <priority> and <changefreq> values", so writing
     * them added two lines per URL — a third of the file across twelve thousand
     * entries — that no crawler we care about reads.
     */
    protected function writeUrl(XMLWriter $writer, string $loc, ?Carbon $lastmod): bool
    {
        static $seen = [];

        if (isset($seen[$loc])) {
            return false;
        }

        $seen[$loc] = true;

        $writer->startElement('url');
        $writer->writeElement('loc', $loc);

        if ($lastmod) {
            $writer->writeElement('lastmod', $lastmod->toAtomString());
        }

        $writer->endElement();

        return true;
    }

    protected function humanSize(string $path): string
    {
        $bytes = filesize($path) ?: 0;

        return $bytes > 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
