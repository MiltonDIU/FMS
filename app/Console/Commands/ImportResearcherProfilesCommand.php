<?php

namespace App\Console\Commands;

use App\Models\ResearchInterest;
use App\Models\SocialLink;
use App\Models\SocialMediaPlatform;
use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings the research directory's own record of a teacher onto their profile.
 *
 * The file is the Directorate of Research's list: a biography, a set of
 * expertise areas, and the scholarly profiles a researcher is reachable at.
 * None of it was in the system, and all of it is the sort of thing a teacher
 * would otherwise be asked to type in again.
 *
 * Matching goes through the DIU portfolio URL. Every entry carries one —
 * https://faculty.daffodilvarsity.edu.bd/profile/mct/akhter.html — and the name
 * before .html is the same handle the teachers table stores as `webpage`. It is
 * an exact identifier rather than a name to be guessed at, which matters on a
 * file where the same person appears twice under two spellings of their title.
 * All 197 handles in the file match a teacher.
 */
class ImportResearcherProfilesCommand extends Command
{
    protected $signature = 'import:researcher-profiles
                            {--file= : Path to researchers.json (defaults to the copy in public/documents)}
                            {--limit=0 : Limit the number of researchers to process}
                            {--dry-run : Report what would change, write nothing}
                            {--overwrite : Replace a biography the system already holds}
                            {--left-out= : Where to write the researchers this run could not place (default storage/app/public/exports/researchers_left_out.json)}';

    protected $description = 'Import biographies, expertise and scholarly profile links from the research directory export';

    /**
     * Which platform each field is meant for.
     *
     * The intent of the column, not the last word on it — see platformFor.
     */
    protected const FIELD_PLATFORMS = [
        'diuPortfolio' => 'Website',
        'orcidProfile' => 'ORCID',
        'scopusProfile' => 'Scopus',
        'googleSite' => 'Google Scholar',
        'wosProfile' => 'Web of Science',
    ];

    /**
     * The host a link actually points at, which beats the column it arrived in.
     *
     * The file is hand-kept and the columns have drifted: two Google Scholar
     * pages and a ResearchGate one sit under scopusProfile, an ORCID under it
     * too, and a portfolio page under googleSite. Filing a Scholar link as a
     * Scopus profile would put a wrong identifier on a public page, so the host
     * is read and believed where it is recognised.
     */
    protected const HOST_PLATFORMS = [
        'orcid.org' => 'ORCID',
        'scopus.com' => 'Scopus',
        'scholar.google' => 'Google Scholar',
        'webofscience.com' => 'Web of Science',
        // Publons was folded into Web of Science; the old links still resolve.
        'publons.com' => 'Web of Science',
        'researchgate.net' => 'ResearchGate',
        'faculty.daffodilvarsity.edu.bd' => 'Website',
    ];

    /**
     * Researchers this run could not place, kept whole.
     *
     * Whole records rather than names, because the file they are written to is
     * meant to be repaired and fed back through --file: add the portfolio URL
     * that is missing and the entry imports like any other. A list of names
     * would only say who was lost.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $leftOut = [];

    public function handle(): int
    {
        $file = $this->option('file')
            ?: public_path('documents/old publication/researchers.json');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $records = json_decode(file_get_contents($file), true);

        if (! is_array($records)) {
            $this->error("Invalid JSON in {$file}");

            return self::FAILURE;
        }

        $researchers = $this->uniqueByHandle($records);

        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $researchers = array_slice($researchers, 0, $limit, true);
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? '🔍 Dry run — nothing will be written'
            : '🚀 Importing researcher profiles...');

        $platforms = $this->platforms();
        $stats = ['matched' => 0, 'bios' => 0, 'bios_kept' => 0, 'interests' => 0, 'links' => 0, 'usernames' => 0, 'flagged' => 0];

        $bar = $this->output->createProgressBar(count($researchers));
        $bar->start();

        foreach ($researchers as $handle => $researcher) {
            $teacher = Teacher::whereRaw('LOWER(webpage) = ?', [$handle])->first();

            if (! $teacher) {
                $this->leftOut[] = [
                    'left_out_because' => "no teacher has webpage \"{$handle}\"",
                ] + $researcher;

                $bar->advance();

                continue;
            }

            $stats['matched']++;

            if ($dryRun) {
                $this->tally($teacher, $researcher, $platforms, $stats);
                $bar->advance();

                continue;
            }

            DB::transaction(function () use ($teacher, $researcher, $platforms, &$stats) {
                $this->applyBiography($teacher, $researcher, $stats);
                $this->applyInterests($teacher, $researcher, $stats);
                $this->applyLinks($teacher, $researcher, $platforms, $stats);
                $this->markAsResearcher($teacher, $stats);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'Count'], [
            ['Researchers in file', count($records)],
            ['Left out', count($this->leftOut)],
            ['After merging duplicates', count($researchers)],
            ['Matched to a teacher', $stats['matched']],
            ['Biographies written', $stats['bios']],
            ['Biographies left alone', $stats['bios_kept']],
            ['Research interests added', $stats['interests']],
            ['Profile links added', $stats['links']],
            ['Usernames filled in on existing links', $stats['usernames']],
            ['Newly marked as researchers', $stats['flagged']],
        ]);

        $this->reportLeftOut();

        return self::SUCCESS;
    }

    /**
     * Writes out everyone this run could not place, and says where.
     *
     * Written on a dry run too. It reports rather than changes anything, and it
     * is the half of the run somebody has to act on — withholding it until a
     * real import would mean finding out who is missing only after the import
     * has already happened.
     */
    protected function reportLeftOut(): void
    {
        if ($this->leftOut === []) {
            $this->info('Every researcher in the file was placed.');

            return;
        }

        $path = $this->option('left-out')
            ?: storage_path('app/public/exports/researchers_left_out.json');

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($this->leftOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->warn(count($this->leftOut) . ' researcher(s) could not be placed. Written to:');
        $this->line('  ' . $path);
        $this->newLine();

        $this->table(
            ['Name', 'Department', 'Why'],
            array_map(fn (array $r) => [
                $r['name'] ?? '—',
                \Illuminate\Support\Str::limit($r['department'] ?? '—', 40),
                $r['left_out_because'],
            ], $this->leftOut),
        );

        // The full path, because --file takes one; a bare filename would be
        // read relative to wherever the command happened to be run from.
        $this->line('Fill in the missing portfolio URLs there, then bring them in with:');
        $this->line('  php artisan import:researcher-profiles --file="' . $path . '"');
    }

    /**
     * One entry per portfolio handle, with repeats merged rather than dropped.
     *
     * The portfolio URL is the only thing two entries are ever judged the same
     * by. Names are not compared and deliberately so: the three repeats in the
     * file are one person each written twice under two spellings of their title
     * — "Engr. Md. Imran Hasan Bappy" and "Mr. Md. Imran Hasan Bappy" — and a
     * name match would be a guess where the URL is an identifier. All three
     * pairs carry the same portfolio URL character for character, and no two
     * different URLs reduce to the same handle, so merging by handle is merging
     * by URL.
     *
     * Merged field by field, because the copies disagree about which fields are
     * filled: one has the longer biography, the other the longer list of
     * expertise. Taking either copy whole would throw away what the other knew.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, array<string, mixed>>  handle => researcher
     */
    protected function uniqueByHandle(array $records): array
    {
        $byHandle = [];

        foreach ($records as $record) {
            $handle = $this->handleFor($record['diuPortfolio'] ?? '');

            if ($handle === null) {
                /*
                 * No portfolio URL to match on, so there is nothing to attach
                 * this biography to. Thirteen entries are in that state: twelve
                 * left the field empty and one holds the word "daffodilvarsity"
                 * where the address should be. Kept rather than skipped in
                 * silence — they are people the directory knows and we do not,
                 * and the only way anyone finds that out is if it is said.
                 */
                $this->leftOut[] = ['left_out_because' => 'no portfolio URL to match on'] + $record;

                continue;
            }

            if (! isset($byHandle[$handle])) {
                $byHandle[$handle] = $record;

                continue;
            }

            $byHandle[$handle] = $this->merge($byHandle[$handle], $record);
        }

        return $byHandle;
    }

    /**
     * @param  array<string, mixed>  $kept
     * @param  array<string, mixed>  $other
     * @return array<string, mixed>
     */
    protected function merge(array $kept, array $other): array
    {
        if (mb_strlen((string) ($other['bio'] ?? '')) > mb_strlen((string) ($kept['bio'] ?? ''))) {
            $kept['bio'] = $other['bio'];
        }

        $expertise = array_merge($kept['expertise'] ?? [], $other['expertise'] ?? []);
        $kept['expertise'] = array_values(array_unique(array_filter(
            array_map(fn ($item) => trim((string) $item), $expertise),
            'strlen',
        )));

        foreach (array_keys(self::FIELD_PLATFORMS) as $field) {
            if (blank($kept[$field] ?? null) && filled($other[$field] ?? null)) {
                $kept[$field] = $other[$field];
            }
        }

        return $kept;
    }

    /**
     * The handle a portfolio URL names, or null when there is none in it.
     *
     * Not anchored to the end of the string on purpose. One row reads
     * "daffhttps://faculty.daffodilvarsity.edu.bd/profile/cse/nushrat.htmlodilvarsity",
     * where a copy-paste has wrapped the word "daffodilvarsity" around the real
     * URL — the address is still in there, and anchoring would lose that person
     * over a typing slip. The one row left holding no URL at all is skipped and
     * reported.
     */
    protected function handleFor(string $portfolio): ?string
    {
        return preg_match('~/([^/]+)\.html?~i', trim($portfolio), $matches)
            ? mb_strtolower($matches[1])
            : null;
    }

    /** @param  array<string, mixed>  $researcher */
    protected function applyBiography(Teacher $teacher, array $researcher, array &$stats): void
    {
        $bio = trim((string) ($researcher['bio'] ?? ''));

        if ($bio === '') {
            return;
        }

        // A biography somebody has written here outranks the directory's, unless
        // this run was told otherwise.
        if (filled($teacher->bio) && ! $this->option('overwrite')) {
            $stats['bios_kept']++;

            return;
        }

        $teacher->bio = $bio;
        $teacher->save();

        $stats['bios']++;
    }

    /**
     * Record that this teacher is in the research directory.
     *
     * Everything above — the biography, the expertise, the scholarly links —
     * came out of the Directorate of Research's file, and the flag is what says
     * so afterwards. A second site reads these profiles back through an API,
     * and this is the column it filters on; without it the only way to know who
     * belongs in that set would be to re-read the file.
     *
     * Only ever turned on here. Turning it off is a decision for the teachers
     * table screen, and a run of this command should not quietly undo one.
     *
     * @param  array<string, int>  $stats
     */
    protected function markAsResearcher(Teacher $teacher, array &$stats): void
    {
        if ($teacher->is_researcher) {
            return;
        }

        $teacher->is_researcher = true;
        $teacher->save();

        $stats['flagged']++;
    }

    /** @param  array<string, mixed>  $researcher */
    protected function applyInterests(Teacher $teacher, array $researcher, array &$stats): void
    {
        $existing = $teacher->researchInterests()
            ->pluck('interest')
            ->map(fn ($interest) => mb_strtolower(trim((string) $interest)))
            ->flip();

        $sortOrder = (int) $teacher->researchInterests()->max('sort_order');

        foreach ($researcher['expertise'] ?? [] as $interest) {
            $interest = trim((string) $interest);

            if ($interest === '' || $existing->has(mb_strtolower($interest))) {
                continue;
            }

            ResearchInterest::create([
                'teacher_id' => $teacher->id,
                'interest' => $interest,
                'sort_order' => ++$sortOrder,
            ]);

            $existing->put(mb_strtolower($interest), true);
            $stats['interests']++;
        }
    }

    /**
     * @param  array<string, mixed>  $researcher
     * @param  array<string, SocialMediaPlatform>  $platforms
     */
    protected function applyLinks(Teacher $teacher, array $researcher, array $platforms, array &$stats): void
    {
        $sortOrder = (int) $teacher->socialLinks()->max('sort_order');

        foreach (self::FIELD_PLATFORMS as $field => $intended) {
            $url = trim((string) ($researcher[$field] ?? ''));

            if ($url === '' || ! str_starts_with(mb_strtolower($url), 'http')) {
                continue;
            }

            $platform = $platforms[$this->platformFor($url, $intended)] ?? null;

            if ($platform === null) {
                continue;
            }

            $username = $this->usernameIn($url, $platform, $teacher);

            // Matched on the address as well as the platform: a teacher may hold
            // more than one Website link, and re-running must not add a second
            // copy of one already here.
            $existing = SocialLink::where('teacher_id', $teacher->id)
                ->where('social_media_platform_id', $platform->id)
                ->where('url', $url)
                ->first();

            if ($existing) {
                /*
                 * A link already here keeps its address and gains only what it
                 * is missing. Runs before this one left the portfolio links
                 * without a username — Website declares no base URL, so there
                 * was nothing to read one out of — and those rows are already
                 * imported. Filling the blank is how the fix reaches them
                 * without anybody re-importing.
                 */
                if (blank($existing->username) && filled($username)) {
                    $existing->update(['username' => $username]);
                    $stats['usernames']++;
                }

                continue;
            }

            SocialLink::create([
                'teacher_id' => $teacher->id,
                'social_media_platform_id' => $platform->id,
                'username' => $username,
                'url' => $url,
                'sort_order' => ++$sortOrder,
            ]);

            $stats['links']++;
        }
    }

    /** The platform a URL belongs to: what its host says, or what its column meant. */
    protected function platformFor(string $url, string $intended): string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (self::HOST_PLATFORMS as $needle => $platform) {
            if (str_contains($host, $needle)) {
                return $platform;
            }
        }

        return $intended;
    }

    /**
     * Which query parameter carries the identifier, where one does.
     *
     * These three put it in the query string, and the file is full of URLs that
     * are the right page reached the wrong way. 24 of the 171 ORCID links are
     * "orcid.org/my-orcid?orcid=0000-…" — the page you land on from your own
     * dashboard — where stripping the prefix yields the literal "my-orcid" as
     * 24 different people's identifier. Others carry the parameters in another
     * order, so the URL does not begin with the platform's base and prefix
     * stripping gives nothing at all.
     */
    protected const IDENTIFIER_PARAMS = [
        'ORCID' => 'orcid',
        'Google Scholar' => 'user',
        'Scopus' => 'authorId',
    ];

    /**
     * The identifier inside a profile URL, or null when there is none to read.
     *
     * Two ways, in order of how much they can be trusted: the query parameter
     * the platform names its identifier in, and failing that whatever follows
     * the platform's own base URL.
     */
    protected function usernameIn(string $url, SocialMediaPlatform $platform, Teacher $teacher): ?string
    {
        $parameter = self::IDENTIFIER_PARAMS[$platform->name] ?? null;

        if ($parameter !== null) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (filled($query[$parameter] ?? null)) {
                return mb_substr(trim((string) $query[$parameter]), 0, 255);
            }
        }

        $base = trim((string) $platform->base_url);

        if ($base !== '' && str_starts_with($url, $base)) {
            // Cut at the first separator: a base URL ending in a query parameter
            // leaves the rest of the query attached, and "MEC2pZkAAAAJ&hl=en" is
            // not anybody's identifier.
            $username = trim(substr($url, strlen($base)));
            $username = preg_split('/[&?#\/]/', $username)[0] ?? '';

            if ($username !== '') {
                return mb_substr($username, 0, 255);
            }
        }

        /*
         * The portfolio, which declares no base URL to strip and so came out
         * blank on every teacher.
         *
         * Its identifier is the handle in the address, and that handle is by
         * construction the teacher's own `webpage` — it is what this whole
         * import matched on. Taken from the teacher rather than re-read from
         * the URL so it keeps the capitalisation the system stores: the file
         * writes handles in lower case and some of ours are not.
         */
        if ($base === '' && filled($teacher->webpage) && $this->handleFor($url) !== null) {
            return mb_substr((string) $teacher->webpage, 0, 255);
        }

        return null;
    }

    /**
     * The platforms this import writes to, by name.
     *
     * @return array<string, SocialMediaPlatform>
     */
    protected function platforms(): array
    {
        $wanted = array_unique(array_merge(
            array_values(self::FIELD_PLATFORMS),
            array_values(self::HOST_PLATFORMS),
        ));

        $platforms = SocialMediaPlatform::whereIn('name', $wanted)->get()->keyBy('name');

        foreach ($wanted as $name) {
            if (! $platforms->has($name)) {
                $this->warn("Platform \"{$name}\" is not seeded; its links will be skipped.");
            }
        }

        return $platforms->all();
    }

    /**
     * What a dry run would have done, counted without writing.
     *
     * @param  array<string, mixed>  $researcher
     * @param  array<string, SocialMediaPlatform>  $platforms
     */
    protected function tally(Teacher $teacher, array $researcher, array $platforms, array &$stats): void
    {
        $bio = trim((string) ($researcher['bio'] ?? ''));

        if ($bio !== '') {
            if (filled($teacher->bio) && ! $this->option('overwrite')) {
                $stats['bios_kept']++;
            } else {
                $stats['bios']++;
            }
        }

        $existing = $teacher->researchInterests()
            ->pluck('interest')
            ->map(fn ($interest) => mb_strtolower(trim((string) $interest)))
            ->flip();

        foreach ($researcher['expertise'] ?? [] as $interest) {
            $interest = trim((string) $interest);

            if ($interest !== '' && ! $existing->has(mb_strtolower($interest))) {
                $existing->put(mb_strtolower($interest), true);
                $stats['interests']++;
            }
        }

        foreach (self::FIELD_PLATFORMS as $field => $intended) {
            $url = trim((string) ($researcher[$field] ?? ''));

            if ($url === '' || ! str_starts_with(mb_strtolower($url), 'http')) {
                continue;
            }

            $platform = $platforms[$this->platformFor($url, $intended)] ?? null;

            if ($platform === null) {
                continue;
            }

            $existing = SocialLink::where('teacher_id', $teacher->id)
                ->where('social_media_platform_id', $platform->id)
                ->where('url', $url)
                ->first();

            if (! $existing) {
                $stats['links']++;
            } elseif (blank($existing->username) && filled($this->usernameIn($url, $platform, $teacher))) {
                $stats['usernames']++;
            }
        }
    }
}
